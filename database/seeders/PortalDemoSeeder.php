<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\PurchaseLead;
use App\Constants\BuyerRegion;
use App\Constants\LeadState;
use App\Constants\OnboardingStep;
use App\Constants\PhoneVerificationChannel;
use App\Constants\TenancyPermissionConstants;
use App\Constants\WalletTransactionType;
use App\Events\Phone\PhoneVerified;
use App\Events\Wallet\WalletToppedUp;
use App\Models\BuyerProfile;
use App\Models\CallerId;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\TenantOnboardingStep;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BuyerOnboardingService;
use App\Services\Onboarding\OnboardingService;
use App\Services\Wallet\WalletService;
use Database\Seeders\Demo\FunnelDemoSeeder;
use Illuminate\Database\Seeder;

/**
 * Prueffaelle fuer die Abnahme des Kaeufer-Onboardings (LP-ONBOARD-016).
 *
 * Legt drei Kaeufer-Workspaces an, die genau auf den drei Stufen stehen, an
 * denen der Abnahmeplan ansetzt -- damit niemand sich fuer jeden Prueffall
 * einen Kaeufer von Hand zusammenklickt und dabei bei Stufe 2 landet, obwohl
 * er Stufe 1 pruefen wollte:
 *
 * - **Stufe 1 (`qa-telefon@demo.test`)** -- freigeschalteter Kaeufer, Nummer
 *   **nicht** bestaetigt. Der Fall fuer die harte Sperre: jeder angemeldete
 *   Screen muss auf /onboarding/telefon fuehren, jede Livewire-Aktion mit 403
 *   antworten.
 * - **Stufe 2 (`qa-wizard@demo.test`)** -- Nummer bestaetigt, Wizard mitten
 *   drin: Branchen stehen, Region und Zahlung fehlen. Der Fall fuer
 *   Schritt-Leiste, Zurueck, Reload mitten im Wizard und "Spaeter einrichten".
 * - **Stufe 3 (`qa-checkliste@demo.test`)** -- Wizard abgeschlossen, Guthaben
 *   aufgeladen, ein Lead gekauft, **nicht** angerufen. Der Fall fuer
 *   Aktivierungskarte (Ring bei 2 von 5), Tour und Mailstrecke; der offene
 *   Haken ist `first_call`, und genau der ist der Aktivierungspunkt.
 *
 * Den Bestandskunden-Fall ("sieht nach `onboarding:backfill` weder Wizard noch
 * Karte") tragen die beiden Kaeufer des FunnelDemoSeeders: Sie haben Kaeufe
 * und Anrufe, aber keine Zeile in `tenant_onboarding_steps` -- also genau der
 * Zustand, den der Backfill vorfindet.
 *
 * Aufruf:
 *
 *     php artisan db:seed --class="Database\Seeders\PortalDemoSeeder"
 *
 * Setzt den FunnelDemoSeeder voraus und ruft ihn selbst auf, wenn er fehlt:
 * Ohne Betreiber, Funnel und verfuegbare Leads gibt es weder Branchenkacheln
 * mit Anfragezahlen noch einen Lead, den Stufe 3 kaufen koennte.
 *
 * Nicht Teil des DatabaseSeeder -- Prueffaelle gehoeren nicht in eine frische
 * Installation.
 */
class PortalDemoSeeder extends Seeder
{
    private const PASSWORD = 'demo1234';

    private const STAGE_PHONE_EMAIL = 'qa-telefon@demo.test';

    private const STAGE_WIZARD_EMAIL = 'qa-wizard@demo.test';

    private const STAGE_CHECKLIST_EMAIL = 'qa-checkliste@demo.test';

    /** Guthaben der Stufe 3 in Cent -- reicht fuer mehrere Kaeufe. */
    private const TOPUP_CENTS = 50_000;

    public function __construct(
        private readonly BuyerOnboardingService $buyerOnboarding,
        private readonly OnboardingService $onboarding,
        private readonly PurchaseLead $purchaseLead,
        private readonly WalletService $wallets,
    ) {}

