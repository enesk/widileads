<?php

declare(strict_types=1);

namespace App\Marketplace;

use App\Models\Funnel;

/**
 * Was ein Kaeufer vom Angebot der Plattform sehen darf (FB-051).
 *
 * Ein Kaeufer soll seine Kaufkriterien auf einzelne Funnels einschraenken
 * koennen -- dafuer muss er wissen, welche es gibt. Das ist ein bewusster
 * Blick ueber die Mandantengrenze, deshalb steht er hier und nicht verstreut
 * in Livewire-Komponenten: eine benannte Stelle, an der nachlesbar ist, was
 * genau freigegeben wird.
 *
 * Freigegeben sind ausschliesslich Kennung und Name veroeffentlichter Funnels.
 * Das ist die Angebotsliste des Marktplatzes, keine Betreiberinterna: Struktur,
 * Preise, Leads und Entwuerfe bleiben aussen vor. FB-053 baut auf derselben
 * Liste auf.
 */
class MarketplaceCatalog
{
    /**
     * Veroeffentlichte Funnels als Kennung => Name, alphabetisch.
     *
     * @return array<int, string>
     */
    public function publishedFunnels(): array
    {
        return Funnel::query()
            // Der Kaeufer-Mandant besitzt keinen dieser Funnels -- ohne das
            // Abschalten des Mandanten-Scopes waere die Liste immer leer.
            ->withoutGlobalScope('tenant')
            ->published()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->map(static fn (string $name): string => $name)
            ->all();
    }
}
