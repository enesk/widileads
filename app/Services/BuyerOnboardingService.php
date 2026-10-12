<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\AuditAction;
use App\Constants\BuyerRegistrationStatus;
use App\Constants\TenancyPermissionConstants;
use App\Constants\TenantType;
use App\Models\BuyerProfile;
use App\Models\BuyerRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Registrierung und Freischaltung von Kaeufern (FB-050).
 *
 * Die einzige Stelle, an der ein Kaeufer-Mandant entsteht und an der sich sein
 * Freischaltungsstand aendert. Das ist kein Formalismus: An `status` haengt der
 * Marktplatzzugriff, und jede Entscheidung darueber muss im Audit-Log stehen
 * (FB-005). Wer die Spalte direkt schreibt, umgeht beides -- deshalb steht sie
 * am Modell in $guarded.
 */
class BuyerOnboardingService
{
    public function __construct(
        private readonly TenantCreationService $tenantCreationService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Legt aus einer eingegangenen Registrierung einen Kaeufer-Mandanten an.
     *
     * Der Mandant entsteht ueber denselben Weg wie jeder andere
     * (TenantCreationService), nur mit Typ `buyer` und der Rolle `buyer` fuer
     * den registrierenden Benutzer. Er ist danach `pending` und kommt an keinen
     * Lead, bis der Plattform-Admin entschieden hat.
     *
     * Mandant und Registrierungssatz entstehen in einer Transaktion: einen
     * Kaeufer-Mandanten ohne Registrierungssatz gaebe es sonst, und der haette
     * keinen Freischaltungsstand -- also auch keine wirksame Sperre.
     *
     * Die Branche aus der Registrierung (LP-PORTAL-004) landet als erster
     * Eintrag in den Kaufkriterien des neuen Workspaces
     * (`buyer_profiles.funnel_ids`) und nicht in einer eigenen Spalte am
     * Registrierungssatz. Grund: Der Marktplatz filtert auf genau dieses Feld,
     * und der erste Wizard-Schritt liest seine Vorauswahl daraus. Eine zweite
     * Spalte daneben waere eine Angabe, die den Marktplatz nicht veraendert --
     * und zwei Wahrheiten, die auseinanderlaufen koennen.
     *
     * Das Profil entsteht in derselben Transaktion wie Mandant und
     * Registrierungssatz: Es ist Teil des Vorgangs, nicht ein Nachtrag.
     *
     * @param  array{company_name: string, contact_name: string, contact_email: string, contact_phone?: string|null, broker_register_number?: string|null, vat_id: string, funnel_id?: int|null}  $data
     */
    public function register(User $user, array $data): BuyerRegistration
    {
        return DB::transaction(function () use ($user, $data): BuyerRegistration {
            $tenant = $this->tenantCreationService->createTenant(
                $user,
                $data['company_name'],
                TenantType::BUYER,
                TenancyPermissionConstants::ROLE_BUYER,
            );

            $registration = new BuyerRegistration([
                'tenant_id' => $tenant->getKey(),
                'company_name' => $data['company_name'],
                'contact_name' => $data['contact_name'],
                'contact_email' => $data['contact_email'],
                'contact_phone' => $data['contact_phone'] ?? null,
                'broker_register_number' => $data['broker_register_number'] ?? null,
                'vat_id' => $data['vat_id'],

                // Der Zeitstempel ist der Nachweis der Zustimmung zum
                // Auftragsverarbeitungsvertrag. Er wird hier gesetzt und nicht
                // vom Formular uebernommen -- ein Client soll ihn nicht waehlen
                // koennen.
                'av_accepted_at' => now(),
            ]);

            $registration->status = BuyerRegistrationStatus::PENDING;
            $registration->save();

            $funnelId = $data['funnel_id'] ?? null;

            if ($funnelId !== null) {
                BuyerProfile::query()->updateOrCreate(
                    ['tenant_id' => $tenant->getKey()],
                    ['funnel_ids' => [(int) $funnelId]],
                );
            }

            return $registration;
        });
    }

    /**
     * Schaltet einen Kaeufer frei. Ab hier -- und nur ab hier -- erreicht er
     * den Marktplatz.
     */
    public function approve(BuyerRegistration $registration, User $admin): BuyerRegistration
    {
        return $this->decide($registration, BuyerRegistrationStatus::ACTIVE, $admin, null);
    }

    /**
     * Lehnt einen Kaeufer ab. Die Begruendung ist Pflicht, damit die
     * Entscheidung nachvollziehbar bleibt und dem Kaeufer mitgeteilt werden
     * kann.
     */
    public function reject(BuyerRegistration $registration, User $admin, string $reason): BuyerRegistration
    {
        return $this->decide($registration, BuyerRegistrationStatus::REJECTED, $admin, $reason);
    }

    /**
     * Schreibt die Entscheidung des Plattform-Admins samt Audit-Eintrag.
     *
     * Zustandswechsel und Protokolleintrag liegen in einer Transaktion: ein
     * freigeschalteter Kaeufer ohne Beleg waere genau die Luecke, die das
     * Audit-Log schliessen soll.
     */
    private function decide(
        BuyerRegistration $registration,
        BuyerRegistrationStatus $status,
        User $admin,
        ?string $reason,
    ): BuyerRegistration {
        return DB::transaction(function () use ($registration, $status, $admin, $reason): BuyerRegistration {
            $previous = $registration->status;

            $registration->status = $status;
            $registration->reviewed_at = now();
            $registration->reviewed_by = $admin->getKey();
            $registration->rejection_reason = $reason;
            $registration->save();

            $this->auditLogger->log(
                $status === BuyerRegistrationStatus::ACTIVE
                    ? AuditAction::BUYER_APPROVED
                    : AuditAction::BUYER_REJECTED,
                $registration,
                array_filter([
                    'company_name' => $registration->company_name,
                    'previous_status' => $previous->value,
                    'reason' => $reason,
                ], static fn (mixed $value): bool => $value !== null),
                $registration->tenant,
                $admin,
            );

            return $registration;
        });
    }
}
