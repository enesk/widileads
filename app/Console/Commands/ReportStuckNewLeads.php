<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Mail\Lead\StuckNewLeads;
use App\Models\Lead;
use App\Models\Scopes\TenantScopes;
use App\Services\LeadStateService;
use App\Services\SupportMailbox;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Ticket #108: Meldet Leads, die im Zustand `neu` liegengeblieben sind.
 *
 * Die Pruefung aus FB-033 laeuft in der Warteschlange (ScreenCreatedLead) und
 * braucht Sekunden. Ein Lead, der Stunden spaeter noch auf `neu` steht, ist
 * nie geprueft worden -- der Job ist ausgefallen. Genau das ist im September
 * 2026 passiert: Eine falsch geschriebene Queue-Verbindung liess 15 Jobs
 * scheitern, 12 Leads blieben einen Monat lang in `neu` liegen. Sie wurden
 * nie kaufbar, erschienen nie im Marktplatz, ihre Betreiber bekamen keine
 * Meldung -- und niemandem fiel es auf, weil ein Lead in `neu` nirgends
 * auftaucht. Dieser Lauf ist der Melder, der damals gefehlt hat.
 *
 * Zwei Betriebsarten:
 *
 * - Ohne Schalter: melden. Konsolenausgabe, Protokolleintrag und eine Mail an
 *   die Betreiberadresse (wie `wallet:verify`), Rueckgabewert 1, solange
 *   Treffer offen sind. Das ist die Form, die im Zeitplan laeuft.
 * - `--retire`: die Treffer auf `abgelaufen` setzen. Das ist die Entscheidung
 *   fuer liegengebliebene Altleads, die nicht mehr in den Marktplatz sollen:
 *   Ein vier Wochen alter Kontakt ist keine frische Ware, und ein Kaeufer
 *   zahlt dafuer den gleichen Preis wie fuer einen Lead von heute. Verschickt
 *   wird dabei nichts: `abgelaufen` loest weder die Marktplatz-Listung noch
 *   die LeadReceived-Meldung aus (die haengt am Wechsel nach `verfuegbar`).
 *
 * `--retire` ist nicht rueckholbar -- `abgelaufen` ist ein Endzustand. Deshalb
 * fragt der Lauf nach, solange `--force` fehlt, und im Zeitplan steht er ohne
 * beides. Wer stattdessen das Screening nachholen will, ist bei
 * LeadScreeningService::screen() richtig; das ist eine Geldentscheidung und
 * bewusst kein Schalter dieses Kommandos.
 */
class ReportStuckNewLeads extends Command
{
    /**
     * Leads je Durchgang. Gross genug fuer wenige Abfragen, klein genug, dass
     * ein Ausfall mit tausenden Leads nicht den Speicher sprengt.
     */
    private const CHUNK_SIZE = 200;

    /**
     * Belegzeilen in Konsole und Mail. Darueber steht nur die Sammelzeile --
     * vollstaendig bleibt der Befund im Protokoll.
     */
    private const DETAIL_LIMIT = 50;

    protected $signature = 'leads:stuck-new
        {--hours= : Stunden in `neu`, ab denen ein Lead als liegengeblieben gilt (Vorgabe aus der Konfiguration)}
        {--retire : Treffer auf `abgelaufen` setzen -- ohne Marktplatz und ohne Meldung an den Betreiber}
        {--justification= : Begruendung, die am Zustandswechsel protokolliert wird}
        {--force : Rueckfrage vor dem Abschluss ueberspringen}';

    protected $description = 'Melde Leads, die im Zustand `neu` liegengeblieben sind, und schliesse sie auf Wunsch ab.';