    public function run(): void
    {
        if (User::query()->where('email', self::STAGE_PHONE_EMAIL)->exists()) {
            $this->command?->warn(
                'Die Prueffaelle existieren bereits. Zum Neuaufbau zuerst die Datenbank zuruecksetzen.'
            );

            // Trotzdem durchlassen: Der FunnelDemoSeeder traegt auf einem
            // bestehenden Bestand die fehlenden Aufladungs- und Anrufspuren der
            // beiden Bestandskaeufer nach (LP-ONBOARD-054). Legt nichts an.
            $this->callOnce([FunnelDemoSeeder::class]);

            return;
        }

        $this->callOnce([FunnelDemoSeeder::class]);

        $admin = $this->platformAdmin();
        $funnel = Funnel::query()->latest('id')->first();

        [$phoneStage, $phoneUser] = $this->createBuyer(
            $admin,
            self::STAGE_PHONE_EMAIL,
            'QA Stufe 1 -- Telefonsperre',
            'Rita Sperr',
        );

        [$wizardStage, $wizardUser] = $this->createBuyer(
            $admin,
            self::STAGE_WIZARD_EMAIL,
            'QA Stufe 2 -- Wizard offen',
            'Milan Halbfertig',
        );

        $this->verifyPhone($wizardStage, $wizardUser, '+4930555100002');

        // Nur der erste Pflichtschritt. Der Wizard fuehrt diesen Workspace
        // damit auf `region` -- der Schritt mit Karte, Pin und Umkreis.
        $this->onboarding->complete(
            $wizardStage,
            OnboardingStep::INDUSTRIES_SELECTED,
            $wizardUser,
            ['industries' => $funnel instanceof Funnel ? [$funnel->getKey()] : []],
        );

        if ($funnel instanceof Funnel) {
            $wizardStage->buyerProfile?->update(['funnel_ids' => [$funnel->getKey()]]);
        }

        [$checklistStage, $checklistUser] = $this->createBuyer(
            $admin,
            self::STAGE_CHECKLIST_EMAIL,
            'QA Stufe 3 -- Checkliste offen',
            'Bea Startklar',
        );

        $this->verifyPhone($checklistStage, $checklistUser, '+4930555100003');
        $this->completeWizard($checklistStage, $checklistUser, $funnel);
        $this->topUp($checklistStage);
        $this->buyOneLead($checklistStage, $checklistUser);

        // Die beiden abgebrochenen Stufen altern lassen: "Haengt gerade" im
        // Admin-Trichter (LP-ONBOARD-015) zeigt nur Workspaces, die laenger
        // als die Schwelle auf derselben Stufe stehen -- frisch geseedete
        // waeren Minuten alt und die Liste bliebe leer, obwohl genau diese
        // beiden Faelle hineingehoeren.
        $this->backdate($phoneStage, $phoneUser, 5);
        $this->backdate($wizardStage, $wizardUser, 3);

        $this->command?->info('Prueffaelle des Kaeufer-Onboardings angelegt (Passwort: '.self::PASSWORD.').');
        $this->command?->line('  Stufe 1 (Telefonsperre):   '.self::STAGE_PHONE_EMAIL.'  Workspace '.$phoneStage->uuid);
        $this->command?->line('  Stufe 2 (Wizard offen):    '.self::STAGE_WIZARD_EMAIL.'  Workspace '.$wizardStage->uuid);
        $this->command?->line('  Stufe 3 (Checkliste):      '.self::STAGE_CHECKLIST_EMAIL.'  Workspace '.$checklistStage->uuid);
        $this->command?->line('  Bestandskunden fuer onboarding:backfill: kaeufer-nord@demo.test, kaeufer-sued@demo.test');
    }

