<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Constants\LeadState;
use App\Funnel\Snapshots\FunnelSnapshot;
use App\Funnel\Snapshots\QuestionSnapshot;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ein Lead in der API (FB-030d).
 *
 * Ausgeliefert wird genau eine von zwei Auspraegungen der Spezifikation:
 * `LeadMasked` oder `LeadFull`, erkennbar am Feld `contact_visibility`. Welche
 * es ist, entscheidet der Server je Lead -- nicht der Client und nicht ein
 * Parameter.
 *
 * Diese Klasse trifft die Entscheidung nicht und kennt die Maskierregeln nicht:
 * Sie fragt `Lead::contactFor()` und gibt aus, was zurueckkommt (FB-032,
 * Architekturleitsatz 5).
 *
 * @mixin Lead
 */
class LeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Lead $lead */
        $lead = $this->resource;

        // Ueber die API fragt das Token eines Workspaces, kein Benutzer
        // (FB-006: der Tenant ist Token-Traeger).
        $viewer = $request->user();
        $contact = $viewer instanceof Tenant
            ? $lead->contactForTenant($viewer)
            : $lead->contactFor($viewer instanceof User ? $viewer : null);

        $payload = [
            'uuid' => $lead->uuid,
            'lead_state' => $lead->lead_state->value,
            'settled_price' => $this->money($lead->settled_price),
            'settled_at' => $lead->settled_at?->toIso8601String(),
            'anonymized_at' => $lead->anonymized_at?->toIso8601String(),
            'funnel' => $lead->funnel === null ? null : [
                'public_token' => $lead->funnel->public_token,
                'name' => $lead->funnel->name,
            ],
            'funnel_version' => $lead->funnelVersion === null ? null : [
                'version' => $lead->funnelVersion->version,
                'note' => null,
                'published_at' => $lead->funnelVersion->published_at?->toIso8601String(),
                'published_by' => $lead->funnelVersion->publisher?->name,
            ],
            'score' => $lead->score,
            'result' => $this->result($lead),
            'price_at_creation' => $this->money($lead->price_at_creation),
            'origin' => $lead->embed_origin,
            'utm' => [
                'source' => $lead->utm_source,
                'medium' => $lead->utm_medium,
                'campaign' => $lead->utm_campaign,
                'term' => $lead->utm_term,
                'content' => $lead->utm_content,
            ],
            'answers' => $this->answers($lead),
            'created_at' => $lead->created_at?->toIso8601String(),
            'contact_visibility' => $contact->masked ? 'masked' : 'full',
            'contact' => [
                'first_name' => $contact->firstName,
                'last_name' => $contact->lastName,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'postal_code' => $contact->postalCode,
            ],
        ];

        // LeadFull traegt zusaetzlich den Kaufzeitpunkt. Bis FB-054 den
        // Kaufvorgang baut, gibt es nur die Freigabe fuer den Eigentuemer --
        // dann steht dort der Zeitpunkt des Zustandswechsels nach `verkauft`.
        if (! $contact->masked) {
            $payload['purchased_at'] = $this->purchasedAt($lead);
        }

        return $payload;
    }

    /**
     * Der ausgespielte Ergebnis-Screen.
     *
     * Ausgegeben wird der Schluessel des Punktebereichs aus dem Snapshot, nicht
     * die ID einer Zeile aus `funnel_results`: Der Lead haengt an der
     * veroeffentlichten Fassung, und die darf sich nicht mit der Live-Zeile
     * aendern.
     *
     * @return array{key: string, title: string}|null
     */
    private function result(Lead $lead): ?array
    {
        if ($lead->result_key === null) {
            return null;
        }

        $snapshot = $lead->funnelVersion?->snapshot;

        if (! is_array($snapshot)) {
            return null;
        }

        foreach (FunnelSnapshot::fromArray($snapshot)->results as $result) {
            if ($result->key === $lead->result_key) {
                return ['key' => $result->key, 'title' => $result->title];
            }
        }

        return null;
    }

    /**
     * Die Qualifizierungsantworten -- ohne die reservierten Kontaktfelder.
     *
     * Sie erscheinen ausschliesslich unter `contact`, damit die Maskierung
     * nicht ueber die Rohantworten zu umgehen ist.
     *
     * @return list<array<string, mixed>>
     */
    private function answers(Lead $lead): array
    {
        $questions = $this->questionsByFieldKey($lead);

        return $lead->answers
            ->reject(static fn (LeadAnswer $answer): bool => $answer->isPersonal())
            ->map(function (LeadAnswer $answer) use ($questions): array {
                $question = $questions[$answer->field_key] ?? null;

                return [
                    'field_key' => $answer->field_key,
                    'label' => $question instanceof QuestionSnapshot ? $question->label : $answer->field_key,
                    'value' => $answer->value,
                    'value_label' => $this->valueLabel($question, $answer->value),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Die Fragen der Fassung, aus der dieser Lead stammt -- nach Feldschluessel.
     *
     * Gelesen wird der Snapshot, nicht die Live-Tabellen: Der Lead soll mit den
     * Beschriftungen erscheinen, die der Endkunde gesehen hat.
     *
     * @return array<string, QuestionSnapshot>
     */
    private function questionsByFieldKey(Lead $lead): array
    {
        $snapshot = $lead->funnelVersion?->snapshot;

        if (! is_array($snapshot)) {
            return [];
        }

        $questions = [];

        foreach (FunnelSnapshot::fromArray($snapshot)->questions() as $question) {
            $questions[$question->fieldKey] = $question;
        }

        return $questions;
    }

    private function valueLabel(?QuestionSnapshot $question, mixed $value): ?string
    {
        if ($question === null || $question->options === [] || $value === null) {
            return null;
        }

        $values = is_array($value) ? array_values($value) : [$value];
        $labels = [];

        foreach ($values as $given) {
            foreach ($question->options as $option) {
                if ($option->matches($given)) {
                    $labels[] = $option->label;

                    break;
                }
            }
        }

        return $labels === [] ? null : implode(', ', $labels);
    }

    /**
     * Betraege verlassen die API als ganzzahlige kleinste Waehrungseinheit,
     * nie als Gleitkommazahl.
     *
     * @return array{amount: int, currency: string, formatted: string}|null
     */
    private function money(?string $amount): ?array
    {
        if ($amount === null) {
            return null;
        }

        $currency = strtoupper((string) config('app.default_currency', 'EUR'));
        $cents = (int) round(((float) $amount) * 100);

        return [
            'amount' => $cents,
            'currency' => $currency,
            'formatted' => number_format($cents / 100, 2, ',', '.').' '.($currency === 'EUR' ? '€' : $currency),
        ];
    }

    private function purchasedAt(Lead $lead): ?string
    {
        return $lead->stateLog
            ->firstWhere('to_state', LeadState::VERKAUFT)
            ?->created_at?->toIso8601String();
    }
}
