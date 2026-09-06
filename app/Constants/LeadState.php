<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Lebenszyklus eines Leads (FB-030).
 *
 * `leads.lead_state` ist die EINZIGE Zustandsspalte eines Leads
 * (Architekturleitsatz 1). Es gibt daneben keine Boolean-Flags wie is_reached
 * oder is_fake -- jede Zustandsaussage wird aus diesem Enum abgeleitet.
 *
 * Der Wert eines Case steht so in der Datenbank und im lead_state_log und darf
 * nach dem ersten Einsatz nicht mehr geaendert werden. Die Werte sind bewusst
 * deutsch, weil sie fachliche Begriffe des Lead-Geschaefts sind und in
 * Auswertungen und Exporten unmittelbar lesbar sein sollen.
 *
 * Welche Uebergaenge zwischen diesen Zustaenden erlaubt sind, steht
 * ausschliesslich in LeadTransitions::TABLE. Gewechselt wird ausschliesslich
 * ueber App\Services\LeadStateService::transition() (Architekturleitsatz 2).
 *
 * Jeder Case braucht eine deutsche Beschriftung unter
 * `lang/de/funnel.php` -> `lead.state.<wert>` (durch Test abgesichert).
 */
enum LeadState: string
{
    /** Vollstaendige Anfrage, noch nicht zum Kauf freigegeben (Spam-/Dublettenpruefung laeuft). */
    case NEU = 'neu';

    /** Im Marktplatz sichtbar und kaufbar. */
    case VERFUEGBAR = 'verfuegbar';

    /** Von einem Kaeufer in den Warenkorb gelegt; die Reservierung verfaellt nach config('funnel.lead.reservation_ttl'). */
    case RESERVIERT = 'reserviert';

    /** Gekauft: Kontaktdaten sind fuer den Kaeufer freigegeben, der Preis ist festgeschrieben. */
    case VERKAUFT = 'verkauft';

    /** Endzustand: Kontakt nachgewiesen (Phase 2, FB-E7) -- der Lead wird abgerechnet. */
    case ERREICHT = 'erreicht';

    /** Endzustand: Kontaktversuche nachweislich ausgeschoepft -- der Kaeufer erhaelt eine Gutschrift. */
    case UNERREICHBAR = 'unerreichbar';

    /** Endzustand: Spam, Fehleingabe oder Dublette -- es wird nicht abgerechnet. */
    case UNGUELTIG = 'ungueltig';

    /** Endzustand: nie verkauft, Aufbewahrungsfrist erreicht (FB-037). */
    case ABGELAUFEN = 'abgelaufen';

    /**
     * Ist dieser Zustand ein Endzustand?
     *
     * Beim Eintritt in einen Endzustand wird der Preis einmalig in
     * `leads.settled_price` festgeschrieben und danach nie wieder geaendert
     * (Architekturleitsatz 4: Geld folgt Belegen). Aus einem Endzustand fuehrt
     * kein Uebergang mehr heraus.
     */
    public function isFinal(): bool
    {
        return in_array($this, self::finalStates(), true);
    }

    /**
     * Deutsche Beschriftung fuer Oberflaeche, Filter und Exporte.
     */
    public function label(): string
    {
        return __('funnel.lead.state.'.$this->value);
    }

    /**
     * Alle Endzustaende in der Reihenfolge des Datenmodells.
     *
     * @return list<self>
     */
    public static function finalStates(): array
    {
        return [
            self::ERREICHT,
            self::UNERREICHBAR,
            self::UNGUELTIG,
            self::ABGELAUFEN,
        ];
    }

    /**
     * Beschriftungen aller Zustaende, z. B. fuer Auswahlfilter.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->label();
        }

        return $labels;
    }
}
