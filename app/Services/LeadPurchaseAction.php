<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadPurchase;
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
     * Fuehrt den Kauf aus und gibt den Kaufbeleg zurueck.
     *
     * Den Beleg braucht die Oberflaeche, um den Kaeufer danach direkt auf
     * seinen Lead zu fuehren -- ohne ihn muesste sie den Kauf nachtraeglich
     * suchen und koennte im Mehrfachverkauf den falschen finden.
     *
     * @param  int|null  $priceShownCents  Preis, den die Oberflaeche dem Kaeufer
     *                                     genannt hat. Weicht er vom heutigen
     *                                     Preis des Verkaeufers ab, wird der
     *                                     Kauf abgelehnt (LP-WALLET-007).
     */
    public function purchase(Tenant $buyer, Lead $lead, ?User $actor = null, ?int $priceShownCents = null): LeadPurchase;

    /**
     * Der Preis dieses Leads in Cent, wie er dem Kaeufer anzuzeigen ist.
     *
     * Die Oberflaeche fragt ihn hier ab und schickt ihn beim Kauf als
     * price_shown_cents zurueck -- so faellt auf, wenn der Verkaeufer den Preis
     * inzwischen geaendert hat (LP-WALLET-007).
     *
     * Mit Kaeufer gerechnet enthaelt der Preis bei Pay as you go den Aufschlag
     * (LP-POSTPAID-007) -- also den Betrag, den dieser Kaeufer wirklich traegt.
     */
    public function priceCentsOf(Lead $lead, ?Tenant $buyer = null): int;
}
