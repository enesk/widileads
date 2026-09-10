<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Sicherheitsrelevante Vorgaenge, die im Audit-Log (FB-005) festgehalten werden.
 *
 * Der Wert eines Case ist der in `audit_logs.action` gespeicherte Schluessel und
 * darf nach dem ersten Einsatz nicht mehr geaendert werden -- bestehende
 * Eintraege sind unveraenderlich und wuerden sonst ihre Bedeutung verlieren.
 *
 * Jeder Case braucht eine deutsche Beschriftung unter
 * `lang/de/funnel.php` -> `audit.actions.<wert mit _ statt .>` (durch Test
 * abgesichert). Punkte koennen dort nicht stehen, weil sie in
 * Uebersetzungsschluesseln eine Ebene trennen.
 */
enum AuditAction: string
{
    /** Erfolgreiche Anmeldung eines Benutzers (verdrahtet in FB-005). */
    case USER_LOGGED_IN = 'user.logged_in';

    /** Wechsel des aktiven Mandanten-Kontexts (verdrahtet in FB-005). */
    case TENANT_SWITCHED = 'tenant.switched';

    /** Einem Benutzer wurde eine Rolle zugewiesen (verdrahtet in FB-005). */
    case ROLE_ASSIGNED = 'role.assigned';

    /** Einem Benutzer wurde eine Rolle entzogen (verdrahtet in FB-005). */
    case ROLE_REVOKED = 'role.revoked';

    /** API-Token erstellt -- wird von FB-006 (Sanctum-Tokens je Tenant) aufgerufen. */
    case API_TOKEN_CREATED = 'api_token.created';

    /** API-Token widerrufen/geloescht -- wird von FB-006 aufgerufen. */
    case API_TOKEN_DELETED = 'api_token.deleted';

    /** Datenexport angestossen -- wird von FB-073 (CSV/XLSX-Export) aufgerufen. */
    case DATA_EXPORTED = 'data.exported';

    /** Personenbezug eines Leads auf Loeschersuchen hin entfernt (FB-038). */
    case DATA_ERASED = 'data.erased';

    /** Lead durch einen Kaeufer gekauft -- wird von FB-054 (Kaufvorgang) aufgerufen. */
    case LEAD_PURCHASED = 'lead.purchased';

    /** Zwangsstatuswechsel eines Leads durch einen Operator-Admin -- wird von FB-036 aufgerufen. */
    case LEAD_STATE_FORCED = 'lead.state_forced';

    /** Einbettungsversuch von einer nicht freigegebenen Herkunft (FB-025). */
    case EMBED_ORIGIN_REJECTED = 'embed.origin_rejected';

    /** Kaeufer vom Plattform-Admin freigeschaltet -- wird von FB-050 aufgerufen. */
    case BUYER_APPROVED = 'buyer.approved';

    /** Kaeufer vom Plattform-Admin abgelehnt -- wird von FB-050 aufgerufen. */
    case BUYER_REJECTED = 'buyer.rejected';

    /** Bestaetigung einer Rufnummer bei Twilio angefordert (FB-080). */
    case CALLER_ID_REQUESTED = 'caller_id.requested';

    /** Rufnummer von Twilio bestaetigt und als Rufnummernanzeige zugelassen (FB-080). */
    case CALLER_ID_VERIFIED = 'caller_id.verified';

    /**
     * Deutsche Beschriftung fuer die Anzeige im Admin-Panel.
     */
    public function label(): string
    {
        return __('funnel.audit.actions.'.$this->translationKey());
    }

    /**
     * Schluessel der Beschriftung in lang/<sprache>/funnel.php.
     */
    public function translationKey(): string
    {
        return str_replace('.', '_', $this->value);
    }

    /**
     * Beschriftungen aller Aktionen, z. B. fuer Auswahlfilter.
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
