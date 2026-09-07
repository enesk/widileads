<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;

/**
 * Der Kauf eines Leads durch einen Kaeufer-Mandanten.
 *
 * Der Marktplatz (FB-053) zeigt den Kaufknopf, loest den Kauf aber nicht selbst
 * aus -- er fragt diese Zusage. Bis FB-054 den Kaufvorgang baut, antwortet die
 * Vorgabeumsetzung mit "noch nicht verfuegbar" und der Knopf bleibt inaktiv.
 * FB-054 tauscht nur die Container-Bindung, genau wie FB-032 es mit dem
 * LeadPurchaseLookup vorgesehen hat und FB-031 mit dem SubmissionReceiver
 * gemacht hat.
 *
 * Die Trennung ist kein Formalismus: Der Kaufvorgang braucht Reservierung,
 * Guthabenpruefung und Zustandswechsel in einer Transaktion (FB-054). Waere er
 * hier vorweggenommen, entstuende genau die halbfertige zweite Kaufstelle, die
 * spaeter niemand mehr findet.
 */
interface LeadPurchaseAction
{
    /**
     * Kann heute ueberhaupt gekauft werden? Ist die Antwort nein, zeigt der
     * Marktplatz den Knopf inaktiv mit einem Hinweis.
     */
    public function isAvailable(): bool;

    /**
     * Darf dieser Kaeufer diesen Lead jetzt kaufen? Beantwortet nur die
     * Sichtbarkeit des Knopfes -- die verbindliche Pruefung gehoert in die
     * Kauftransaktion selbst (FB-054).
     */
    public function canPurchase(Tenant $buyer, Lead $lead): bool;

    /**
     * Fuehrt den Kauf aus.
     */
    public function purchase(Tenant $buyer, Lead $lead, ?User $actor = null): void;
}
