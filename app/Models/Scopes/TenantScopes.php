<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Filament\Facades\Filament;

/**
 * Die Namen aller globalen Mandanten-Scopes (FB-091).
 *
 * Es gibt zwei davon, und das ist leicht zu uebersehen:
 *
 *  - `tenant` aus App\Models\Concerns\BelongsToTenant -- unser eigener;
 *  - je Filament-Panel einen zweiten, den Filament selbst fuer jede Resource
 *    registriert, die auf den Mandanten eingeschraenkt ist. Er heisst nach dem
 *    Panel, im Dashboard also `dashboard_tenancy`.
 *
 * Wer den Mandanten-Scope bewusst abschaltet -- der Marktplatz tut das, weil
 * ein Kaeufer Leads sehen muss, die ihm noch nicht gehoeren -- muss beide
 * loswerden. Bis FB-091 stand an diesen Stellen nur `withoutGlobalScope('tenant')`;
 * der Filament-Scope blieb stehen und schnitt das Ergebnis auf den eigenen
 * Mandanten zurueck. Fuer den Kaeufer hiess das: Marktplatz und Fragebogenauswahl
 * waren immer leer.
 *
 * Deshalb steht die Liste hier und nicht als Zeichenkette an jeder Abfrage:
 * kommt ein Panel dazu, deckt sie es von selbst mit ab.
 */
final class TenantScopes
{
    /** Unser eigener Scope aus BelongsToTenant. */
    public const OWN = 'tenant';

    /**
     * Alle Scope-Namen, die einen Mandanten einschraenken.
     *
     * Einen Scope zu entfernen, der gar nicht registriert ist, kostet nichts --
     * die Liste darf also grosszuegig sein.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        $names = [self::OWN];

        foreach (Filament::getPanels() as $panel) {
            $names[] = $panel->getTenancyScopeName();
        }

        return array_values(array_unique($names));
    }
}
