<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Erreichbarkeitspruefung per Click-to-Call (FB-082)
    |--------------------------------------------------------------------------
    |
    | Saemtliche Schwellwerte des Regelwerks "Lead erreichbar / nicht
    | erreichbar". AttemptClassifier, LeadResolver, Scheduler und Portal lesen
    | ausschliesslich aus config('lead_calls.*') -- kein Wert dieser Liste darf
    | irgendwo im Code hartkodiert stehen.
    |
    */

    // Ab dieser Gespraechsdauer (Sekunden) gilt ein Versuch als angenommen.
    // Kuerzere Verbindungen zaehlen als nicht erreicht.
    'answered_min_seconds' => (int) env('LEAD_CALLS_ANSWERED_MIN_SECONDS', 30),

    // Mindestabstand (Stunden) zwischen zwei Versuchen desselben Kaeufers.
    // Frueher ausgeloeste Anrufe zaehlen nicht als eigener Versuch.
    'retry_min_hours' => (int) env('LEAD_CALLS_RETRY_MIN_HOURS', 2),

    // So viele erfolglose Versuche braucht es mindestens, bevor ein Lead als
    // nicht erreichbar gelten kann.
    'unreachable_attempts' => (int) env('LEAD_CALLS_UNREACHABLE_ATTEMPTS', 3),

    // ... und die Versuche muessen sich ueber mindestens so viele Tage
    // erstrecken. Beide Bedingungen gelten zusammen.
    'unreachable_min_days' => (int) env('LEAD_CALLS_UNREACHABLE_MIN_DAYS', 2),

    // Frist (Tage ab delivered_at), innerhalb derer der Kaeufer den Lead
    // erreichen muss. Danach entscheidet der Scheduler.
    'deadline_days' => (int) env('LEAD_CALLS_DEADLINE_DAYS', 7),

    // Klingeldauer (Sekunden) des Kaeufer-Legs, bevor Twilio aufgibt.
    'buyer_ring_timeout' => (int) env('LEAD_CALLS_BUYER_RING_TIMEOUT', 25),

    // Klingeldauer (Sekunden) des Lead-Legs im Dial-Verb.
    'lead_ring_timeout' => (int) env('LEAD_CALLS_LEAD_RING_TIMEOUT', 30),

    // Twilio Answering Machine Detection. "DetectMessageEnd" wartet den
    // Ansagetext des Anrufbeantworters ab, statt nur zu klassifizieren.
    'machine_detection' => env('LEAD_CALLS_MACHINE_DETECTION', 'DetectMessageEnd'),

    // Ab wie vielen Prozentpunkten ueber dem Durchschnitt aller Kaeufer die
    // Erreichbarkeits-Uebersicht (FB-085) einen Kaeufer hervorhebt. Reine
    // Anzeige -- am Regelwerk aendert der Wert nichts.
    'unreachable_rate_flag_points' => (int) env('LEAD_CALLS_UNREACHABLE_RATE_FLAG_POINTS', 20),

    // Cutover fuer Bestandsleads: Nur Leads mit delivered_at ab diesem
    // Zeitpunkt unterliegen dem Regelwerk. null = keine Einschraenkung,
    // alle Leads sind erfasst. Format: ein von Carbon lesbares Datum,
    // z. B. "2026-10-01" oder "2026-10-01 00:00:00".
    'enforce_from' => env('LEAD_CALLS_ENFORCE_FROM'),

];
