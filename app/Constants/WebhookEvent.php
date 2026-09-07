<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Ereignisse, die ein Funnel nach aussen meldet (FB-030e).
 *
 * Die Werte stehen im Header `X-Funnel-Event` und im Rahmen jedes Aufrufs. Sie
 * sind Teil des Vertrags mit dem Empfaenger und aendern sich nicht.
 */
enum WebhookEvent: string
{
    /** Aus einer abgeschlossenen Sitzung ist ein Lead entstanden (Zustand neu). */
    case LEAD_CREATED = 'lead.created';

    /** Ein Lead hat seinen Zustand gewechselt. */
    case LEAD_STATE_CHANGED = 'lead.state_changed';

    /** Ein Kaeufer hat einen Lead gekauft. */
    case LEAD_PURCHASED = 'lead.purchased';

    /** Eine neue Fassung eines Funnels wurde veroeffentlicht. */
    case FUNNEL_PUBLISHED = 'funnel.published';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