    public function __construct(
        private readonly LeadStateService $leadStateService,
        private readonly SupportMailbox $support,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $hours = $this->hours();

        if ($hours < 1) {
            $this->error('--hours muss mindestens 1 sein -- ein Lead in `neu` ist in der Sekunde seiner Entstehung kein Befund.');

            return self::FAILURE;
        }

        /** @var list<array{id: int, tenant_id: int|null, funnel_id: int|null, created_at: string, age_hours: int}> $stuck */
        $stuck = [];

        self::stuckNewLeadsQuery($hours)->chunkById(self::CHUNK_SIZE, function ($leads) use (&$stuck): void {
            foreach ($leads as $lead) {
                $stuck[] = $this->describe($lead);
            }
        });

        if ($stuck === []) {
            $this->info(sprintf('Kein Lead steht laenger als %d Stunde(n) in `neu`.', $hours));

            return self::SUCCESS;
        }

        $this->render($stuck, $hours);

        Log::warning('Leads sind im Zustand `neu` liegengeblieben.', [
            'hours' => $hours,
            'count' => count($stuck),
            'lead_ids' => array_column($stuck, 'id'),
        ]);

        if (! $this->option('retire')) {
            $this->notify($stuck, $hours);

            // Rueckgabewert 1: Ein liegengebliebener Lead ist ein Vorfall. Wer
            // den Lauf ueberwacht, soll ihn am Rueckgabewert erkennen und nicht
            // erst in der Ausgabe suchen muessen.
            return self::FAILURE;
        }

        $justification = $this->justification();

        if ($justification === null) {
            return self::FAILURE;
        }

        if (! $this->confirmedRetirement($stuck)) {
            $this->warn('Abgebrochen -- kein Lead wurde abgeschlossen.');

            return self::FAILURE;
        }

        $retired = $this->retire($stuck, $hours, $justification);

        $this->info(sprintf('%d Lead(s) als liegengeblieben auf `abgelaufen` gesetzt.', $retired));

        return self::SUCCESS;
    }

    /**
     * Die Trefferbedingung, abfragbar ohne den Lauf.
     *
     * Ohne Mandanten-Scopes, weil der Lauf ohne Kaeuferkontext auf der Konsole
     * stattfindet -- mit Scope faende er nichts (wie `leads:resolve-expired`).
     *
     * Gerechnet wird ab `created_at`: Ein Lead entsteht in `neu`, und der
     * Pruefjob wird im selben Moment eingereiht. Die Verweildauer im Zustand
     * ist damit sein Alter, ohne dass das Zustandsprotokoll befragt werden
     * muss.
     *
     * @param  int|null  $hours  Verweildauer in Stunden; Vorgabe aus der Konfiguration
     * @param  Carbon|null  $at  Stichtag des Laufs; Vorgabe ist jetzt
     * @return Builder<Lead>
     */
    public static function stuckNewLeadsQuery(?int $hours = null, ?Carbon $at = null): Builder
    {
        $hours ??= (int) config('funnel.lead.stuck_new_alert_hours');

        return Lead::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->where('lead_state', LeadState::NEU)
            ->where('created_at', '<=', ($at ?? now())->copy()->subHours(max($hours, 0)));
    }

    /**
     * Stunden aus der Option, sonst aus der Konfiguration.
     */
    private function hours(): int
    {
        $option = $this->option('hours');

        return $option === null
            ? (int) config('funnel.lead.stuck_new_alert_hours')
            : (int) $option;
    }

    /**
     * @return array{id: int, tenant_id: int|null, funnel_id: int|null, created_at: string, age_hours: int}
     */
    private function describe(Lead $lead): array
    {
        return [
            'id' => (int) $lead->getKey(),
            'tenant_id' => $lead->tenant_id === null ? null : (int) $lead->tenant_id,
            'funnel_id' => $lead->funnel_id === null ? null : (int) $lead->funnel_id,
            'created_at' => (string) $lead->created_at,
            'age_hours' => (int) $lead->created_at?->diffInHours(now()),
        ];
    }

    /**
     * @param  list<array{id: int, tenant_id: int|null, funnel_id: int|null, created_at: string, age_hours: int}>  $stuck
     */
    private function render(array $stuck, int $hours): void
    {
        $this->warn(sprintf(
            '%d Lead(s) stehen laenger als %d Stunde(n) in `neu` -- ihre Pruefung ist nie gelaufen.',
            count($stuck),
            $hours,
        ));

        $this->table(
            ['Lead', 'Workspace', 'Funnel', 'Angelegt', 'Stunden'],
            array_map(
                static fn (array $row): array => [
                    $row['id'],
                    $row['tenant_id'] ?? '-',
                    $row['funnel_id'] ?? '-',
                    $row['created_at'],
                    $row['age_hours'],
                ],
                array_slice($stuck, 0, self::DETAIL_LIMIT),
            ),
        );

        if (count($stuck) > self::DETAIL_LIMIT) {
            $this->line(sprintf('... und %d weitere.', count($stuck) - self::DETAIL_LIMIT));
        }
    }

