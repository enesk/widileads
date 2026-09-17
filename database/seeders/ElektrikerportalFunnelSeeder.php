<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\PublishFunnel;
use App\Constants\FunnelStatus;
use App\Constants\TenantType;
use App\Constants\WebhookEvent;
use App\Models\Funnel;
use App\Models\FunnelOrigin;
use App\Models\FunnelTheme;
use App\Models\FunnelWebhook;
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
 * Dazu der Webhook `lead.created` an das Portal: Das Portal ordnet die Anfrage
 * ueber die Antwort `firmenprofil` dem Betrieb zu und erkennt Wiederholungen an
 * der Lead-UUID. Das Secret entsteht beim Anlegen und wird genau dann einmal
 * ausgegeben -- danach gibt es die Anwendung nicht mehr heraus
 * (App\Models\FunnelWebhook). Es gehoert ins Portal, nicht ins Repository.
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

    /**
     * Empfaenger von `lead.created` auf dem Portal.
     */
    private const PORTAL_WEBHOOK_URL = 'https://elektrikerportal.com/webhooks/leads';

    /**
     * Die lokale Entwicklungsseite. Nur in der Umgebung `local` angelegt.
     */
    private const LOCAL_WEBHOOK_URL = 'https://elektriker.test/webhooks/leads';

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
            $this->subscribeWebhooks($existing);
            $this->report($existing, 'existiert bereits');

            return;
        }

        $funnel = $this->templateImporter->import(self::TEMPLATE, $tenant);

        // Nur die Pflichtzeile mit den Vorgabewerten der Tabelle: Das Frontend
        // rendert selbst, ein fehlendes Theme soll aber keine Vorschau im
        // Builder brechen.
        FunnelTheme::query()->firstOrCreate(['funnel_id' => $funnel->getKey()]);

        $this->allowOrigins($funnel);
        $this->subscribeWebhooks($funnel);

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

    /**
     * Legt die Webhooks an, die noch fehlen. Bestehende bleiben unberuehrt,
     * sonst wuerde ein erneuter Lauf das Secret wechseln, das im Portal
     * hinterlegt ist.
     *
     * Lokal geht der Webhook an das Live-Portal inaktiv an den Start: Lokale
     * Testanfragen samt Kontaktdaten sollen dort nicht ankommen, und das
     * lokale Secret kennt das Live-Portal ohnehin nicht.
     */
    private function subscribeWebhooks(Funnel $funnel): void
    {
        $isLocal = app()->environment('local');

        $this->subscribe($funnel, self::PORTAL_WEBHOOK_URL, active: ! $isLocal);

        if ($isLocal) {
            $this->subscribe($funnel, self::LOCAL_WEBHOOK_URL, active: true);
        }
    }

    private function subscribe(Funnel $funnel, string $url, bool $active): void
    {
        $exists = FunnelWebhook::query()
            ->where('funnel_id', $funnel->getKey())
            ->where('url', $url)
            ->exists();

        if ($exists) {
            $this->command?->line(sprintf('Webhook %s besteht bereits, Secret unveraendert.', $url));

            return;
        }

        $webhook = FunnelWebhook::query()->create([
            'funnel_id' => $funnel->getKey(),
            'url' => $url,
            'secret' => FunnelWebhook::newSecret(),
            'events' => [WebhookEvent::LEAD_CREATED->value],
            'active' => $active,
        ]);

        // Einmalige Ausgabe auf der Konsole, nicht ins Log.
        $this->command?->warn(sprintf(
            'Webhook %s angelegt (%s). Secret, nur jetzt sichtbar: %s',
            $url,
            $active ? 'aktiv' : 'inaktiv',
            $webhook->secret,
        ));
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
