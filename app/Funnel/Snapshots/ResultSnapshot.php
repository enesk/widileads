<?php

declare(strict_types=1);

namespace App\Funnel\Snapshots;

/**
 * Ein Ergebnis-Screen aus dem Funnel-Snapshot (FB-013).
 *
 * Der Bereich ist beidseitig einschliessend: min_score 4 und max_score 7 decken
 * die Punktzahlen 4, 5, 6 und 7 ab. `key` identifiziert das Ergebnis innerhalb
 * seiner Funnel-Version stabil (FB-014).
 */
class ResultSnapshot
{
    public function __construct(
        public readonly string $key,
        public readonly int $minScore,
        public readonly int $maxScore,
        public readonly string $title,
        public readonly ?string $body,
        public readonly ?string $ctaLabel,
        public readonly ?string $ctaUrl,
        public readonly bool $showContactForm,
    ) {}

    /**
     * @param  array<string, mixed>  $result
     */
    public static function fromArray(array $result): self
    {
        $minScore = (int) ($result['min_score'] ?? 0);
        $maxScore = (int) ($result['max_score'] ?? 0);

        return new self(
            key: (string) ($result['key'] ?? self::keyFor($minScore, $maxScore)),
            minScore: $minScore,
            maxScore: $maxScore,
            title: (string) ($result['title'] ?? ''),
            body: isset($result['body']) ? (string) $result['body'] : null,
            ctaLabel: isset($result['cta_label']) ? (string) $result['cta_label'] : null,
            ctaUrl: isset($result['cta_url']) ? (string) $result['cta_url'] : null,
            showContactForm: (bool) ($result['show_contact_form'] ?? true),
        );
    }

    /**
     * Stabiler Schluessel eines Ergebnisses innerhalb einer Funnel-Version.
     *
     * Abgeleitet aus dem Punktebereich, weil der innerhalb einer Version fest
     * ist und die Bereiche sich dank ResultRangeValidator nicht ueberschneiden.
     * Der Lead speichert diesen Schluessel zusammen mit funnel_version_id
     * (FB-031) -- nicht die ID aus funnel_results, denn die Live-Zeile darf
     * sich aendern, die veroeffentlichte Fassung nicht.
     */
    public static function keyFor(int $minScore, int $maxScore): string
    {
        return $minScore.'-'.$maxScore;
    }

    public function covers(int $score): bool
    {
        return $score >= $this->minScore && $score <= $this->maxScore;
    }

    public function range(): string
    {
        return self::keyFor($this->minScore, $this->maxScore);
    }
}