    private function platformAdmin(): User
    {
        $admin = User::query()->where('is_admin', true)->first();

        if ($admin instanceof User) {
            return $admin;
        }

        $admin = User::query()->create([
            'name' => 'QA-Plattformadmin',
            'email' => 'qa-admin@demo.test',
            'password' => bcrypt(self::PASSWORD),
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);

        $admin->assignRole(TenancyPermissionConstants::ROLE_ADMIN);

        return $admin;
    }

    /**
     * Ein freigeschalteter Kaeufer-Workspace ohne jeden Onboarding-Haken.
     *
     * Der Weg laeuft ueber BuyerOnboardingService::register()/approve() und
     * nicht ueber Factories: Nur so stimmen Registrierung, Rolle,
     * Mitgliedschaft und Kaeuferprofil mit dem Betrieb ueberein -- und nur ein
     * freigeschalteter Kaeufer kommt ueberhaupt bis zur Telefonsperre.
     *
     * @return array{0: Tenant, 1: User}
     */
    private function createBuyer(User $admin, string $email, string $company, string $contactName): array
    {
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

        if (! $tenant instanceof Tenant) {
            throw new \RuntimeException('Die Freischaltung hat keinen Workspace hinterlassen: '.$email);
        }

        BuyerProfile::query()->firstOrCreate(
            ['tenant_id' => $tenant->getKey()],
            ['funnel_ids' => [], 'region' => BuyerRegion::NATIONWIDE->value, 'auto_buy' => false],
        );

        return [$tenant, $user->refresh()];
    }

    /**
     * Bestaetigte Rufnummer -- derselbe Zustand, den ein erfolgreicher
     * Bestaetigungsanruf hinterlaesst (Ticket #97).
     *
     * Drei Dinge gehoeren dazu, und ein Prueffall ohne eines davon kommt bis
     * zum Anruf-Knopf und nicht weiter: der Zeitstempel am Benutzer (daran
     * haengt die Telefonsperre), die `caller_ids`-Zeile mit Validation Request
     * (daran haengt der Anruf selbst, sonst lehnt Twilio mit 21210 ab) und der
     * gespiegelte Haken im Workspace ueber das Ereignis `PhoneVerified`.
     *
     * Der Zuhoerer laeuft in der Queue, also die enge Klammer auf `sync`.
     */
    private function verifyPhone(Tenant $workspace, User $user, string $number): void
    {
        $user->forceFill([
            'phone_e164' => $number,
            'phone_number_verified_at' => now(),
            'phone_verification_channel' => PhoneVerificationChannel::CALL,
        ])->save();

        CallerId::factory()->verified()->create([
            'tenant_id' => $workspace->getKey(),
            'user_id' => $user->getKey(),
            'phone_number' => $number,
        ]);

        $this->synchronously(fn () => event(new PhoneVerified($user, PhoneVerificationChannel::CALL)));
    }

    /**
     * Alle drei Pflichtschritte -- damit faellt auch der Wizard-Abschluss und
     * mit ihm die geplante Mailstrecke.
     */
    private function completeWizard(Tenant $workspace, User $user, ?Funnel $funnel): void
    {
        $funnelIds = $funnel instanceof Funnel ? [$funnel->getKey()] : [];

        $this->onboarding->complete(
            $workspace,
            OnboardingStep::INDUSTRIES_SELECTED,
            $user,
            ['industries' => $funnelIds],
        );

        $this->onboarding->complete(
            $workspace,
            OnboardingStep::REGION_SELECTED,
            $user,
            ['region' => BuyerRegion::RADIUS->value, 'postal_code' => '20095', 'radius_km' => 50],
        );

        $this->onboarding->complete(
            $workspace,
            OnboardingStep::PAYMENT_SETUP,
            $user,
            ['mode' => 'prepaid', 'package' => 250],
        );

        $workspace->buyerProfile?->update([
            'funnel_ids' => $funnelIds,
            'region' => BuyerRegion::RADIUS->value,
            'postal_code' => '20095',
            'radius_km' => 50,
        ]);

        $workspace->refresh();
    }

    /**
     * Guthaben und der Haken `first_topup`.
     *
     * Im Betrieb entsteht beide Male dasselbe aus einer bezahlten Bestellung:
     * CreditWalletAfterPayment bucht und feuert WalletToppedUp. Hier gibt es
     * keine Bestellung, deshalb wird gebucht und das Ereignis von Hand
     * ausgeloest -- sonst blieben alle Haken der Checkliste leer und der
     * Prueffall waere keiner. MarkFirstTopup ist ShouldQueue, fuer die Dauer
     * des Ereignisses laeuft die Queue deshalb synchron (vgl.
     * FunnelDemoSeeder::settle()).
     */
    private function topUp(Tenant $buyer): void
    {
        $transaction = $this->wallets->post(
            wallet: Wallet::forBuyer($buyer),
            type: WalletTransactionType::TOPUP,
            amountCents: self::TOPUP_CENTS,
            description: __('marketplace.wallet.descriptions.topup', ['order' => 'QA']),
            reference: $buyer,
            idempotencyKey: WalletService::keyFor(WalletTransactionType::TOPUP, $buyer, 'portal-qa'),
            meta: ['source' => 'portal_demo_seeder'],
        );

        $this->synchronously(fn () => event(new WalletToppedUp($buyer, $transaction)));
    }

    /**
     * Ein gekaufter, nicht angerufener Lead -- der Haken `first_purchase`.
     *
     * Der Kauf laeuft ueber PurchaseLead, den einzigen Weg zu einem
     * LeadPurchase; MarkFirstPurchase haengt an LeadPurchased und ist
     * ShouldQueue, also wieder die enge Klammer auf `sync`. Angerufen wird
     * bewusst nicht: Der offene Haken `first_call` ist der Prueffall.
     *
     * Der Kaeufer selbst ist der Handelnde und nicht der Plattformadmin:
     * `lead_purchases.purchased_by_user_id` entscheidet in
     * LeadPurchasePolicy, und ein auf den Admin gebuchter Kauf waere fuer den
     * Kaeufer der Kauf eines Kollegen -- das Lead-Detail antwortete mit 403,
     * ohne dass etwas kaputt waere.
     */
    private function buyOneLead(Tenant $buyer, User $actor): void
    {
        $lead = Lead::query()
            ->where('lead_state', LeadState::VERFUEGBAR)
            ->whereNull('reserved_by')
            ->oldest('id')
            ->first();

        if (! $lead instanceof Lead) {
            $this->command?->warn('Kein verfuegbarer Lead fuer Stufe 3 -- `first_purchase` bleibt offen.');

            return;
        }

        $this->synchronously(fn () => $this->purchaseLead->handle($buyer, $lead, $actor));
    }

    /**
     * Datiert einen Prueffall samt seiner Stufenzeitpunkte zurueck.
     *
     * Der Trichter liest die Zeiten, aus denen er den Stillstand rechnet, aus
     * drei Quellen (tenants.created_at, users.email_verified_at /
     * phone_number_verified_at und tenant_onboarding_steps.completed_at) --
     * deshalb werden alle drei verschoben. Reine Darstellungsdaten; am
     * Verhalten der Sperre oder des Wizards aendert sich dadurch nichts.
     */
    private function backdate(Tenant $workspace, User $user, int $days): void
    {
        $at = now()->subDays($days);

        Tenant::query()->whereKey($workspace->getKey())->update(['created_at' => $at]);

        User::query()->whereKey($user->getKey())->update([
            'created_at' => $at,
            'email_verified_at' => $at,
            'phone_number_verified_at' => $user->phone_number_verified_at === null ? null : $at,
        ]);

        TenantOnboardingStep::query()
            ->where('tenant_id', $workspace->getKey())
            ->update(['completed_at' => $at]);

        $this->onboarding->forgetProgress($workspace);
    }

    /**
     * Fuehrt einen Aufruf samt seiner Queue-Zuhoerer sofort aus.
     *
     * Die Umschaltung gilt nur fuer diesen Aufruf: Alles andere (Mails der
     * Strecke, Webhooks) soll weiterhin in der Queue liegen und nicht beim
     * Seedern verschickt werden.
     */
    private function synchronously(callable $callback): void
    {
        $previous = config('queue.default');

        config(['queue.default' => 'sync']);

        try {
            $callback();
        } finally {
            config(['queue.default' => $previous]);
        }
    }
}
