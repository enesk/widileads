<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Constants\PaymentMethodStatus;
use App\Constants\SettlementStatus;
use App\Events\Postpaid\PostpaidDowngradeRequested;
use App\Http\Controllers\Controller;
use App\Models\Settlement;
use App\Models\StripeWebhookEvent;
use App\Services\Payments\PaymentMethodService;
use App\Services\Wallet\SettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;

/**
 * Meldungen von Stripe zu hinterlegten Zahlungsmitteln (LP-POSTPAID-005).
 *
 * Eigener Endpunkt neben dem vorhandenen SaaSykit-Webhook: Dieser hier
 * entscheidet ueber die Zahlungsfaehigkeit eines Postpaid-Kaeufers, jener ueber
 * Abonnements und Bestellungen. Zwei Ereignismengen, zwei Zustaendigkeiten --
 * und im Stripe-Dashboard zwei Endpunkte, die man getrennt abschalten kann,
 * ohne die Aufladungen mitzunehmen. Das Secret darf dasselbe sein
 * (config('wallet.postpaid.webhook_signing_secret') faellt darauf zurueck).
 *
 * Vier Ereignisse zaehlen hier:
 *
 * - `payment_method.detached` -- das Zahlungsmittel ist beim Kunden abgehaengt,
 *   etwa von Hand im Stripe-Dashboard. Es ist damit nicht mehr einziehbar.
 * - `mandate.updated` mit Status `inactive` -- das SEPA-Mandat ist widerrufen,
 *   durch den Kaeufer oder durch seine Bank. Die Lastschrift waere ab sofort
 *   unberechtigt.
 * - `payment_intent.succeeded` -- ein Postpaid-Einzug ist eingegangen
 *   (LP-POSTPAID-008). Erst hier wird gutgeschrieben: Bei SEPA liegen
 *   zwischen Anstossen und Eingang bis zu 14 Tage.
 * - `payment_intent.payment_failed` -- der Einzug ist gescheitert. Der erste
 *   Fehlschlag setzt den zweiten Versuch an, der zweite fuehrt zur
 *   Rueckstufung (LP-POSTPAID-009). Meldet Stripe den Fehlschlag zu einem
 *   bereits gutgeschriebenen Einzug, ist es keine Ablehnung, sondern eine
 *   Ruecklastschrift: Das Geld war da und ist wieder weg.
 * - `charge.dispute.created` -- der Kaeufer hat eine Kartenbelastung bei seiner
 *   Bank angefochten (LP-POSTPAID-009). Fachlich dasselbe wie die
 *   Ruecklastschrift, nur ueber den Kartenweg.
 *
 * Fremde PaymentIntents -- Abonnements, Einmalkaeufe, Aufladungen -- laufen
 * durch dieselben zwei Ereignisse. Zugeordnet wird ausschliesslich ueber
 * `metadata.settlement_id`, das der SettlementService beim Anstossen setzt;
 * ohne diese Angabe geschieht hier nichts.
 *
 * War es das Standardmittel eines Postpaid-Wallets, ist die Grundlage des
 * Verfahrens entfallen; der Dienst stoesst dann die Rueckstufung an
 * (LP-POSTPAID-009). Der Webhook selbst stuft nicht zurueck: Er stellt nur
 * fest, was passiert ist.
 *
 * Die Antwort ist immer 200, sobald Signatur und Rumpf stimmen -- auch fuer
 * Ereignisse, die hier niemanden interessieren. Ein 4xx auf ein fremdes
 * Ereignis laesst Stripe den Endpunkt als kaputt melden und stundenlang
 * wiederholen.
 */
class StripePostpaidWebhookController extends Controller
{
    public function __construct(
        private PaymentMethodService $paymentMethods,
        private SettlementService $settlements,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $event = $this->buildEvent($request);
        } catch (SignatureVerificationException $exception) {
            Log::warning('Postpaid-Webhook: Signatur nicht gueltig.', [
                'reason' => $exception->getMessage(),
                'signing_secret_configured' => filled($this->signingSecret()),
            ]);

            return response()->json(['message' => 'Invalid signature'], 400);
        } catch (Throwable $exception) {
            Log::warning('Postpaid-Webhook: Rumpf nicht lesbar.', [
                'reason' => $exception->getMessage(),
                'content_length' => strlen((string) $request->getContent()),
            ]);

            return response()->json(['message' => 'Invalid payload'], 400);
        }

