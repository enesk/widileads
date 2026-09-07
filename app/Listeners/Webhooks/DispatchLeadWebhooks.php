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
 * einmal Kaeufer-Webhooks gibt, laeuft der Kontakt hier ueber den
 * LeadContactResolver statt ueber die Rohspalten.
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
        // Der Webhook gehoert dem Funnel und damit dem Eigentuemer-Workspace --
        // deshalb Klartext. Der Weg fuehrt ueber den Resolver, damit es genau
        // eine Stelle bleibt, die darueber entscheidet.
        //
        // Vorgesehen ist hier contactFor(); das braucht aber einen Betrachter
        // als User, und einen gibt es beim Webhook nicht -- der Empfaenger ist
        // eine URL, und das Token gehoert einem Tenant. Sobald der Resolver eine
        // Tenant-Variante hat (gemeldete Blockade zu FB-030d), gehoert hier
        // forTenant($lead, $funnel->tenant) hin. Bis dahin ist das Ergebnis
        // dasselbe, weil es strukturell keine Kaeufer-Webhooks gibt: Sie haengen
        // am Funnel, und der gehoert dem Betreiber (siehe Test).
        $contact = $this->contacts->internal($lead);

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
