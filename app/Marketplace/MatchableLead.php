<?php

declare(strict_types=1);

namespace App\Marketplace;

/**
 * Die Eigenschaften eines Leads, an denen ein Kaufkriterium ansetzt (FB-051).
 *
 * Bewusst nicht das Lead-Modell: `leads` traegt heute nur Zustand und
 * Abrechnung. Funnelbezug, Punktzahl, Postleitzahl und Antworten legt erst
 * FB-031 an. Gegen diese schmale Struktur laesst sich der Matcher schon jetzt
 * vollstaendig pruefen, und FB-031 muss spaeter nur die Abbildung
 * Lead -> MatchableLead liefern, ohne den Matcher anzufassen.
 *
 * Die Uebergabestelle, die FB-031 zu bedienen hat:
 *
 *   funnelId    `leads.funnel_id`
 *   score       `leads.score` -- null, solange keine Punktzahl ermittelt wurde
 *   postalCode  die Antwort auf den reservierten Feldschluessel `plz`,
 *               im Klartext (nicht die maskierte Fassung aus FB-032 -- gefiltert
 *               wird serverseitig auf den echten Werten)
 *   answers     `lead_answers` als flaches Array `field_key => Wert`, genau wie
 *               das Antwortformat der oeffentlichen Strecke
 *               (docs/funnel-builder/snapshot-format.md, Abschnitt "Antworten").
 *               Mehrfachauswahlen stehen als Liste.
 *
 * Sinnvollerweise entsteht sie als `MatchableLead::fromLead(Lead $lead)` neben
 * dem Lead-Modell, sobald dessen Spalten existieren.
 */
final class MatchableLead
{
    /**
     * @param  array<string, mixed>  $answers  field_key => Wert; Mehrfachauswahl als Liste
     */
    public function __construct(
        public readonly ?int $funnelId = null,
        public readonly ?int $score = null,
        public readonly ?string $postalCode = null,
        public readonly array $answers = [],
    ) {}

    /**
     * @param  array{funnel_id?: int|string|null, score?: int|string|null, postal_code?: string|null, answers?: array<string, mixed>}  $lead
     */
    public static function fromArray(array $lead): self
    {
        $postalCode = $lead['postal_code'] ?? null;

        return new self(
            funnelId: isset($lead['funnel_id']) ? (int) $lead['funnel_id'] : null,
            score: isset($lead['score']) ? (int) $lead['score'] : null,
            postalCode: $postalCode === null ? null : trim((string) $postalCode),
            answers: (array) ($lead['answers'] ?? []),
        );
    }

    /**
     * Die Antwort auf einen Feldschluessel als Liste -- eine Mehrfachauswahl
     * traegt mehrere Werte, eine Einfachauswahl genau einen.
     *
     * @return list<mixed>
     */
    public function answersFor(string $fieldKey): array
    {
        if (! array_key_exists($fieldKey, $this->answers)) {
            return [];
        }

        $answer = $this->answers[$fieldKey];

        return is_array($answer) ? array_values($answer) : [$answer];
    }
}
