<?php

declare(strict_types=1);

namespace App\Http\Controllers\Twilio;

use App\Http\Controllers\Controller;
use App\Models\CallAttempt;
use App\Services\CallService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Ergebnis der Anrufbeantworter-Erkennung (FB-082).
 *
 * Wird mitgeschrieben, nicht gedeutet: Ob eine Mailbox als erreicht zaehlt, ist
 * eine Geldfrage und in Ticket #12 noch offen.
 */
class CallMachineDetectionController extends Controller
{
    public function __invoke(Request $request, CallAttempt $attempt, CallService $calls): Response
    {
        $calls->recordMachineDetection($attempt, $request->post());

        return response()->noContent();
    }
}
