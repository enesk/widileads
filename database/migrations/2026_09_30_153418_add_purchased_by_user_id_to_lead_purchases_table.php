<?php

declare(strict_types=1);

use App\Constants\TenancyPermissionConstants;
use App\Models\TenantUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Der Kauf bekommt einen handelnden Nutzer.
 *
 * Bis hierher trug `lead_purchases` nur `buyer_tenant_id`: Wer im Mandanten
 * geklickt hat, war nirgends festgehalten. "Meine Leads" konnte deshalb nicht
 * zwischen Kollegen trennen, und die Trennung waere ohne diese Spalte auch
 * nicht nachtraeglich herstellbar -- die Information existiert sonst nirgends.
 *
 * **Nullable ist Absicht.** Der Autokauf (FB-056) entscheidet ueber ein
 * Kaufprofil, nicht ueber einen Menschen. Ihm einen Nutzer zuzuschreiben waere
 * falsch: Er hat nicht gekauft. Solche Kaeufe tauchen in "Meine Leads" bei
 * niemandem auf und stehen in "Team Leads" als automatisch.
 *
 * **Der Altbestand geht je Mandant an den aeltesten Admin.** Ohne Zuschreibung
 * waeren nach dem Deploy saemtliche bisherigen Kaeufe aus "Meine Leads"
 * verschwunden. Der aelteste Admin ist die einzige Zuordnung, die sich aus den
 * vorhandenen Daten begruenden laesst; gibt es im Mandanten keinen, bleibt die
 * Spalte null. Zugeschrieben wird ausschliesslich innerhalb desselben
 * Mandanten -- ein Nutzer eines fremden Mandanten kommt nicht in Frage.
 *
 * Die Zuschreibung laeuft ueber den Query Builder statt ueber ein
 * `UPDATE ... JOIN`, damit sie auf SQLite genauso laeuft wie auf MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_purchases', function (Blueprint $table) {
            $table->foreignId('purchased_by_user_id')->nullable()->after('buyer_tenant_id')
                ->constrained('users')->nullOnDelete();

            // "Meine Leads" liest genau danach: die Kaeufe eines Nutzers
            // innerhalb seines Mandanten.
            $table->index(['buyer_tenant_id', 'purchased_by_user_id']);
        });

        $this->assignExistingPurchasesToTenantAdmins();
    }

    public function down(): void
    {
        Schema::table('lead_purchases', function (Blueprint $table) {
            $table->dropIndex(['buyer_tenant_id', 'purchased_by_user_id']);
            $table->dropConstrainedForeignId('purchased_by_user_id');
        });
    }

    /**
     * Schreibt die vorhandenen Kaeufe je Kaeufer-Mandant dem aeltesten Nutzer
     * mit Admin-Rolle zu.
     *
     * Die Rolle haengt nicht am Benutzer, sondern an der Mitgliedschaft
     * (TenantUser als Pivot mit HasRoles). Gefragt wird deshalb ueber
     * `tenant_user` und `model_has_roles`, und "aeltester" heisst: die
     * aelteste Mitgliedschaft im Mandanten.
     *
     * Oeffentlich, damit der Test sie gegen echte Daten laufen lassen kann:
     * Zum Zeitpunkt der Migration ist die Datenbank im Test leer, der
     * Altbestand entsteht erst danach.
     */
    public function assignExistingPurchasesToTenantAdmins(): void
    {
        $adminRoleIds = DB::table('roles')
            ->where('name', TenancyPermissionConstants::ROLE_ADMIN)
            ->where('is_tenant_role', true)
            ->pluck('id');

        if ($adminRoleIds->isEmpty()) {
            return;
        }

        $tenantIds = DB::table('lead_purchases')
            ->distinct()
            ->pluck('buyer_tenant_id');

        foreach ($tenantIds as $tenantId) {
            $userId = DB::table('tenant_user')
                ->join('model_has_roles', function ($join): void {
                    $join->on('model_has_roles.model_id', '=', 'tenant_user.id')
                        ->where('model_has_roles.model_type', '=', TenantUser::class);
                })
                ->whereIn('model_has_roles.role_id', $adminRoleIds)
                ->where('tenant_user.tenant_id', $tenantId)
                ->orderBy('tenant_user.id')
                ->value('tenant_user.user_id');

            if ($userId === null) {
                continue;
            }

            DB::table('lead_purchases')
                ->where('buyer_tenant_id', $tenantId)
                ->update(['purchased_by_user_id' => $userId]);
        }
    }
};
