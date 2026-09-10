<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\PublishFunnel;
use App\Constants\FunnelStatus;
use App\Constants\TenantType;
use App\Models\Funnel;
use App\Models\FunnelTheme;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FunnelTemplateImporter;
use Illuminate\Database\Seeder;

/**
 * Legt den einseitigen Funnel "Sichere dein Tier jetzt ab" an und
 * veroeffentlicht ihn sofort.
 *
 * Aufruf:
 *
 *     php artisan db:seed --class=TierAbsicherungFunnelSeeder
 *
 * Bewusst nicht Teil des DatabaseSeeder: Der Funnel gehoert zu einer konkreten
 * Landingpage, nicht zu einer frischen Installation.
 *
 * Der Seeder ist wiederholbar. Existiert der Funnel bereits, wird er nicht ein
 * zweites Mal angelegt -- nur der Token noch einmal ausgegeben, denn genau den
 * braucht das Frontend.
 */
class TierAbsicherungFunnelSeeder extends Seeder
{
    private const TEMPLATE = 'tier-absicherung';

    private const SLUG = 'tier-absicherung';

    public function __construct(
        private readonly FunnelTemplateImporter $templateImporter,
        private readonly PublishFunnel $publishFunnel,
    ) {}

    public function run(): void
    {
        $tenant = $this->operatorTenant();

        if ($tenant === null) {
            $this->command?->warn('Kein Mandant vorhanden - der Funnel wurde uebersprungen.');

            return;
        }

        $existing = Funnel::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('slug', self::SLUG)
            ->first();

        if ($existing !== null) {
            $this->report($existing, 'existiert bereits');

            return;
        }

        $funnel = $this->templateImporter->import(self::TEMPLATE, $tenant);

        // Die Vorlage traegt ein Theme, der FunnelStructureWriter schreibt aber
        // nur die Struktur. Ohne diesen Schritt bliebe das Erscheinungsbild
        // leer und der Absende-Knopf hiesse "Absenden" statt "Jetzt absichern
        // lassen".
        $this->applyTheme($funnel);

        $this->publishFunnel->handle($funnel, $this->publisherFor($tenant));

        $this->report($funnel->refresh(), 'angelegt und veroeffentlicht');
    }

    /**
     * Der Betreiber-Mandant. Faellt auf den ersten Mandanten zurueck, damit der
     * Seeder auch in einer Installation ohne gepflegten Typ laeuft.
     */
    private function operatorTenant(): ?Tenant
    {
        return Tenant::query()->withoutGlobalScopes()->where('type', TenantType::OPERATOR->value)->first()
            ?? Tenant::query()->withoutGlobalScopes()->first();
    }

    /**
     * Veroeffentlicht wird im Namen eines Nutzers des Mandanten, damit die
     * Fassung einen Urheber traegt. Gibt es keinen, bleibt das Feld leer.
     */
    private function publisherFor(Tenant $tenant): ?User
    {
        return $tenant->users()->first();
    }

    private function applyTheme(Funnel $funnel): void
    {
        FunnelTheme::query()->updateOrCreate(
            ['funnel_id' => $funnel->getKey()],
            [
                'primary_color' => '#1d5c9e',
                'secondary_color' => '#64748b',
                'background_color' => '#ffffff',
                'text_color' => '#0f172a',
                'font' => 'inter',
                'progress_style' => 'none',
                'border_radius' => 10,
                'button_next_label' => 'Weiter',
                'button_back_label' => 'Zurueck',
                'button_submit_label' => 'Jetzt absichern lassen',
            ],
        );
    }

    private function report(Funnel $funnel, string $state): void
    {
        $this->command?->info(sprintf(
            'Funnel "%s" %s. Status: %s, Token: %s',
            $funnel->name,
            $state,
            $funnel->status instanceof FunnelStatus ? $funnel->status->value : (string) $funnel->status,
            $funnel->public_token,
        ));
    }
}
