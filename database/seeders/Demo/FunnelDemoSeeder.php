<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Actions\PublishFunnel;
use App\Actions\PurchaseLead;
use App\Constants\FunnelFieldKey;
use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Constants\TenancyPermissionConstants;
use App\Constants\TenantType;
use App\Models\BuyerProfile;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BuyerOnboardingService;
use App\Services\CreditLedgerService;
use App\Services\FunnelTemplateImporter;
use App\Services\LeadStateService;
use App\Services\TenantCreationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Demo-Umgebung des Funnel Builders (FB-045).
 *
 * Legt einen vollstaendigen, in sich stimmigen Datenbestand an: einen
 * Betreiber mit veroeffentlichtem Pfotencheck-Funnel, zwei freigeschaltete
 * Kaeufer mit unterschiedlichen Kaufkriterien, 200 Leads ueber alle acht
 * Zustaende, Guthaben, echte Kaeufe und eine anerkannte Reklamation.
 *
 * Aufruf:
 *
 *     php artisan db:seed --class="Database\Seeders\Demo\FunnelDemoSeeder"
 *
 * Bewusst **nicht** Teil des DatabaseSeeder: Der Bestand ist gross, dauert
 * spuerbar und hat in einer frischen Installation nichts verloren.
 *
 * Zwei Dinge, die den Aufbau erklaeren:
 *
 * - **Jeder Zustandswechsel laeuft ueber LeadStateService::transition().** Ein
 *   direkt gesetztes `lead_state` waere schneller, wuerde aber das
 *   Zustandsprotokoll leer lassen -- und genau das Protokoll ist es, was diese
 *   Demo zeigen soll. Die Architekturregel aus FB-042 schlaegt darauf ohnehin an.
 * - **Kaeufe laufen ueber PurchaseLead.** Damit stimmen Kaufbeleg, Guthabenkonto
 *   und Zustand zusammen, statt drei Mal von Hand nachgebaut zu werden.
 */
class FunnelDemoSeeder extends Seeder
{
    private const OPERATOR_EMAIL = 'betreiber@demo.test';

    private const BUYER_NORTH_EMAIL = 'kaeufer-nord@demo.test';

    private const BUYER_SOUTH_EMAIL = 'kaeufer-sued@demo.test';

    private const PASSWORD = 'demo1234';

    private const LEAD_COUNT = 200;

    /**
     * Zielverteilung der Leads. Die Summe ist LEAD_COUNT.
     *
     * Die Reihenfolge entspricht dem Lebenszyklus, damit die Verteilung auf
     * einen Blick plausibel ist: Der Marktplatz traegt den groessten Teil,
     * Endzustaende sind die Ausnahme.
     */
    private const DISTRIBUTION = [
        'neu' => 20,
        'verfuegbar' => 90,
        'reserviert' => 15,
        'verkauft' => 30,
        'erreicht' => 20,
        'unerreichbar' => 10,
        'ungueltig' => 10,
        'abgelaufen' => 5,
    ];

    public function __construct(
        private readonly TenantCreationService $tenantCreation,
        private readonly BuyerOnboardingService $buyerOnboarding,
        private readonly FunnelTemplateImporter $templateImporter,
        private readonly PublishFunnel $publishFunnel,
        private readonly PurchaseLead $purchaseLead,
        private readonly LeadStateService $states,
        private readonly CreditLedgerService $credits,
    ) {}

