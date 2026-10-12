<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\PurchaseLead;
use App\Constants\FunnelFieldKey;
use App\Constants\LeadResolutionReason;
use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Constants\PurchaseStatus;
use App\Constants\SaleMode;
use App\Constants\TenantType;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\LeadPurchase;
use App\Models\Scopes\TenantScopes;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CallerIdService;
use App\Services\LeadResolver;
use App\Services\LeadStateService;
use App\Services\ManualLeadStateService;
use App\Services\TenantTypeService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Ticket #89: Ein Uebungs-Lead mit der Nummer des Betreibers, damit der erste
 * echte Portal-Anruf gefuehrt werden kann (Vorarbeit fuer #86/#85).
 *
 * Alle Kaufbelege im Live-Bestand tragen echte Interessentennummern -- ein
 * Probeanruf darueber ist keine Option. Gebraucht wird genau eine Zeile: ein
 * Lead, dessen Nummer dem Betreiber gehoert, gekauft von seinem eigenen
 * Kaeufer-Workspace, damit das Anrufpanel erreichbar ist und der Anruf genau
 * dieses eine Telefon anklingelt.
 *
 * Warum ein Kommando und kein Seeder: Der PortalDemoSeeder ist Prueffallbestand
 * mit drei Workspaces samt Passwoertern, nimmt keine Parameter -- die
 * Zielnummer ist aber der Kern der Aufgabe -- und hat keinen Ort fuer den
 * Rueckbau.
 *
 * **Die Geldseite ist echt.** Gekauft wird ueber App\Actions\PurchaseLead, also
 * ueber denselben Weg wie jeder Portal-Kauf: echter Kaufbeleg im Stand
 * `reserved`, echte Reservierung auf dem Kaeufer-Wallet. Abgebucht wird nie.
 * Beim Rueckbau loest die Reservierung sich auf, und die Gegenbuchung hebt die
 * erste exakt auf -- zwei Journalzeilen, Summe null, `wallet:verify` bleibt
 * glatt. Ein Freibetrag, eine Sonderaufladung oder ein Preis von 0 Cent waere
 * jeweils eine Testzeile in der Geldseite und ist deshalb ausgeschlossen.
 *
 * **Der Rueckbau laeuft ausschliesslich ueber den Fachweg** (LeadResolver, dann
 * ManualLeadStateService). Ein DELETE auf dem Lead ist genau der Fehler aus
 * #80: Die FK-Kaskade raeumt den Kaufbeleg ab und laesst die Reservierung im
 * Journal stehen.
 *
 * Nur die Reihenfolge ist wichtig: erst die Erreichbarkeit entscheiden, dann
 * den Zustand setzen. LeadResolver::resolve() steigt bei einem Lead in einem
 * Endzustand aus (Ticket #92) -- umgekehrt bliebe die Reservierung stehen.
 */
class SeedCallProbeLead extends Command
{
    /** Kennzeichnung, ueber die das Kommando seinen eigenen Lead wiederfindet. */
    private const UTM_SOURCE = 'call-probe';

    private const UTM_CAMPAIGN = 'ticket-89';

    /** Name in den Antworten -- der Uebungs-Lead soll nicht wie eine echte Anfrage aussehen. */
    private const ANSWER_NAME = 'Uebungs-Lead (Probeanruf)';

    /** Steht im Portal am Kauf und beantwortet dort die Frage "was ist das?". */
    private const BUYER_NOTES = 'Uebungskauf fuer den ersten echten Portal-Anruf (Ticket #89). Kein Interessent -- die Nummer gehoert dem Betreiber.';

    /** Pflichtbegruendung des Zwangswechsels; landet im lead_state_log und im Audit-Log. */
    private const JUSTIFICATION = 'Uebungs-Lead fuer den ersten echten Portal-Anruf (Ticket #89), Nummer des Betreibers, kein Interessent.';

    /** So lange wird auf den Zuhoerer SettleLeadPurchase gewartet. */
    private const RELEASE_WAIT_SECONDS = 15;

    protected $signature = 'leads:call-probe
        {--to= : Zielnummer in E.164, Pflicht beim Anlegen (das Telefon des Betreibers)}
        {--buyer= : E-Mail des kaufenden Benutzers; sein Workspace kauft, er ist der Anrufende}
        {--funnel= : Funnel-Kennung (Id, Slug oder Name); Vorgabe ist der erste Funnel mit Einzelverkauf, der nicht dem Kaeufer gehoert}
        {--zip=20095 : PLZ des Uebungs-Leads}
        {--status : Nur anzeigen, ob ein Uebungs-Lead offen ist, und beenden}
        {--teardown : Den offenen Uebungs-Lead zurueckbauen und beenden}
        {--dry-run : Alle Pruefungen laufen lassen und auflisten, was passieren wuerde; nichts schreiben}';

    protected $description = "Create or remove a practice lead whose phone number is the operator's own, so the portal call panel can be exercised once.";

    /** @var list<array{0: string, 1: string, 2: string}> */
    private array $rows = [];

    private bool $failed = false;

    public function __construct(
        private readonly CallerIdService $callerIds,
        private readonly LeadStateService $leadStates,
        private readonly ManualLeadStateService $manualStates,
        private readonly LeadResolver $resolver,
        private readonly PurchaseLead $purchases,
        private readonly TenantTypeService $tenantTypes,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('status')) {
            return $this->showStatus();
        }

        if ($this->option('teardown')) {
            return $this->teardown();
        }

        return $this->create();
    }

    /**
     * Zeigt den offenen Uebungs-Lead -- oder dass keiner offen ist.
     */
    private function showStatus(): int
    {
        $lead = $this->openProbeLead();

        if ($lead === null) {
            $this->info('Kein Uebungs-Lead offen.');

            return self::SUCCESS;
        }

        $this->renderProbe($lead, $this->purchaseOf($lead), 'Uebungs-Lead offen.');
        $this->line('');
        $this->line('Rueckbau: php artisan leads:call-probe --teardown');

        return self::SUCCESS;
    }

    /**
     * Anlegen: Erst laufen alle Pruefungen vollstaendig durch, dann wird
     * geschrieben. Eine Pruefung, die erst nach dem ersten Schreibvorgang
     * greift, hinterlaesst einen halben Uebungs-Lead.
     */
    private function create(): int
    {
        $plan = $this->plan();

        $this->table(['Pruefung', 'Stand', 'Befund'], $this->rows);

        if ($this->failed || $plan === null) {
            $this->error('Es wird nichts angelegt -- erst die als FEHLER markierten Punkte beheben.');

            $open = $this->openProbeLead();

            if ($open !== null) {
                $this->line('');
                $this->renderProbe($open, $this->purchaseOf($open), 'Dieser Uebungs-Lead steht noch offen.');
                $this->line('');
                $this->line('Rueckbau: php artisan leads:call-probe --teardown');
            }

            return self::FAILURE;
        }

        if ($this->isDryRun()) {
            $this->info('Trockenlauf -- es wurde nichts geschrieben.');
            $this->line('Angelegt wuerde: Lead in '.LeadState::NEU->value.' -> '.LeadState::VERFUEGBAR->value
                .' (Grund '.LeadTransitionReason::SCREENING_PASSED->value.'), Kauf im Workspace '
                .$plan['buyer']->getKey().' zu '.$this->euro($plan['price_cents']).', Zielnummer '.$plan['to'].'.');

            return self::SUCCESS;
        }

        return $this->write($plan);
    }

    /**
     * Alle Pruefungen aus Abschnitt 3 der Vorgabe. Jede Zeile nennt den Befund
     * und den naechsten Schritt.
     *
     * Der Funnel steht vor dem Guthaben, obwohl die Vorgabe ihn danach
     * auffuehrt: Der Kaufpreis ist der Preis des Verkaeufers, und der Verkaeufer
     * ist der Mandant des Funnels -- ohne Funnel gibt es keinen Preis, gegen den
     * sich das Guthaben pruefen liesse.
     *
     * @return array{buyer: Tenant, user: User, funnel: Funnel, to: string, zip: string, price_cents: int}|null
     */
    private function plan(): ?array
    {
        $open = $this->openProbeLead();

        $this->assert(
            'Offener Uebungs-Lead',
            $open === null,
            'Lead #'.$open?->getKey().' ('.$open?->lead_state->value.') steht noch offen -- erst zurueckbauen: php artisan leads:call-probe --teardown',
            'keiner offen',
        );

        $user = $this->resolveUser();
        $buyer = $user === null ? null : $this->resolveBuyerTenant($user);

        if ($user === null) {
            $this->pending('Kaeufer-Workspace', 'erst muss --buyer aufgeloest sein');
        }

        $this->assertCallSetup($user);

        $funnel = $buyer === null ? null : $this->resolveFunnel($buyer);

        if ($buyer === null) {
            $this->pending('Funnel', 'erst muss der Kaeufer-Workspace stehen');
        }

        $priceCents = ($buyer === null || $funnel === null) ? null : $this->assertFunds($buyer, $funnel);

        if ($buyer === null || $funnel === null) {
            $this->pending('Freies Guthaben', 'ohne Kaeufer und Funnel gibt es keinen Kaufpreis');
        }

        $to = $this->resolveTargetNumber($user);
        $zip = trim((string) $this->option('zip'));

        if ($this->failed || $user === null || $buyer === null || $funnel === null || $priceCents === null || $to === null) {
            return null;
        }

        return [
            'buyer' => $buyer,
            'user' => $user,
            'funnel' => $funnel,
            'to' => $to,
            'zip' => $zip === '' ? '20095' : $zip,
            'price_cents' => $priceCents,
        ];
    }

    /**
     * Legt Lead, Antworten und Kauf an -- in der Reihenfolge der Vorgabe.
     *
     * @param  array{buyer: Tenant, user: User, funnel: Funnel, to: string, zip: string, price_cents: int}  $plan
     */
    private function write(array $plan): int
    {
        $lead = $this->createLead($plan);

        // Nicht ueber LeadScreeningService::screen(): Die Dublettenregel wuerde
        // den zweiten Uebungs-Lead derselben Nummer im selben Funnel als
        // `ungueltig` verwerfen, und der Betreiber stuende ohne Erklaerung vor
        // einem Lead, den er nicht kaufen kann.
        $this->leadStates->transition(
            $lead,
            LeadState::VERFUEGBAR,
            LeadTransitionReason::SCREENING_PASSED,
            null,
            ['probe' => true, 'ticket' => 89],
        );

        try {
            $purchase = $this->purchases->handle($plan['buyer'], $lead, $plan['user']);
        } catch (Throwable $exception) {
            $this->error('Der Kauf ist gescheitert: '.$exception->getMessage());
            $this->line('Der Lead #'.$lead->getKey().' steht jetzt in "'.$lead->fresh()?->lead_state->value
                .'". Mit --teardown zurueckbauen, sobald die Ursache behoben ist.');

            return self::FAILURE;
        }

        $purchase->update(['buyer_notes' => self::BUYER_NOTES]);

        $this->renderProbe($lead->refresh(), $purchase->refresh(), 'Uebungs-Lead angelegt.');
        $this->line('');
        $this->line('Danach: php artisan leads:call-probe --teardown');
        $this->line('');
        $this->warn('Ein Probeanruf ohne Abnehmen zaehlt voll: Die call_attempts-Zeile entsteht vor dem Waehlen, '
            .'ein nicht angenommener Versuch gilt als gueltiger Fehlversuch und sperrt den naechsten fuer '
            .(int) config('lead_calls.retry_min_hours').' Stunden. Wer die Zeile sehen will, nimmt ab.');
        $this->warn('Vor dem Ende der Sitzung aufraeumen: Solange der Uebungs-Lead offen steht, laeuft seine Frist.');

        return self::SUCCESS;
    }

    /**
     * Der Lead entsteht in `neu` -- dem Zustand, in dem jeder Lead entsteht.
     * Fachliche Antworten bleiben leer, nur die Kontaktfelder werden gefuellt:
     * Ohne sie zeigt das Lead-Detail im Portal eine leere Antwortliste, und der
     * Betreiber prueft einen Screen, der kaputt aussieht.
     *
     * @param  array{buyer: Tenant, user: User, funnel: Funnel, to: string, zip: string, price_cents: int}  $plan
     */
    private function createLead(array $plan): Lead
    {
        $funnel = $plan['funnel'];

        return DB::transaction(function () use ($funnel, $plan): Lead {
            /** @var Lead $lead */
            $lead = Lead::query()->create([
                'tenant_id' => $funnel->tenant_id,
                'funnel_id' => $funnel->getKey(),
                'funnel_version_id' => $funnel->current_version_id,
                'score' => 0,
                'price_at_creation' => $funnel->effectiveLeadPrice(),
                'shared_price_at_creation' => $funnel->effectiveSharedPrice(),
                'phone_e164' => $plan['to'],
                'postal_code' => $plan['zip'],
                'utm_source' => self::UTM_SOURCE,
                'utm_campaign' => self::UTM_CAMPAIGN,
            ]);

            foreach ([
                FunnelFieldKey::NAME->value => self::ANSWER_NAME,
                FunnelFieldKey::TELEFON->value => $plan['to'],
                FunnelFieldKey::PLZ->value => $plan['zip'],
            ] as $fieldKey => $value) {
                LeadAnswer::query()->create([
                    'lead_id' => $lead->getKey(),
                    'field_key' => $fieldKey,
                    'value' => $value,
                ]);
            }

            // lead_state steht in $guarded und kommt aus dem Standardwert der
            // Spalte (`neu`) -- ohne Nachladen traege die Instanz hier null.
            $lead->refresh();

            return $lead;
        });
    }

    /**
     * Rueckbau ueber den Fachweg: Erreichbarkeit entscheiden, Zustand setzen,
     * auf den Zuhoerer warten. Gebucht wird hier nichts.
     */
    private function teardown(): int
    {
        $lead = $this->openProbeLead();

        if ($lead === null) {
            $this->info('Kein Uebungs-Lead offen -- es gibt nichts zurueckzubauen.');

            return self::SUCCESS;
        }

        $purchase = $this->purchaseOf($lead);
        $admin = $this->resolveAdmin();

        if ($admin === null) {
            $this->error('Kein Plattform-Admin (users.is_admin) vorhanden -- ManualLeadStateService verlangt ihn fuer den Zwangswechsel mit Pflichtbegruendung.');

            return self::FAILURE;
        }

        if ($this->isDryRun()) {
            $this->renderProbe($lead, $purchase, 'Trockenlauf -- es wird nichts geschrieben.');
            $this->line('');
            $this->line('Zurueckgebaut wuerde: Erreichbarkeit '.LeadResolutionReason::THREE_ATTEMPTS->value
                .' (loest ueber SettleLeadPurchase die Reservierung auf), danach Zustand '
                .LeadState::UNERREICHBAR->value.' von Hand durch '.$admin->email.'.');

            return self::SUCCESS;
        }

        // Erst entscheiden, dann den Zustand setzen: Aus einem Endzustand
        // entscheidet LeadResolver::resolve() nicht mehr (Ticket #92), und ohne
        // die Entscheidung bliebe die Reservierung stehen.
        $resolved = $this->resolver->resolve($lead, LeadResolutionReason::THREE_ATTEMPTS);

        $this->line($resolved
            ? 'Erreichbarkeit entschieden: '.LeadResolutionReason::THREE_ATTEMPTS->value.' -> '.$lead->contact_status->value
            : 'Erreichbarkeit war bereits entschieden ('.$lead->contact_status->value.') -- nichts geaendert.');

        if ($lead->lead_state->isFinal()) {
            $this->line('Zustand steht schon auf "'.$lead->lead_state->value.'" -- kein Zwangswechsel noetig.');
        } else {
            $this->manualStates->force($lead, LeadState::UNERREICHBAR, self::JUSTIFICATION, $admin);
            $this->line('Zustand gesetzt: '.LeadState::UNERREICHBAR->value.' mit Pflichtbegruendung im lead_state_log.');
        }

        return $this->awaitRelease($purchase);
    }

    /**
     * SettleLeadPurchase ist ShouldQueue -- der Rueckbau ist erst fertig, wenn
     * der Job durch ist. Bleibt der Stand aus, ist der Worker der Verdaechtige.
     */
    private function awaitRelease(?LeadPurchase $purchase): int
    {
        if ($purchase === null) {
            $this->line('Kein Kaufbeleg zum Uebungs-Lead -- es war nichts reserviert.');

            return self::SUCCESS;
        }

        for ($second = 0; $second < self::RELEASE_WAIT_SECONDS; $second++) {
            $purchase->refresh();

            if ($purchase->status === PurchaseStatus::RELEASED) {
                $this->info('Reservierung aufgeloest. Kaufbeleg #'.$purchase->getKey().' steht auf "'
                    .$purchase->status->value.'", '.$this->euro((int) $purchase->price_cents).' sind wieder frei.');

                return self::SUCCESS;
            }

            sleep(1);
        }

        $this->error('Die Reservierung ist noch nicht aufgeloest. Worker pruefen'
            .' (systemctl status horizon-widileads), danach: php artisan wallet:verify');
        $this->line('Kaufbeleg #'.$purchase->getKey().' steht auf "'.$purchase->status->value.'".');

        return self::FAILURE;
    }

    /**
     * Der Uebungs-Lead, der noch nicht in einem Endzustand steht. Gesucht wird
     * ueber die Kennzeichnung und nicht ueber die Nummer: Die Nummer kann sich
     * zwischen zwei Sitzungen aendern, die Kennzeichnung nicht.
     */
    private function openProbeLead(): ?Lead
    {
        /** @var Lead|null $lead */
        $lead = Lead::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->where('utm_source', self::UTM_SOURCE)
            ->whereNotIn('lead_state', LeadState::finalStates())
            ->latest('id')
            ->first();

        return $lead;
    }

    private function purchaseOf(Lead $lead): ?LeadPurchase
    {
        /** @var LeadPurchase|null $purchase */
        $purchase = LeadPurchase::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->where('lead_id', $lead->getKey())
            ->latest('id')
            ->first();

        return $purchase;
    }

    /**
     * Der Block, den die Sitzung #86 traegt.
     */
    private function renderProbe(Lead $lead, ?LeadPurchase $purchase, string $headline): void
    {
        $buyer = $purchase?->buyer;
        $user = $purchase?->purchasedBy;

        $this->info($headline);
        $this->line(sprintf('  %-16s#%d  (%s, %s)', 'Lead', (int) $lead->getKey(), $lead->lead_state->value, $lead->contact_status->value));
        $this->line($purchase === null
            ? sprintf('  %-16s--  kein Kaufbeleg', 'Kaufbeleg')
            : sprintf(
                '  %-16s#%d  %s, %s geblockt',
                'Kaufbeleg',
                (int) $purchase->getKey(),
                $purchase->status->value,
                $this->euro((int) $purchase->price_cents + (int) $purchase->surcharge_cents),
            ));
        $this->line(sprintf('  %-16s%s   -- dieses Telefon klingelt', 'Zielnummer', (string) $lead->phone_e164));
        $this->line(sprintf(
            '  %-16s%s  (Workspace %s)',
            'Kaeufer',
            $user === null ? '--' : $user->email,
            $buyer === null ? '--' : (string) $buyer->getKey(),
        ));

        if ($buyer !== null && $purchase !== null) {
            $this->line(sprintf('  %-16s%s', 'Anrufpanel', route('portal.leads.show', [
                'tenant' => $buyer->uuid,
                'purchase' => $purchase->getKey(),
            ])));
        }
    }

    private function resolveUser(): ?User
    {
        $email = trim((string) $this->option('buyer'));

        if ($email === '') {
            $this->assert('Kaeufer', false, 'Pflichtangabe --buyer=<E-Mail> fehlt -- der Benutzer ist der Anrufende und traegt den Kauf.', '');

            return null;
        }

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        $this->assert('Kaeufer', $user !== null, 'Kein Benutzer mit der E-Mail "'.$email.'".', $email);

        return $user;
    }

    /**
     * Der Kaeufer-Mandant des Benutzers. Steht er in mehreren, wird nicht
     * geraten: Der Kauf haengt am Mandanten, eine falsche Wahl waere still
     * falsch.
     */
    private function resolveBuyerTenant(User $user): ?Tenant
    {
        /** @var Collection<int, Tenant> $tenants */
        $tenants = $user->tenants()->where('type', TenantType::BUYER)->get();

        if ($tenants->isEmpty()) {
            $this->assert('Kaeufer-Workspace', false, 'Der Benutzer gehoert zu keinem Kaeufer-Mandanten.', '');

            return null;
        }

        if ($tenants->count() > 1) {
            $this->assert(
                'Kaeufer-Workspace',
                false,
                'Der Benutzer gehoert zu mehreren Kaeufer-Mandanten -- eindeutig ist der Kauf nur mit einem: '
                    .$tenants->map(fn (Tenant $tenant): string => $tenant->getKey().' ('.$tenant->name.')')->implode(', '),
                '',
            );

            return null;
        }

        /** @var Tenant $buyer */
        $buyer = $tenants->first();

        $approved = $this->tenantTypes->canAccessMarketplace($buyer);

        $this->assert(
            'Kaeufer-Workspace',
            $approved,
            'Workspace '.$buyer->getKey().' ("'.$buyer->name.'") ist nicht marktplatzfaehig -- erst die Kaeufer-Registrierung freischalten.',
            'Workspace '.$buyer->getKey().' ('.$buyer->name.')',
        );

        return $approved ? $buyer : null;
    }

    /**
     * Dieselben Bedingungen, die CallService::start() prueft -- damit der
     * Betreiber nicht erst am Anruf-Knopf merkt, dass etwas fehlt.
     *
     * Die Telefonsperre und From==To (#93) gehoeren dazu, obwohl CallService
     * sie nicht selbst prueft: Die Sperre steht als Middleware vor dem
     * Anrufpanel, und From==To weist Twilio beim Waehlen ab -- beide halten den
     * Probeanruf auf, nachdem der Uebungs-Lead schon offen steht.
     */
    private function assertCallSetup(?User $user): void
    {
        $platformNumber = $this->platformNumber();

        $this->assert(
            'Portal-Nummer',
            $platformNumber !== '',
            'twilio.from_number ist leer -- ohne Portal-Nummer beginnt kein Anruf. Pruefen mit: php artisan leads:check-call-setup --production',
            $platformNumber.' erscheint beim Lead als Rufnummernanzeige',
        );

        if ($user === null) {
            $this->pending('Telefon des Anrufenden', 'erst muss --buyer aufgeloest sein');
            $this->pending('Rufnummernanzeige', 'erst muss --buyer aufgeloest sein');
            $this->pending('Absender ungleich Rufnummernanzeige', 'erst muss --buyer aufgeloest sein');

            return;
        }

        // Die bestaetigte Caller-Id allein genuegt nicht: EnsurePhoneVerified
        // (#3) haengt an users.phone_number_verified_at und sperrt jeden
        // eingeloggten Screen, also auch das Anrufpanel.
        $this->assert(
            'Telefon des Anrufenden',
            $user->phone_number_verified_at !== null,
            'Fuer "'.$user->email.'" ist die Rufnummernbestaetigung offen -- im Portal nachholen, sonst sperrt EnsurePhoneVerified das Anrufpanel.',
            'bestaetigt am '.$user->phone_number_verified_at?->format('d.m.Y H:i'),
        );

        $callerId = $this->callerIds->forUser($user);

        $this->assert(
            'Rufnummernanzeige',
            $callerId !== null && $callerId->isUsableAsCallerId(),
            $callerId === null
                ? 'Fuer "'.$user->email.'" ist keine Rufnummer bestaetigt -- im Portal unter "Eigene Rufnummer". Pruefen mit: php artisan leads:check-call-setup --user='.$user->email
                : 'Die Rufnummer '.$callerId->phone_number.' hat den Stand "'.$callerId->status->value.'" -- nutzbar ist nur eine bestaetigte. Pruefen mit: php artisan leads:check-call-setup --user='.$user->email,
            (string) $callerId?->phone_number.' bestaetigt',
        );

        if ($callerId === null) {
            $this->pending('Absender ungleich Rufnummernanzeige', 'erst muss eine Rufnummernanzeige stehen');

            return;
        }

        // CallService::start() waehlt die Caller-Id des Mitarbeiters von der
        // Portal-Nummer aus (CallService.php:144). Sind beide gleich, weist
        // Twilio den Anruf ab und der Versuch landet in FAILED_IGNORED.
        $this->assert(
            'Absender ungleich Rufnummernanzeige',
            $platformNumber === '' || $callerId->phone_number !== $platformNumber,
            'Portal-Nummer und Rufnummernanzeige sind beide '.$platformNumber
                .' -- Twilio laesst From gleich To nicht zu. Einen Nutzer mit anderer Rufnummernanzeige waehlen oder die gekaufte Nummer als TWILIO_FROM setzen.',
            $platformNumber.' waehlt '.$callerId->phone_number,
        );
    }

    /**
     * Freies Guthaben gegen den Kaufpreis. Bei Pay as you go zaehlt der
     * Gesamtpreis samt Aufschlag -- das ist der Betrag, den dieser Kaeufer
     * traegt.
     */
    private function assertFunds(Tenant $buyer, Funnel $funnel): ?int
    {
        $seller = $funnel->tenant;

        if (! $seller instanceof Tenant) {
            $this->assert('Freies Guthaben', false, 'Dem Funnel haengt kein Mandant an -- ohne Verkaeufer gibt es keinen Preis.', '');

            return null;
        }

        // Der Preis haengt am Verkaeufer, nicht am Lead -- fuer die Rechnung
        // genuegt deshalb eine unverwahrte Instanz mit gesetzter Beziehung.
        $priceCents = $this->purchases->currentPriceCentsOf(
            (new Lead)->setRelation('tenant', $seller),
            $buyer,
        );

        $wallet = Wallet::forBuyer($buyer);
        $availableCents = (int) $wallet->available_cents;

        $this->assert(
            'Freies Guthaben',
            $availableCents >= $priceCents,
            'Der Uebungskauf kostet '.$this->euro($priceCents).', frei sind '.$this->euro($availableCents)
                .' -- erst aufladen, der Kauf laeuft zum regulaeren Preis des Verkaeufers.',
            $this->euro($availableCents).' frei, Kaufpreis '.$this->euro($priceCents),
        );

        return $availableCents >= $priceCents ? $priceCents : null;
    }

    /**
     * Der Funnel: vorhanden, Einzelverkauf, nicht dem Kaeufer gehoerend.
     *
     * Einzelverkauf ist Bedingung und keine Vorliebe: Bleibt der Lead nach dem
     * Kauf im Angebot (effectiveMaxBuyers() > 1), steht die Nummer des
     * Betreibers fremden Kaeufern im Marktplatz.
     */
    private function resolveFunnel(Tenant $buyer): ?Funnel
    {
        $wanted = trim((string) $this->option('funnel'));

        $query = Funnel::query()->withoutGlobalScopes(TenantScopes::names());

        if ($wanted !== '') {
            /** @var Funnel|null $funnel */
            $funnel = $query
                ->where(fn ($inner) => $inner
                    ->where('id', is_numeric($wanted) ? (int) $wanted : 0)
                    ->orWhere('slug', $wanted)
                    ->orWhere('public_token', $wanted)
                    ->orWhere('name', $wanted))
                ->first();

            if ($funnel === null) {
                $this->assert('Funnel', false, 'Kein Funnel "'.$wanted.'" (Id, Slug, public_token oder Name).', '');

                return null;
            }

            return $this->assertFunnelFits($funnel, $buyer) ? $funnel : null;
        }

        /** @var Funnel|null $funnel */
        $funnel = $query
            ->where('sale_mode', SaleMode::EXCLUSIVE->value)
            ->where('tenant_id', '!=', $buyer->getKey())
            ->oldest('id')
            ->first();

        if ($funnel === null) {
            $this->assert(
                'Funnel',
                false,
                'Kein Funnel mit Einzelverkauf, der nicht dem Kaeufer gehoert -- mit --funnel= einen waehlen.',
                '',
            );

            return null;
        }

        return $this->assertFunnelFits($funnel, $buyer) ? $funnel : null;
    }

    private function assertFunnelFits(Funnel $funnel, Tenant $buyer): bool
    {
        if ((int) $funnel->tenant_id === (int) $buyer->getKey()) {
            // PurchaseLead::guardLead() weist den Kauf des eigenen Leads ab;
            // Verkaeufer und Kaeufer muessen verschiedene Mandanten sein.
            $this->assert('Funnel', false, 'Funnel #'.$funnel->getKey().' gehoert dem Kaeufer selbst -- der Kauf am eigenen Lead ist gesperrt.', '');

            return false;
        }

        $maxBuyers = $funnel->effectiveMaxBuyers();

        if ($maxBuyers > 1) {
            $this->assert(
                'Funnel',
                false,
                'Funnel #'.$funnel->getKey().' verkauft mehrfach ('.$maxBuyers.' Kaeufer) -- der Lead bliebe nach dem Kauf im Marktplatz. Mit --funnel= einen Funnel mit Einzelverkauf waehlen.',
                '',
            );

            return false;
        }

        $this->assert('Funnel', true, '', '#'.$funnel->getKey().' "'.$funnel->name.'", Einzelverkauf, Mandant '.$funnel->tenant_id);

        return true;
    }

    /**
     * Die Zielnummer: lesbar, waehlbar, und nicht die Rufnummernanzeige des
     * Benutzers -- sonst ruft das Portal dasselbe Telefon an, das als Anzeige
     * dient, und der Betreiber hoert sich selbst.
     */
    private function resolveTargetNumber(?User $user): ?string
    {
        $input = trim((string) $this->option('to'));

        if ($input === '') {
            $this->assert('Zielnummer', false, 'Pflichtangabe --to=<E.164> fehlt -- das ist das Telefon, das klingeln soll.', '');

            return null;
        }

        try {
            $normalized = $this->callerIds->normalize($input);
        } catch (InvalidArgumentException $exception) {
            $this->assert('Zielnummer', false, '"'.$input.'" ist keine waehlbare Rufnummer ('.$exception->getMessage().').', '');

            return null;
        }

        $callerId = $user === null ? null : $this->callerIds->forUser($user);

        if ($callerId !== null && $callerId->phone_number === $normalized) {
            $this->assert(
                'Zielnummer',
                false,
                $normalized.' ist die Rufnummernanzeige des Benutzers -- Twilio verbindet, aber der Betreiber hoert sich selbst. Eine zweite Nummer benutzen.',
                '',
            );

            return null;
        }

        $this->assert('Zielnummer', true, '', $normalized);

        return $normalized;
    }

    /**
     * Der Plattform-Admin, der den Zwangswechsel verantwortet: Gate
     * `leads.force-state` verlangt `is_admin`. Ist der kaufende Benutzer selbst
     * Admin, ist er die naheliegende Wahl -- er fuehrt die Sitzung.
     */
    private function resolveAdmin(): ?User
    {
        $email = trim((string) $this->option('buyer'));

        if ($email !== '') {
            /** @var User|null $buyerUser */
            $buyerUser = User::query()->where('email', $email)->where('is_admin', true)->first();

            if ($buyerUser !== null) {
                return $buyerUser;
            }
        }

        /** @var User|null $admin */
        $admin = User::query()->where('is_admin', true)->oldest('id')->first();

        return $admin;
    }

    /**
     * Kanonisch config('twilio.from_number'), mit dem alten SaaSykit-Ort als
     * Rueckfall -- derselbe Rueckfall wie in CallService::platformNumber().
     */
    private function platformNumber(): string
    {
        $number = (string) config('twilio.from_number');

        return $number !== '' ? $number : (string) config('services.twilio.from');
    }

    private function isDryRun(): bool
    {
        return (bool) $this->option('dry-run');
    }

    private function assert(string $label, bool $passed, string $problem, string $detail): void
    {
        if ($passed) {
            $this->rows[] = [$label, 'OK', $detail];

            return;
        }

        $this->failed = true;
        $this->rows[] = [$label, 'FEHLER', $problem];
    }

    /**
     * Eine Pruefung, die nicht laufen kann, weil eine Angabe davor fehlt. Sie
     * steht trotzdem in der Liste: Der Trockenlauf soll jede Pruefung mit ihrem
     * Ergebnis zeigen, auch das Ergebnis "nicht geprueft".
     */
    private function pending(string $label, string $because): void
    {
        $this->rows[] = [$label, 'OFFEN', 'Nicht geprueft -- '.$because.'.'];
    }

    private function euro(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' '.(string) config('wallet.currency');
    }
}
