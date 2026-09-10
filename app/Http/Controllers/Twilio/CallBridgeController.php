<?php

declare(strict_types=1);

namespace App\Http\Controllers\Twilio;

use App\Http\Controllers\Controller;
use App\Models\CallAttempt;
use App\Services\CallService;
use Illuminate\Http\Response;

/**
 * Die Anweisung, was nach dem Abnehmen geschehen soll (FB-081).
 *
 * Twilio ruft diese Adresse ab, sobald der Kaeufer das Gespraech annimmt, und
 * stellt daraufhin zum Lead durch. Erreichbar nur mit gueltiger
 * Twilio-Signatur -- die Antwort enthaelt die Rufnummer des Leads, und sie ist
 * die einzige Stelle, an der diese Nummer das Portal verlaesst.
 */
class CallBridgeController extends Controller
{
    public function __invoke(CallAttempt $attempt, CallService $calls): Response
    {
        return response(
            (string) $calls->bridgeInstruction($attempt),
            Response::HTTP_OK,
        )->header('Content-Type', 'text/xml');
    }
}
