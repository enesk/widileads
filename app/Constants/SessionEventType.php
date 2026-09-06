<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Ereignisse einer oeffentlichen Funnel-Sitzung (FB-021).
 *
 * Die Abfolge ist die Quelle fuer Abbruchquoten je Schritt: Wo bricht der
 * Endkunde ab, welcher Schritt kostet die meisten Anfragen.
 */
enum SessionEventType: string
{
    /** Die Strecke wurde aufgerufen. */
    case VIEW = 'view';

    /** Ein Schritt wurde angezeigt. */
    case STEP_VIEW = 'step_view';

    /** Ein Schritt wurde gueltig abgeschlossen. */
    case STEP_COMPLETE = 'step_complete';

    /** Die Anfrage wurde abgesendet. */
    case SUBMIT = 'submit';

    /** Die Sitzung wurde nach Inaktivitaet als abgebrochen markiert. */
    case ABANDON = 'abandon';
}
