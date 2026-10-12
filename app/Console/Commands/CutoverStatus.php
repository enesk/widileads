<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\PurchaseStatus;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Scopes\TenantScopes;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ticket #94: Zeigt in einem Lauf, wie reif der Cutover der
 * Erreichbarkeitspruefung (`LEAD_CALLS_ENFORCE_FROM`) ist.
 *
 * Das Scharfstellen war bis hierher eine Betreiber-Sitzung aus fuenf
 * Handgriffen im Handbuch und zwei langen `tinker --execute`-Blocks; jeder
 * Durchgang (Tickets #76, #85) hat dieselben Werte von Hand neu gemessen.
 * Alle drei Vorbedingungen, das fruehste zulaessige Datum und der Trockenlauf
 * sind maschinell abfragbar -- und genau das macht dieses Kommando.
 *
 * Nur lesend. Es wird nichts gesetzt, nichts abgerechnet und nichts gewaehlt;
 * die einzigen Fernabfragen sind die von `leads:check-call-setup`, das fuer
 * Vorbedingung 1 unveraendert mitlaeuft.
 */
class CutoverStatus extends Command
{
    protected $signature = 'leads:cutover-status
        {--date= : Datum fuer den Trockenlauf (YYYY-MM-DD); Vorgabe ist der gesetzte Cutover, sonst der Vorschlag aus call_attempts}
        {--at= : Stichtag, zu dem der Lauf gerechnet wird (Vorgabe: jetzt) -- fuer die Frage "was greift der erste Lauf nach Fristablauf?"}
        {--skip-setup-check : Vorbedingung 1 ueberspringen (keine Twilio-Abfrage)}';

    protected $description = 'Show how ripe the contact-check cutover is: three preconditions, earliest allowed date, dry run.';

    /** @var list<array{0: string, 1: string, 2: string}> */
    private array $rows = [];

    private bool $hasFailure = false;

    public function handle(): int
    {
        $this->checkSetup();
        $suggestion = $this->checkCallAttempts();
        $this->checkVerifiedPhones();
        $earliest = $this->checkEarliestDate();

        $this->table(['Pruefung', 'Stand', 'Befund'], $this->rows);

        $this->dryRun($suggestion, $earliest);

        if ($this->hasFailure) {
            $this->error('Der Cutover darf so nicht scharf gestellt werden -- erst die als FEHLER markierten Vorbedingungen herstellen. '
                .'LEAD_CALLS_ENFORCE_FROM bleibt leer, das Regelwerk damit aus.');

            return self::FAILURE;
        }

        $this->info('Alle Vorbedingungen des Cutovers stehen. Reihenfolge beim Scharfstellen: '
            .'docs/funnel-builder/betriebshandbuch.md, Abschnitt 1.');

        return self::SUCCESS;
    }

    /**
     * Vorbedingung 1: die Einrichtung des Anrufwegs. Bewusst der echte Lauf
     * von `leads:check-call-setup --production` und keine zweite Pruefmenge --
     * eine Kopie wuerde beim ersten Zusatz dort auseinanderlaufen. Die Tabelle
     * des Unterkommandos bleibt stumm; wer die Einzelbefunde braucht, ruft es
     * selbst auf.
     */
    private function checkSetup(): void
    {
        if ($this->option('skip-setup-check')) {
            $this->rows[] = ['1. Einrichtung des Anrufwegs', 'HINWEIS', 'Uebersprungen (--skip-setup-check) -- vor dem Scharfstellen nachholen.'];

            return;
        }

        $failed = $this->callSilent('leads:check-call-setup', ['--production' => true]) !== self::SUCCESS;

        $this->assert(
            '1. Einrichtung des Anrufwegs',
            ! $failed,
            'leads:check-call-setup --production meldet FEHLER -- Einzelbefunde mit "php artisan leads:check-call-setup --production".',
            'leads:check-call-setup --production laeuft ohne FEHLER.',
        );
    }

    /**
     * Vorbedingung 2: Solange es keinen einzigen Anrufversuch gibt, gibt es
     * auch keinen "ersten Tag, an dem das Anrufpanel erreichbar war", und
     * jedes Cutover-Datum waere geraten.
     *
     * @return string|null Vorschlag fuer das Cutover-Datum (YYYY-MM-DD)
     */
    private function checkCallAttempts(): ?string
    {
        // Direkt auf der Tabelle: `call_attempts` haengt ueber BelongsToTenant
        // an einem Mandantenkontext, den die Konsole nicht hat.
        $first = DB::table('call_attempts')->min('created_at');
        $count = DB::table('call_attempts')->count();

        $this->assert(
            '2. Erster echter Anrufversuch',
            $first !== null,
            'call_attempts ist leer -- ueber das Portal wurde noch nie gewaehlt. '
                .'Weg: eigene Rufnummer bestaetigen, "leads:call-probe --to=<zweite eigene Nummer>", anrufen.',
            $count.' Versuch(e), aeltester am '.$this->asDateTime($first).' -- Vorschlag fuer den Cutover: '.$this->asDate($first),
        );

        return $first === null ? null : $this->asDate($first);
    }

    /**
     * Vorbedingung 3 (in Ticket #85 live gemessen, 0 von 4 Konten): Ohne ein
     * Konto mit bestaetigter Rufnummer sperrt EnsurePhoneVerified jeden
     * eingeloggten Screen -- das Anrufpanel ist dann fuer niemanden
     * erreichbar, und Vorbedingung 2 kann niemand erfuellen.
     */
    private function checkVerifiedPhones(): void
    {
        $total = User::query()->count();
        $verified = User::query()->whereNotNull('phone_number_verified_at')->count();

        $this->assert(
            '3. Konto mit bestaetigter Rufnummer',
            $verified > 0,
            '0 von '.$total.' Konten haben users.phone_number_verified_at gesetzt -- EnsurePhoneVerified sperrt damit jeden '
                .'eingeloggten Screen, das Anrufpanel ist fuer niemanden erreichbar. Im Portal unter /onboarding/telefon nachholen.',
            $verified.' von '.$total.' Konten haben eine bestaetigte Rufnummer.',
        );
    }

    /**
     * Das fruehste zulaessige Datum: ein Tag nach der letzten Auslieferung
     * eines Leads mit offener Reservierung.
     *
     * Ein Cutover darunter rechnet diese Leads beim ersten Lauf nach
     * Fristablauf ab, obwohl zu ihnen kein Anrufversuch existiert und der
     * Kaeufer durch die Telefonsperre nie anrufen konnte.
     *
     * Ein Tag, nicht eine Sekunde: `Carbon::parse('2026-10-12')` ist 00:00 Uhr
     * (siehe ResolveExpiredLeads::expiredLeadsQuery()), ein Datum trennt also
     * nur auf Tagesgrenze.
     */
    private function checkEarliestDate(): ?string
    {
        $latest = Lead::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->whereNotNull('delivered_at')
            ->whereIn('id', LeadPurchase::query()->select('lead_id')->where('status', PurchaseStatus::RESERVED->value))
            ->max('delivered_at');

        if ($latest === null) {
            $this->rows[] = [
                '4. Fruehestes zulaessiges Datum', 'OK',
                'Kein Lead mit offener Reservierung -- jedes Datum ist zulaessig.',
            ];

            return null;
        }

        $earliest = Carbon::parse((string) $latest)->startOfDay()->addDay()->toDateString();

        $this->rows[] = [
            '4. Fruehestes zulaessiges Datum', 'HINWEIS',
            $earliest.' -- letzte Auslieferung mit offener Reservierung am '.$this->asDateTime($latest)
                .'. Ein frueheres Datum rechnet diese Leads ab.',
        ];

        return $earliest;
    }

    /**
     * Punkt 5: Was wuerde `leads:resolve-expired` mit diesem Cutover greifen,
     * und was heisst das in Geld?
     *
     * Gerechnet wird gegen ResolveExpiredLeads::expiredLeadsQuery() selbst --
     * die Bedingung steht damit an einer Stelle und nicht noch einmal hier und
     * ein drittes Mal im Handbuch.
     */
    private function dryRun(?string $suggestion, ?string $earliest): void
    {
        $date = $this->resolveDryRunDate($suggestion);

        if ($date === null) {
            $this->warn('5. Trockenlauf: kein Datum -- weder --date= uebergeben, noch LEAD_CALLS_ENFORCE_FROM gesetzt, '
                .'noch ein Anrufversuch vorhanden, aus dem sich eines ergibt.');

            return;
        }

        $at = $this->option('at') === null ? now() : Carbon::parse((string) $this->option('at'));

        $leadIds = ResolveExpiredLeads::expiredLeadsQuery($date, $at)
            ->orderBy('id')
            ->pluck('id');

        /** @var Collection<int, LeadPurchase> $purchases */
        $purchases = LeadPurchase::query()
            ->whereIn('lead_id', $leadIds)
            ->where('status', PurchaseStatus::RESERVED->value)
            ->orderBy('id')
            ->get();

        $priceCents = (int) $purchases->sum('price_cents');
        $surchargeCents = (int) $purchases->sum('surcharge_cents');

        $this->line('');
        $this->line('<options=bold>5. Trockenlauf mit Cutover '.$date.', Stichtag '.$at->format('d.m.Y H:i').'</>');
        $this->line('   Leads: '.$leadIds->count().$this->idList($leadIds));
        $this->line('   Kaufbelege in Reservierung: '.$purchases->count().$this->idList($purchases->pluck('id')));
        $this->line('   Geldsumme: '.number_format($priceCents / 100, 2, ',', '.').' EUR'
            .($surchargeCents > 0 ? ' zzgl. '.number_format($surchargeCents / 100, 2, ',', '.').' EUR Postpaid-Aufschlag' : ''));

        if ($earliest !== null && $date < $earliest) {
            $this->warn('   Dieses Datum liegt vor dem fruehesten zulaessigen ('.$earliest.') -- es rechnet Leads ab, zu denen kein Anrufversuch existiert.');
        }

        if ($leadIds->isNotEmpty()) {
            $this->warn('   Jeder Treffer wird im 03:00-Lauf als billable/deadline abgerechnet. Kommt etwas Unerwartetes vor: '
                .'erst den Cutover spaeter legen, dann abrechnen -- nie umgekehrt.');
        }
    }

    /**
     * Die Nummern dahinter, aber nicht hundert davon: Eine Fristwelle schiebt
     * sonst die Geldsumme aus dem Bild.
     *
     * @param  Collection<int, int>  $ids
     */
    private function idList(Collection $ids): string
    {
        if ($ids->isEmpty()) {
            return '';
        }

        $shown = $ids->take(20);

        return ' ('.$shown->implode(', ').($ids->count() > $shown->count() ? ', ... +'.($ids->count() - $shown->count()) : '').')';
    }

    /**
     * Vorrang: --date=, dann der gesetzte Cutover, dann der Vorschlag aus dem
     * ersten Anrufversuch. Der Trockenlauf soll auch dann etwas sagen, wenn
     * noch nichts gesetzt ist.
     */
    private function resolveDryRunDate(?string $suggestion): ?string
    {
        $given = trim((string) $this->option('date'));

        if ($given !== '') {
            return Carbon::parse($given)->toDateString();
        }

        $configured = trim((string) config('lead_calls.enforce_from'));

        return $configured !== '' ? Carbon::parse($configured)->toDateString() : $suggestion;
    }

    private function asDate(mixed $value): string
    {
        return Carbon::parse((string) $value)->toDateString();
    }

    private function asDateTime(mixed $value): string
    {
        return Carbon::parse((string) $value)->format('d.m.Y H:i:s');
    }

    private function assert(string $label, bool $passed, string $problem, string $detail): void
    {
        if ($passed) {
            $this->rows[] = [$label, 'OK', $detail];

            return;
        }

        $this->hasFailure = true;
        $this->rows[] = [$label, 'FEHLER', $problem];
    }
}
