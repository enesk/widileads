<?php

declare(strict_types=1);

namespace App\Events\Call;

use App\Models\CallAttempt;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Ein Anrufversuch ist abgeschlossen und bewertet (FB-082/FB-083).
 *
 * Ausgeloest in App\Services\CallService::recordDialResult(), nachdem Dauer,
 * Bewertung und die Entscheidung ueber die Erreichbarkeit festgeschrieben
 * sind. Genau einmal je Versuch: Der Rueckruf steigt bei gesetztem
 * `dial_status` aus, eine zweite Zustellung derselben Twilio-Meldung loest
 * also nichts aus.
 *
 * Der Versuch kann auch ohne Gespraech enden (Lead hat nicht abgenommen,
 * Kaeufer hat aufgelegt). Zuhoerer, die ein echtes Gespraech brauchen, lesen
 * `duration_seconds` -- bewertet wird ausschliesslich im AttemptClassifier.
 *
 * Nachgetragen fuer LP-ONBOARD-012: Die Aktivierungskarte hakt daran
 * `first_call` ab, statt die Versuche zu pollen.
 */
class CallAttemptCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public CallAttempt $attempt) {}
}
