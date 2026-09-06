<?php

declare(strict_types=1);

namespace App\Marketplace;

use App\Constants\TenantType;
use App\Models\Funnel;

/**
 * Was ein Kaeufer vom Angebot der Plattform sehen darf (FB-051).
 *
 * Ein Kaeufer soll seine Kaufkriterien auf einzelne Funnels einschraenken
 * koennen -- dafuer muss er wissen, welche es gibt. Das ist die bislang einzige
 * Stelle im Projekt, an der der Mandanten-Scope bewusst abgeschaltet wird.
 * Genau deshalb steht sie hier und nicht verstreut in Livewire-Komponenten: an
 * einer benannten Stelle laesst sich nachlesen und pruefen, was freigegeben ist.
 *
 * **Freigegeben sind ausschliesslich Kennung und Name.** Kein Preis, keine
 * Zaehldaten, keine Einstellungen, keine Struktur, keine Leads. Diese Grenze ist
 * verbindlich: Wer hier ein Feld ergaenzt, gibt es allen Kaeufern ueber alle
 * Betreiber hinweg frei. FB-053 baut auf dieser Liste auf und erweitert sie
 * nicht stillschweigend.
 *
 * Zwei Einschraenkungen sichern das ab:
 *
 *  - nur Funnels im Status `published` -- Entwuerfe und archivierte Funnels sind
 *    kein Angebot;
 *  - nur Funnels, deren Eigentuemer ein Betreiber-Mandant ist. Ein
 *    Kaeufer-Mandant, der sich selbst einen Funnel anlegt, taucht hier nie auf.
 *
 * Offener Punkt in docs/BACKLOG.md (FB-043): Sobald mehrere Betreiber auf der
 * Plattform sind, sehen alle Kaeufer die Funnelnamen aller Betreiber.
 */
class MarketplaceCatalog
{
    /**
     * Veroeffentlichte Funnels von Betreiber-Mandanten als Kennung => Name,
     * alphabetisch.
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
            ->whereHas('tenant', static fn ($query) => $query->where('type', TenantType::OPERATOR->value))
            // Ausdruecklich nur diese zwei Spalten: was nicht gelesen wird, kann
            // auch nicht versehentlich ausgeliefert werden.
            ->orderBy('name')
            ->pluck('name', 'id')
            ->map(static fn (string $name): string => $name)
            ->all();
    }
}
