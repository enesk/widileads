<?php

declare(strict_types=1);

namespace App\Listeners\Webhooks;

use App\Constants\WebhookEvent;
use App\Events\Lead\LeadCreated;
use App\Events\Lead\LeadPurchased;
use App\Events\Lead\LeadStateChanged;
use App\Models\Lead;
use App\Services\LeadContactResolver;
use App\Services\WebhookDispatcher;

/**
 * Meldet Lead-Ereignisse an die Webhooks des Funnels (FB-030e).
 *
 * Kontaktdaten gehen ausschliesslich an Webhooks des Eigentuemer-Workspaces --
 * das sind heute alle, denn Webhooks haengen am Funnel, und der gehoert dem
 * Betreiber. Ein Kaeufer bekommt seine Leads nicht ueber diesen Weg, sondern
 * nach dem Kauf ueber FB-054. Damit die Regel auch dann noch stimmt, wenn es
 * einmal Kaeufer-Webhooks gibt, entscheidet nicht dieser Listener ueber den
 * Klartext, sondern der LeadContactResolver -- aus Sicht des Workspaces, dem
 * der Funnel gehoert.
 */
class DispatchLeadWebhooks
{
    public function __construct(
        private readonly WebhookDispatcher $dispatcher,
        private readonly LeadContactResolver $contacts,
    ) {}

    public function handleLeadCreated(LeadCreated $event): void
    {
        $this->send($event->lead, WebhookEvent::LEAD_CREATED, [
            'lead' => $this->leadPayload($event->lead),
        ]);
    }

    public function handleLeadStateChanged(LeadStateChanged $event): void
    {
        $this->send($event->lead, WebhookEvent::LEAD_STATE_CHANGED, [
            'lead' => $this->leadPayload($event->lead),
            'from' => $event->from->value,
            'to' => $event->to->value,
            'reason' => $event->reason->value,
        ]);
    }

    public function handleLeadPurchased(LeadPurchased $event): void
    {
        $this->send($event->lead, WebhookEvent::LEAD_PURCHASED, [
            'lead' => $this->leadPayload($event->lead),
            'buyer_uuid' => $event->buyer->uuid,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function send(Lead $lead, WebhookEvent $event, array $data): void
    {
        $funnel = $lead->funnel;

        if ($funnel === null) {
            return;
        }

        $this->dispatcher->dispatch($funnel, $event, $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function leadPayload(Lead $lead): array
    {
        // Wer den Klartext sieht, entscheidet der Resolver -- nicht diese
        // Stelle. Gefragt wird aus Sicht des Workspaces, dem der Funnel
        // gehoert: Beim Webhook gibt es keinen Betrachter als Benutzer, der
        // Empfaenger ist eine URL, und ein API-Token gehoert einem Tenant
        // (FB-006). Genau dafuer gibt es forTenant() seit FB-030d.
        //
        // Heute sind das immer die Webhooks des Eigentuemers, weil sie am
        // Funnel haengen -- der Klartext bleibt also derselbe wie zuvor. Sollte
        // es einmal Kaeufer-Webhooks geben, maskiert der Resolver von sich aus,
        // ohne dass hier etwas nachgezogen werden muesste.
        $contact = $this->contacts->forTenant($lead, $lead->funnel?->tenant);

        return [
            'id' => $lead->getKey(),
            'state' => $lead->lead_state->value,
            'score' => $lead->score,
            'result_key' => $lead->result_key,
            'created_at' => $lead->created_at?->toIso8601String(),
            'contact' => $contact->toArray(),
        ];
    }
}
