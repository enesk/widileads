<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\PublishFunnel;
use App\Constants\FunnelStatus;
use App\Constants\TenantType;
use App\Models\Funnel;
use App\Models\FunnelOrigin;
use App\Models\FunnelTheme;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FunnelTemplateImporter;
use Illuminate\Database\Seeder;

/**
 * Legt den dreistufigen Funnel "Elektriker-Anfrage" fuer elektrikerportal.com
 * an und veroeffentlicht ihn sofort.
 *
 * Aufruf:
 *
 *     php artisan db:seed --class=ElektrikerportalFunnelSeeder
 *
 * Bewusst nicht Teil des DatabaseSeeder: Der Funnel gehoert zu einer konkreten
 * Seite, nicht zu einer frischen Installation.
 *
 * Das Frontend auf elektrikerportal.com rendert eigenes Markup und spricht die
 * oeffentliche Runtime-API an. Darum setzt der Seeder kein Erscheinungsbild,
 * sondern nur die Freigabeliste -- ohne sie lehnt die API jeden Browser-Aufruf
 * von dort mit 403 ab.
 *
 * Der Seeder ist wiederholbar. Existiert der Funnel bereits, wird er nicht ein
 * zweites Mal angelegt; die Freigabeliste wird trotzdem abgeglichen und der
 * Token noch einmal ausgegeben, denn genau den braucht das Frontend.
 *
 * Offen (Ticket #17): Mandant und Leadpreis. Bis zur Klaerung liegt der Funnel
 * beim Betreiber-Mandanten, und die Vorlage traegt keinen eigenen Preis, sodass
 * die Vorgabe aus der Konfiguration greift.
 */
class ElektrikerportalFunnelSeeder extends Seeder
{
    private const TEMPLATE = 'elektrikerportal';

    private const SLUG = 'elektrikerportal-anfrage';

    /**
     * Vergleich in der Freigabeliste ist exakt nach Schema und Host -- darum
     * beide Schreibweisen. elektriker.test ist die lokale Entwicklungsseite
     * (Herd), die sowohl ueber http als auch ueber https erreichbar ist.
     *
     * @var list<string>
     */
    private const ORIGINS = [
        'https://elektrikerportal.com',
        'https://www.elektrikerportal.com',
        'https://elektriker.test',
        'http://elektriker.test',
    ];

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
            $this->allowOrigins($existing);
            $this->report($existing, 'existiert bereits');

            return;
        }

        $funnel = $this->templateImporter->import(self::TEMPLATE, $tenant);

        // Nur die Pflichtzeile mit den Vorgabewerten der Tabelle: Das Frontend
        // rendert selbst, ein fehlendes Theme soll aber keine Vorschau im
        // Builder brechen.
        FunnelTheme::query()->firstOrCreate(['funnel_id' => $funnel->getKey()]);

        $this->allowOrigins($funnel);

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

    private function allowOrigins(Funnel $funnel): void
    {
        foreach (self::ORIGINS as $origin) {
            FunnelOrigin::query()->firstOrCreate([
                'funnel_id' => $funnel->getKey(),
                'origin' => $origin,
            ]);
        }
    }

    private function report(Funnel $funnel, string $state): void
    {
        $this->command?->info(sprintf(
            'Funnel "%s" %s. Status: %s, Token: %s, Freigegeben: %s',
            $funnel->name,
            $state,
            $funnel->status instanceof FunnelStatus ? $funnel->status->value : (string) $funnel->status,
            $funnel->public_token,
            implode(', ', self::ORIGINS),
        ));
    }
}
