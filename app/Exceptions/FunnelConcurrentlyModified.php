<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Der bearbeitete Datensatz wurde zwischenzeitlich von jemand anderem geaendert
 * (FB-015).
 *
 * Der Builder speichert automatisch. Ohne diese Pruefung wuerde der langsamere
 * von zwei Bearbeitern die Aenderungen des schnelleren stillschweigend
 * ueberschreiben - der Verlust faellt erst auf, wenn der Funnel bereits falsch
 * veroeffentlicht ist.
 */
class FunnelConcurrentlyModified extends Exception
{
    public function __construct(public readonly string $subject)
    {
        parent::__construct(__('funnel.builder.concurrent_edit'));
    }
}