    public function run(): void
    {
        $this->callOnce([DatabaseSeeder::class]);

        if (User::query()->where('email', self::OPERATOR_EMAIL)->exists()) {
            $this->command?->warn(
                'Die Demo-Umgebung existiert bereits. Zum Neuaufbau zuerst die Datenbank zuruecksetzen.'
            );

            return;
        }

        $admin = $this->platformAdmin();

        [$operator, $operatorUser] = $this->createOperator();
        $funnel = $this->publishPfotencheck($operator, $operatorUser);

        $north = $this->createBuyer(
            $admin,
            self::BUYER_NORTH_EMAIL,
            'Tierschutz Assekuranz Nord',
            'Britta Ahrens',
            // Nur Hunde, nur der Norden, und erst ab erhoehtem Risiko: der
            // waehlerische Kaeufer, an dem sich die Kaufkriterien zeigen.
            [
                'funnel_ids' => [$funnel->getKey()],
                'postal_prefixes' => ['20', '21', '22', '23', '24'],
                'answer_filters' => ['tierart' => ['hund']],
                'min_score' => 4,
                'auto_buy' => false,
            ],
        );

        $south = $this->createBuyer(
            $admin,
            self::BUYER_SOUTH_EMAIL,
            'Pfotenpolice Sued GmbH',
            'Marek Wendt',
            // Nimmt alles im Sueden, kauft automatisch, deckelt aber den Tag.
            [
                'funnel_ids' => [],
                'postal_prefixes' => ['80', '81', '82', '90', '91'],
                'answer_filters' => [],
                'min_score' => 0,
                'daily_limit' => 25,
                'auto_buy' => true,
            ],
        );

        // Reichlich Deckung: 60 Leads werden gekauft, der Rest bleibt als
        // sichtbares Guthaben im Konto stehen.
        $this->credits->purchase($north, 50, 49_900);
        $this->credits->purchase($south, 50, 49_900);
        $this->credits->adjust($south, 10, null, null);

        $leads = $this->createLeads($funnel, $operator);

        $this->distribute($leads, $operatorUser, $north, $south);

        $this->command?->info('Demo-Umgebung angelegt.');
        $this->command?->line('  Betreiber:  '.self::OPERATOR_EMAIL.' / '.self::PASSWORD);
        $this->command?->line('  Kaeufer 1:  '.self::BUYER_NORTH_EMAIL.' / '.self::PASSWORD);
        $this->command?->line('  Kaeufer 2:  '.self::BUYER_SOUTH_EMAIL.' / '.self::PASSWORD);
        $this->command?->line('  Funnel:     '.$funnel->name.' ('.self::LEAD_COUNT.' Leads)');
    }