        // Idempotenz vor der Verarbeitung: Eine zweite Zustellung desselben
        // Ereignisses darf die Rueckstufung nicht ein zweites Mal anstossen.
        if (! StripeWebhookEvent::claim((string) $event->id, (string) $event->type)) {
            return response()->json(['message' => 'Already processed']);
        }

        match ((string) $event->type) {
            'payment_method.detached' => $this->handleDetached($event),
            'mandate.updated' => $this->handleMandateUpdated($event),
            'payment_intent.succeeded' => $this->handleIntentSucceeded($event),
            'payment_intent.payment_failed' => $this->handleIntentFailed($event),
            'charge.dispute.created' => $this->handleDisputeCreated($event),
            default => null,
        };

        return response()->json(['message' => 'ok']);
    }

    /**
     * Das Zahlungsmittel ist beim Kunden abgehaengt und damit nicht mehr
     * einziehbar.
     */
    private function handleDetached(Event $event): void
    {
        $providerId = (string) ($event->data->object->id ?? '');

        $method = $providerId !== '' ? $this->paymentMethods->findByProviderId($providerId) : null;

        if ($method === null) {
            // Das Konto wird auch fuer Abonnements und Einmalkaeufe genutzt.
            // Ein unbekanntes Zahlungsmittel ist hier der Regelfall und kein
            // Fehler.
            return;
        }

        if (! $method->status->isUsable()) {
            return;
        }

        $this->paymentMethods->markRevokedByProvider($method, PaymentMethodStatus::REVOKED);

        Log::info('Postpaid: Zahlungsmittel bei Stripe abgehaengt, Zeile stillgelegt.', [
            'payment_method_id' => $method->getKey(),
            'wallet_id' => $method->wallet_id,
        ]);
    }

    /**
     * Das SEPA-Mandat wurde widerrufen.
     *
     * Stripe meldet jede Aenderung eines Mandats mit diesem Ereignis; erst der
     * Status `inactive` bedeutet den Widerruf. Alles andere -- etwa die erste
     * Aktivierung -- geht hier absichtlich durch, ohne etwas zu tun.
     */
    private function handleMandateUpdated(Event $event): void
    {
        $status = (string) ($event->data->object->status ?? '');

        if ($status !== 'inactive') {
            return;
        }

        $mandateId = (string) ($event->data->object->id ?? '');

        $method = $mandateId !== '' ? $this->paymentMethods->findByMandateId($mandateId) : null;

        if ($method === null || ! $method->status->isUsable()) {
            return;
        }

        $this->paymentMethods->markRevokedByProvider($method, PaymentMethodStatus::REVOKED);

        Log::info('Postpaid: SEPA-Mandat widerrufen, Zahlungsmittel stillgelegt.', [
            'payment_method_id' => $method->getKey(),
            'wallet_id' => $method->wallet_id,
            'mandate_id' => $mandateId,
        ]);
    }

    /**
     * Ein Postpaid-Einzug ist eingegangen.
     *
     * Gebucht wird im SettlementService unter Sperre und mit festem
     * Idempotenzschluessel. Die Ereignissperre oben und der Schluessel im
     * Ledger sichern denselben Fall doppelt ab -- bei Geld ist das der
     * richtige Aufwand.
     */
    private function handleIntentSucceeded(Event $event): void
    {
        $settlement = $this->settlementOf($event);

        if ($settlement === null) {
            return;
        }

        $this->settlements->markPaid($settlement, (string) ($event->data->object->id ?? ''));
    }

    /**
     * Der Einzug ist gescheitert. Den Grund nennt Stripe im letzten
     * Fehlschlag; steht dort nichts, bleibt es beim Ereignisnamen -- ein
     * erfundener Grund waere schlechter als ein unspezifischer.
     *
     * Zwei Faelle unter einem Ereignisnamen: Wurde der Einzug noch nicht
     * gutgeschrieben, ist es eine Ablehnung und es folgt der zweite Versuch.
     * War er bereits gutgeschrieben, hat die Bank die Lastschrift
     * zurueckgegeben -- dann wird zurueckgebucht, eine Gebuehr erhoben und
     * zurueckgestuft (LP-POSTPAID-009). Bei SEPA liegen zwischen beidem bis zu
     * 14 Tage, die Unterscheidung am Zustand ist also die einzige verlaessliche.
     */
    private function handleIntentFailed(Event $event): void
    {
        $settlement = $this->settlementOf($event);

        if ($settlement === null) {
            return;
        }

        $error = $event->data->object->last_payment_error ?? null;

        $reason = (string) ($error->code ?? $error->message ?? 'payment_failed');

        if ($settlement->status === SettlementStatus::PAID) {
            $this->settlements->markReturned(
                $settlement,
                PostpaidDowngradeRequested::REASON_SEPA_RETURN,
                (string) ($event->data->object->id ?? ''),
            );

            return;
        }

        $this->settlements->markFailed($settlement, $reason);
    }

    /**
     * Der Kaeufer hat eine Kartenbelastung angefochten.
     *
     * Zugeordnet wird ueber die Kennung des PaymentIntent, nicht ueber
     * Metadaten: Ein Dispute traegt die Metadaten des Charge, und die setzen
     * wir dort nicht. Gehoert der Intent zu keinem Einzug, war es eine
     * Aufladung oder ein Abonnement -- dafuer ist dieser Endpunkt nicht
     * zustaendig.
     *
     * Widersprochen wird dem Dispute hier nicht. Ob der Betreiber Beweise
     * einreicht, entscheidet er im Stripe-Dashboard; die Forderung besteht
     * unabhaengig davon weiter und steht nach der Rueckbuchung wieder offen.
     */
    private function handleDisputeCreated(Event $event): void
    {
        $intentId = (string) ($event->data->object->payment_intent ?? '');

        if ($intentId === '') {
            return;
        }

        $settlement = Settlement::query()
            ->where('provider_payment_intent_id', $intentId)
            ->first();

        if (! $settlement instanceof Settlement) {
            return;
        }

        $this->settlements->markReturned(
            $settlement,
            PostpaidDowngradeRequested::REASON_CHARGEBACK,
            $intentId,
        );
    }

    /**
     * Das Settlement hinter einem PaymentIntent, oder null.
     *
     * Gelesen wird `metadata.settlement_id` und nicht die Kennung des Intents:
     * Der zweite Einzugsversuch derselben Forderung ist ein anderer Intent,
     * und ein Abgleich ueber die gespeicherte Kennung wuerde den alten
     * Fehlschlag dem neuen Versuch zuordnen.
     */
    private function settlementOf(Event $event): ?Settlement
    {
        $settlementId = $event->data->object->metadata->settlement_id ?? null;

        if ($settlementId === null || $settlementId === '') {
            // Dasselbe Stripe-Konto bedient auch Abonnements, Einmalkaeufe und
            // Aufladungen. Ein fremder PaymentIntent ist der Regelfall.
            return null;
        }

        $settlement = Settlement::query()->find((int) $settlementId);

        if (! $settlement instanceof Settlement) {
            Log::warning('Postpaid-Webhook: Einzug zu unbekannter Kennung gemeldet.', [
                'settlement_id' => $settlementId,
                'event_type' => (string) $event->type,
            ]);
        }

        return $settlement;
    }

    /**
     * Prueft die Signatur und gibt das Ereignis zurueck.
     *
     * Ohne Signaturpruefung koennte jeder eine Rueckstufung ausloesen oder ein
     * Zahlungsmittel stilllegen -- die Adresse ist oeffentlich erreichbar und
     * traegt kein Geheimnis.
     */
    private function buildEvent(Request $request): Event
    {
        return Webhook::constructEvent(
            $request->getContent(),
            (string) $request->header('Stripe-Signature'),
            $this->signingSecret(),
        );
    }

    private function signingSecret(): string
    {
        return (string) (config('wallet.postpaid.webhook_signing_secret')
            ?: config('services.stripe.webhook_signing_secret'));
    }
}
