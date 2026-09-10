<?php

declare(strict_types=1);

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CallNotPossible;
use App\Services\CallService;
use App\Services\Twilio\OutboundCallFailed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Click-to-Call aus dem Kaeufer-Portal (FB-081).
 *
 * Der Endpunkt stoesst nur an; ob der Anruf zulaessig ist, entscheidet
 * ausschliesslich der CallService. Angesprochen wird der Lead ueber seine
 * oeffentliche Kennung -- eine fortlaufende Nummer waere hier eine Einladung,
 * fremde Leads durchzuprobieren.
 */
class LeadCallController extends Controller
{
    public function __invoke(Request $request, string $lead, CallService $calls): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $purchase = $this->purchaseFor($user, $lead);
        $tenant = $purchase->buyer;

        if (! $tenant instanceof Tenant) {
            abort(Response::HTTP_NOT_FOUND);
        }

        try {
            $attempt = $calls->start($purchase, $user, $tenant);
        } catch (CallNotPossible $exception) {
            return response()->json([
                'message' => $exception->translated(),
                'reason' => $exception->reason(),
            ], $exception->status());
        } catch (OutboundCallFailed $exception) {
            // Die Twilio-Meldung nennt Kontodetails und gehoert ins Log.
            logger()->error('Twilio konnte den Anruf nicht starten.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => __('call.attempt.errors.provider_failed'),
                'reason' => 'call.attempt.errors.provider_failed',
            ], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'attempt' => $attempt->uuid,
            'status' => $attempt->status->value,
            'message' => __('call.attempt.started'),
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Der Kaufbeleg des Anrufenden zu diesem Lead.
     *
     * Ein Lead, den der Benutzer nicht gekauft hat, ist hier nicht "verboten",
     * sondern schlicht nicht vorhanden -- sonst liesse sich an der Antwort
     * ablesen, welche Leads es gibt. Gehoert der Benutzer zu mehreren
     * Workspaces, zaehlt der juengste Kauf: Aus Sicht des Anrufers ist das der
     * Lead, den er gerade vor sich hat.
     */
    private function purchaseFor(User $user, string $leadUuid): LeadPurchase
    {
        $purchase = LeadPurchase::query()
            ->with('lead')
            ->whereIn('buyer_tenant_id', $user->tenants()->select('tenants.id'))
            ->whereHas('lead', static fn ($query) => $query->withoutGlobalScopes()->where('uuid', $leadUuid))
            ->latest('purchased_at')
            ->first();

        abort_unless($purchase instanceof LeadPurchase, Response::HTTP_NOT_FOUND);

        return $purchase;
    }
}