    private function platformAdmin(): User
    {
        $admin = User::query()->where('is_admin', true)->first();

        if ($admin instanceof User) {
            return $admin;
        }

        $admin = User::query()->create([
            'name' => 'Demo-Plattformadmin',
            'email' => 'plattform@demo.test',
            'password' => bcrypt(self::PASSWORD),
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);

        $admin->assignRole(TenancyPermissionConstants::ROLE_ADMIN);

        return $admin;
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function createOperator(): array
    {
        $user = User::query()->create([
            'name' => 'Nina Berg',
            'email' => self::OPERATOR_EMAIL,
            'password' => bcrypt(self::PASSWORD),
            'email_verified_at' => now(),
        ]);

        $user->assignRole(TenancyPermissionConstants::ROLE_USER);

        $tenant = $this->tenantCreation->createTenant($user, 'Pfotencheck Demo', TenantType::OPERATOR);

        return [$tenant, $user];
    }

    private function publishPfotencheck(Tenant $operator, User $operatorUser): Funnel
    {
        $funnel = $this->templateImporter->import('pfotencheck', $operator);

        $this->publishFunnel->handle($funnel, $operatorUser);

        return $funnel->refresh();
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function createBuyer(
        User $admin,
        string $email,
        string $company,
        string $contactName,
        array $profile,
    ): Tenant {
        $user = User::query()->create([
            'name' => $contactName,
            'email' => $email,
            'password' => bcrypt(self::PASSWORD),
            'email_verified_at' => now(),
        ]);

        $user->assignRole(TenancyPermissionConstants::ROLE_USER);

        $registration = $this->buyerOnboarding->register($user, [
            'company_name' => $company,
            'contact_name' => $contactName,
            'contact_email' => $email,
            'contact_phone' => '+4930'.random_int(1000000, 9999999),
            'vat_id' => 'DE'.random_int(100000000, 999999999),
        ]);

        $this->buyerOnboarding->approve($registration, $admin);

        $tenant = $registration->refresh()->tenant;

        BuyerProfile::query()->create(['tenant_id' => $tenant->getKey()] + $profile);

        return $tenant;
    }

    /**
     * Legt alle Leads im Eingangszustand an. Der Zustand kommt erst danach,
     * ueber den Zustandsdienst.
     *
     * @return Collection<int, Lead>
     */
    private function createLeads(Funnel $funnel, Tenant $operator): Collection
    {
        $version = $funnel->currentVersion ?? $funnel->versions()->latest('id')->first();

        $leads = collect();

        for ($index = 1; $index <= self::LEAD_COUNT; $index++) {
            $answers = $this->answersFor($index);

            $lead = Lead::query()->create([
                'tenant_id' => $operator->getKey(),
                'funnel_id' => $funnel->getKey(),
                'funnel_version_id' => $version?->getKey(),
                'score' => $this->scoreFor($answers),
                'result_key' => null,
                'price_at_creation' => config('funnel.lead.default_price'),
                'phone_e164' => $answers[FunnelFieldKey::TELEFON->value],
                'email_normalized' => $answers[FunnelFieldKey::EMAIL->value],
                'postal_code' => $answers[FunnelFieldKey::PLZ->value],
                'utm_source' => ['google', 'meta', 'newsletter', 'direkt'][$index % 4],
                'utm_medium' => 'cpc',
                'utm_campaign' => 'pfotencheck-demo',
                // Verteilt den Bestand ueber die letzten Wochen, damit Listen,
                // Sortierung und Aufbewahrungsfristen etwas zu zeigen haben.
                'created_at' => now()->subDays(intdiv($index, 7))->subHours($index % 24),
            ]);

            foreach ($answers as $fieldKey => $value) {
                LeadAnswer::query()->create([
                    'lead_id' => $lead->getKey(),
                    'field_key' => $fieldKey,
                    'value' => $value,
                ]);
            }

            // Ohne das Neuladen bliebe lead_state null: Die Spalte steht in
            // $guarded, ihren Eingangswert setzt der Datenbank-Standard - und
            // den kennt das eben angelegte Modell noch nicht.
            $leads->push($lead->refresh());
        }

        return $leads;
    }

    /**
     * Antworten eines Leads. Bewusst durchgerechnet statt zufaellig: Ein
     * Demo-Bestand, der sich bei jedem Lauf anders verhaelt, taugt nicht zum
     * Vorfuehren.
     *
     * @return array<string, string>
     */
    private function answersFor(int $index): array
    {
        $tierarten = ['hund', 'hund', 'katze', 'sonstige'];
        $alter = ['unter_1', '1_bis_3', '4_bis_7', 'ab_8'];
        $groessen = ['klein', 'mittel', 'gross', 'veranlagung'];
        $vorerkrankungen = ['keine', 'haut_allergie', 'zaehne', 'gelenke', 'herz_niere'];
        $verhalten = ['ruhig_drinnen', 'normal', 'sehr_aktiv'];
        $schutz = ['vollschutz', 'op_schutz', 'keine'];

        // Nord (2x), Sued (8x/9x) und ein Rest, der zu keinem Kaeufer passt --
        // sonst waere im Marktplatz nie zu sehen, dass Kriterien greifen.
        $plzPraefixe = ['20', '22', '24', '80', '81', '90', '91', '50', '60', '99'];

        $vornamen = ['Lena', 'Jonas', 'Mira', 'Tobias', 'Sarah', 'Kerem', 'Anke', 'Paul', 'Yasmin', 'Frank'];
        $nachnamen = ['Voss', 'Brandt', 'Kern', 'Hoffmann', 'Ludwig', 'Demir', 'Sauer', 'Roth', 'Aydin', 'Neu'];

        $vorname = $vornamen[$index % count($vornamen)];
        $nachname = $nachnamen[intdiv($index, 7) % count($nachnamen)];

        return [
            'tierart' => $tierarten[$index % count($tierarten)],
            'alter' => $alter[$index % count($alter)],
            'rasse_groesse' => $groessen[intdiv($index, 3) % count($groessen)],
            'vorerkrankungen' => $vorerkrankungen[$index % count($vorerkrankungen)],
            'verhalten' => $verhalten[$index % count($verhalten)],
            'versicherungsstatus' => $schutz[intdiv($index, 5) % count($schutz)],
            FunnelFieldKey::VORNAME->value => $vorname,
            FunnelFieldKey::NACHNAME->value => $nachname,
            FunnelFieldKey::EMAIL->value => sprintf('%s.%s%d@example.test', strtolower($vorname), strtolower($nachname), $index),
            FunnelFieldKey::TELEFON->value => '+4915'.str_pad((string) (2000000 + $index), 8, '0', STR_PAD_LEFT),
            FunnelFieldKey::PLZ->value => $plzPraefixe[$index % count($plzPraefixe)].str_pad((string) ($index % 1000), 3, '0', STR_PAD_LEFT),
            FunnelFieldKey::EINWILLIGUNG->value => 'ja',
        ];
    }

    /**
     * Punktzahl aus den Antwortoptionen der Vorlage. Die Werte stehen so in
     * database/templates/pfotencheck.json; sie hier zu wiederholen ist der
     * Preis dafuer, den Bestand ohne die Runtime aufbauen zu koennen.
     *
     * @param  array<string, string>  $answers
     */
    private function scoreFor(array $answers): int
    {
        $points = [
            'tierart' => ['hund' => 2, 'katze' => 1, 'sonstige' => 3],
            'alter' => ['unter_1' => 1, '1_bis_3' => 0, '4_bis_7' => 1, 'ab_8' => 3],
            'rasse_groesse' => ['klein' => 0, 'mittel' => 1, 'gross' => 2, 'veranlagung' => 3],
            'vorerkrankungen' => ['keine' => 0, 'haut_allergie' => 1, 'zaehne' => 1, 'gelenke' => 2, 'herz_niere' => 3],
            'verhalten' => ['ruhig_drinnen' => 0, 'normal' => 1, 'sehr_aktiv' => 2],
            'versicherungsstatus' => ['vollschutz' => 0, 'op_schutz' => 1, 'keine' => 2],
        ];

        $score = 0;

        foreach ($points as $fieldKey => $scale) {
            $score += $scale[$answers[$fieldKey] ?? ''] ?? 0;
        }

        return $score;
    }

    /**
     * Bringt die Leads in die Zielzustaende -- ausschliesslich ueber den
     * Zustandsdienst und, wo Geld im Spiel ist, ueber den Kaufvorgang.
     *
     * @param  Collection<int, Lead>  $leads
     */
    private function distribute(Collection $leads, User $operatorUser, Tenant $north, Tenant $south): void
    {
        $queue = $leads->values();
        $offset = 0;

        $take = function (int $count) use ($queue, &$offset): Collection {
            $slice = $queue->slice($offset, $count);
            $offset += $count;

            return $slice;
        };

        // `neu` bleibt, wie es ist -- der Eingangszustand entsteht beim Anlegen.
        $take(self::DISTRIBUTION['neu']);

        foreach ($take(self::DISTRIBUTION['verfuegbar']) as $lead) {
            $this->release($lead, $operatorUser);
        }

        foreach ($take(self::DISTRIBUTION['reserviert']) as $index => $lead) {
            $this->release($lead, $operatorUser);

            $buyer = $index % 2 === 0 ? $north : $south;

            $this->states->transition(
                $lead,
                LeadState::RESERVIERT,
                LeadTransitionReason::RESERVED_BY_BUYER,
                $operatorUser,
                ['buyer_tenant_id' => $buyer->getKey()],
            );

            $lead->update([
                'reserved_by' => $buyer->getKey(),
                'reserved_until' => now()->addMinutes((int) config('funnel.lead.reservation_ttl')),
            ]);
        }

        $sold = $this->buy($take(self::DISTRIBUTION['verkauft']), $operatorUser, $north, $south);

        foreach ($this->buy($take(self::DISTRIBUTION['erreicht']), $operatorUser, $north, $south) as $lead) {
            $this->states->transition($lead, LeadState::ERREICHT, LeadTransitionReason::CALL_ANSWERED, $operatorUser);
        }

        $unreached = $this->buy($take(self::DISTRIBUTION['unerreichbar']), $operatorUser, $north, $south);

        foreach ($unreached as $lead) {
            $this->states->transition(
                $lead,
                LeadState::UNERREICHBAR,
                LeadTransitionReason::CALL_ATTEMPTS_EXHAUSTED,
                $operatorUser,
            );
        }

        $reasons = [
            LeadTransitionReason::SPAM,
            LeadTransitionReason::DUPLICATE,
            LeadTransitionReason::IMPLAUSIBLE_CONTACT,
        ];

        foreach ($take(self::DISTRIBUTION['ungueltig']) as $index => $lead) {
            $this->states->transition(
                $lead,
                LeadState::UNGUELTIG,
                $reasons[$index % count($reasons)],
                $operatorUser,
            );
        }

        foreach ($take(self::DISTRIBUTION['abgelaufen']) as $lead) {
            $this->release($lead, $operatorUser);

            $this->states->transition(
                $lead,
                LeadState::ABGELAUFEN,
                LeadTransitionReason::RETENTION_ELAPSED,
                $operatorUser,
            );
        }

        $this->fileComplaint($sold->first(), $operatorUser);
    }

    /**
     * Der Weg vom Eingang in den Marktplatz.
     */
    private function release(Lead $lead, User $actor): void
    {
        $this->states->transition($lead, LeadState::VERFUEGBAR, LeadTransitionReason::SCREENING_PASSED, $actor);
    }

    /**
     * Kauft die Leads abwechselnd fuer beide Kaeufer -- ueber den echten
     * Kaufvorgang, damit Beleg, Guthaben und Zustand zusammenpassen.
     *
     * @param  Collection<int, Lead>  $leads
     * @return Collection<int, Lead>
     */
    private function buy(Collection $leads, User $operatorUser, Tenant $north, Tenant $south): Collection
    {
        return $leads->values()->map(function (Lead $lead, int $index) use ($operatorUser, $north, $south): Lead {
            $this->release($lead, $operatorUser);

            $this->purchaseLead->handle($index % 2 === 0 ? $north : $south, $lead, $operatorUser);

            return $lead->refresh();
        });
    }

    /**
     * Eine anerkannte Reklamation: Der Lead wird ungueltig, der Kaeufer bekommt
     * sein Guthaben zurueck.
     *
     * FB-058 baut daraus spaeter einen eigenen Vorgang mit Frist und
     * Entscheidung. Hier stehen nur die beiden Buchungen, die am Ende ohnehin
     * dabei herauskommen -- mehr wuerde dem Ticket vorgreifen.
     */
    private function fileComplaint(?Lead $lead, User $operatorUser): void
    {
        if (! $lead instanceof Lead) {
            return;
        }

        // Lead::purchases() gibt es nicht, und eine Relation nachzuruesten
        // waere eine Aenderung an fremdem Ticketumfang - also von hier aus
        // gesucht.
        $purchase = LeadPurchase::query()->where('lead_id', $lead->getKey())->latest('id')->first();

        if ($purchase === null) {
            return;
        }

        $this->states->transition(
            $lead,
            LeadState::UNGUELTIG,
            LeadTransitionReason::COMPLAINT_APPROVED,
            $operatorUser,
            ['lead_purchase_id' => $purchase->getKey()],
        );

        $this->credits->refund($purchase->buyer, PurchaseLead::CREDITS_PER_LEAD, $purchase);
    }
}