    /**
     * Meldung an die Betreiberadresse -- der einzige Weg, auf dem ein Mensch
     * ohne Blick ins Protokoll von einem Ausfall erfaehrt.
     *
     * @param  list<array{id: int, tenant_id: int|null, funnel_id: int|null, created_at: string, age_hours: int}>  $stuck
     */
    private function notify(array $stuck, int $hours): void
    {
        $recipient = $this->support->addressOrLog(
            'Keine Meldung zu liegengebliebenen Leads verschickt: app.support_email ist nicht brauchbar gesetzt.',
            ['count' => count($stuck)],
        );

        if ($recipient === null) {
            $this->warn('Keine Meldung verschickt: app.support_email ist nicht brauchbar gesetzt (leer, ungueltig oder Starterkit-Domain).');

            return;
        }

        Mail::to($recipient)->send(new StuckNewLeads(
            array_slice($stuck, 0, self::DETAIL_LIMIT),
            $hours,
            count($stuck),
        ));
    }

    /**
     * Die Begruendung, die am Zustandswechsel haengt -- oder null, wenn sie zu
     * kurz ist. Dieselbe Mindestlaenge wie beim Handeingriff im Admin-Panel
     * (FB-036): Ein Protokolleintrag ohne brauchbare Begruendung ist wertlos.
     */
    private function justification(): ?string
    {
        $justification = trim((string) ($this->option('justification')
            ?? 'Pruefung nie gelaufen (Ausfall der Warteschlange); Lead zu alt fuer den Marktplatz.'));

        $minimum = (int) config('funnel.lead.manual_state_min_justification_length');

        if (mb_strlen($justification) < $minimum) {
            $this->error(sprintf('--justification braucht mindestens %d Zeichen.', $minimum));

            return null;
        }

        return $justification;
    }

    /**
     * Rueckfrage vor dem Abschluss: `abgelaufen` ist ein Endzustand, aus dem
     * kein Uebergang mehr herausfuehrt.
     *
     * @param  list<array{id: int, tenant_id: int|null, funnel_id: int|null, created_at: string, age_hours: int}>  $stuck
     */
    private function confirmedRetirement(array $stuck): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return $this->confirm(sprintf(
            '%d Lead(s) endgueltig auf `abgelaufen` setzen? Das ist nicht rueckholbar.',
            count($stuck),
        ), false);
    }

    /**
     * Setzt die Treffer auf `abgelaufen`.
     *
     * Gewechselt wird ueber LeadStateService::transition() -- mit Sperre,
     * Protokolleintrag und Preisfestschreibung wie jeder andere Wechsel
     * (Architekturleitsatz 2). Ein Lead, der zwischen Abfrage und Wechsel
     * doch noch geprueft wurde, faellt mit einer Warnung heraus, statt den
     * Lauf abzubrechen.
     *
     * @param  list<array{id: int, tenant_id: int|null, funnel_id: int|null, created_at: string, age_hours: int}>  $stuck
     * @return int Anzahl der abgeschlossenen Leads.
     */
    private function retire(array $stuck, int $hours, string $justification): int
    {
        $retired = 0;

        foreach ($stuck as $row) {
            $lead = Lead::query()
                ->withoutGlobalScopes(TenantScopes::names())
                ->whereKey($row['id'])
                ->first();

            if (! $lead instanceof Lead || $lead->lead_state !== LeadState::NEU) {
                $this->warn(sprintf('Lead %d uebergangen: steht nicht mehr in `neu`.', $row['id']));

                continue;
            }

            $this->leadStateService->transition(
                $lead,
                LeadState::ABGELAUFEN,
                LeadTransitionReason::MANUAL_OVERRIDE,
                null,
                [
                    'stuck_in_new' => true,
                    'threshold_hours' => $hours,
                    'age_hours' => $row['age_hours'],
                    'justification' => $justification,
                ],
            );

            $retired++;
        }

        Log::info('Liegengebliebene Leads abgeschlossen.', [
            'retired' => $retired,
            'threshold_hours' => $hours,
            'justification' => $justification,
        ]);

        return $retired;
    }
}
