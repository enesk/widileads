<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Concerns;

use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Attributes\Locked;

/**
 * Der Mandantenkontext einer Portal-Komponente (Portal Phase 1).
 *
 * **Warum das nicht die Middleware allein erledigt:** Die Portalseiten tragen
 * die Workspace-UUID im Pfad und ResolvePortalTenant setzt daraus den
 * Filament-Tenant. Jede Folgeanfrage einer Livewire-Komponente geht aber an die
 * globale Route `/livewire/update` -- und die laeuft durch die Middleware-Gruppe
 * `web`, nicht durch die Middleware der Seitenroute. Beim ersten `wire:click`
 * waere der Mandant also weg.
 *
 * Das waere kein leerer Bildschirm, sondern ein Datenleck: Der globale Scope aus
 * BelongsToTenant steigt bei fehlendem Mandanten still aus und zeigt dann die
 * Daten *aller* Mandanten.
 *
 * Deshalb haelt die Komponente die UUID selbst -- gesperrt, also vom Browser
 * nicht veraenderbar -- und stellt den Kontext zu Beginn jeder Anfrage wieder
 * her. Die Mitgliedschaft wird dabei jedes Mal neu geprueft, nicht nur beim
 * ersten Aufruf: Wird ein Nutzer aus einem Workspace entfernt, waehrend seine
 * Seite offen ist, endet sein Zugriff mit der naechsten Anfrage.
 *
 * **Zweiter Riegel:** ResolvePortalTenant ist zusaetzlich als persistente
 * Livewire-Middleware registriert (AppServiceProvider), laeuft also auch bei
 * /livewire/update ueber den gemerkten Pfad der Ursprungsseite. Damit steht der
 * Mandant selbst dann, wenn eine Portal-Komponente diesen Trait vergisst. Der
 * Trait bleibt trotzdem: Er bindet den Kontext an den gesperrten Zustand der
 * Komponente und traegt auch dort, wo es keinen Seitenpfad mit UUID gibt. Die
 * beiden kosten zusammen keine zweite Abfrage -- portalTenant() uebernimmt
 * einen bereits gesetzten Mandanten mit passender UUID.
 */
trait InteractsWithPortalTenant
{
    /**
     * Die Workspace-UUID, wie sie im Pfad der Seite steht.
     */
    #[Locked]
    public string $tenantUuid = '';

    /**
     * Innerhalb einer Anfrage einmal aufgeloest. Nicht oeffentlich, wandert also
     * nicht in den Zustand der Komponente.
     */
    private ?Tenant $portalTenant = null;

    /**
     * Laeuft zu Beginn jeder Anfrage an diese Komponente -- auch bei jeder
     * Folgeanfrage an /livewire/update.
     */
    public function bootInteractsWithPortalTenant(): void
    {
        if ($this->tenantUuid === '') {
            // Erster Aufruf: Den Mandanten hat die Middleware bereits gesetzt,
            // er wird hier nur fuer die Folgeanfragen festgehalten.
            $tenant = Filament::getTenant();

            if ($tenant instanceof Tenant) {
                $this->portalTenant = $tenant;
                $this->tenantUuid = (string) $tenant->uuid;
            }

            return;
        }

        Filament::setTenant($this->portalTenant(), isQuiet: true);
    }

    /**
     * Der Mandant dieser Seite, gegen die Mitgliedschaft geprueft.
     */
    protected function portalTenant(): Tenant
    {
        if ($this->portalTenant instanceof Tenant) {
            return $this->portalTenant;
        }

        $tenant = Filament::getTenant();

        // Steht der Mandant schon, wird er uebernommen statt neu geladen: beim
        // ersten Aufruf hat ihn ResolvePortalTenant aus dem Pfad gesetzt, bei
        // jeder Folgeanfrage dieselbe Middleware als persistente
        // Livewire-Middleware (siehe AppServiceProvider). Beide haben die
        // Mitgliedschaft dabei bereits geprueft -- die UUID muss nur zu der
        // gesperrten UUID dieser Komponente passen, sonst wird regulaer
        // aufgeloest und geprueft.
        if ($tenant instanceof Tenant && ($this->tenantUuid === '' || (string) $tenant->uuid === $this->tenantUuid)) {
            return $this->portalTenant = $tenant;
        }

        $tenant = Tenant::query()->where('uuid', $this->tenantUuid)->first();

        abort_unless($tenant instanceof Tenant, 404);

        $user = $this->portalUser();

        // Ohne Hinweis auf den Mandanten: Eine fremde, aber existierende
        // Workspace-UUID darf sich nicht von einer erfundenen unterscheiden
        // lassen.
        abort_unless($user instanceof User && $user->canAccessTenant($tenant), 403);

        return $this->portalTenant = $tenant;
    }

    protected function portalUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
