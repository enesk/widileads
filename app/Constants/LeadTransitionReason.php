<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Grund eines Zustandswechsels (FB-030).
 *
 * Jeder Aufruf von App\Services\LeadStateService::transition() muss angeben,
 * WARUM der Zustand wechselt. Der Grund wird unveraenderlich im
 * lead_state_log festgehalten und ist die Grundlage fuer Reklamationen,
 * Gutschriften und die Abrechnung -- ein Zustandswechsel ohne nachvollziehbaren
 * Grund waere fachlich wertlos.
 *
 * Der Wert eines Case steht so in der Datenbank und darf nach dem ersten
 * Einsatz nicht mehr geaendert werden. Jeder Case braucht eine deutsche
 * Beschriftung unter `lang/de/funnel.php` -> `lead.reason.<wert>` (durch Test
 * abgesichert).
 */
enum LeadTransitionReason: string
{
    /** Die Pruefung nach dem Anlegen war erfolgreich, der Lead ist kaufbar (FB-033). */
    case SCREENING_PASSED = 'screening_passed';

    /** Gleiche E-Mail oder Telefonnummer im selben Funnel innerhalb der Dublettenfrist (FB-023/FB-033). */
    case DUPLICATE = 'duplicate';

    /** Kontaktdaten sind nicht plausibel: Telefonnummer nicht normalisierbar, Wegwerf-Mail (FB-033). */
    case IMPLAUSIBLE_CONTACT = 'implausible_contact';

    /** Die Einreichung wurde als Spam erkannt: Honeypot, Zeitfalle, Rate-Limit (FB-023). */
    case SPAM = 'spam';

    /** Ein Kaeufer hat den Lead in den Warenkorb gelegt (FB-054). */
    case RESERVED_BY_BUYER = 'reserved_by_buyer';

    /** Die Reservierung ist ohne Kauf abgelaufen (config('funnel.lead.reservation_ttl'), FB-054). */
    case RESERVATION_EXPIRED = 'reservation_expired';

    /** Der Kaeufer hat die Reservierung selbst aufgehoben (FB-054). */
    case RESERVATION_RELEASED = 'reservation_released';

    /** Der Kauf ist abgeschlossen und verbucht (FB-054). */
    case PURCHASED = 'purchased';

    /** Ein Anrufversuch wurde als Gespraech gewertet (FB-083). */
    case CALL_ANSWERED = 'call_answered';

    /** Die Kontaktpflicht ist erfuellt, ohne dass der Lead erreicht wurde (FB-083). */
    case CALL_ATTEMPTS_EXHAUSTED = 'call_attempts_exhausted';

    /** Der Operator hat eine Reklamation des Kaeufers bestaetigt (FB-058). */
    case COMPLAINT_APPROVED = 'complaint_approved';

    /** Die Reklamationsfrist ist ohne Beanstandung verstrichen (FB-058). */
    case COMPLAINT_PERIOD_ELAPSED = 'complaint_period_elapsed';

    /** Die Aufbewahrungsfrist ist erreicht (config('funnel.lead.retention_days'), FB-037). */
    case RETENTION_ELAPSED = 'retention_elapsed';

    /** Ein Operator-Admin hat den Zustand mit Begruendung von Hand gesetzt (FB-036). */
    case MANUAL_OVERRIDE = 'manual_override';

    /**
     * Deutsche Beschriftung fuer Oberflaeche, Protokollansicht und Exporte.
     */
    public function label(): string
    {
        return __('funnel.lead.reason.'.$this->value);
    }

    /**
     * Beschriftungen aller Gruende, z. B. fuer Auswahlfilter.
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
