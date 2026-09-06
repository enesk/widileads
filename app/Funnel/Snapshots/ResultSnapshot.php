<?php

declare(strict_types=1);

namespace App\Funnel\Snapshots;

/**
 * Ein Ergebnis-Screen aus dem Funnel-Snapshot (FB-013).
 *
 * Der Bereich ist beidseitig einschliessend: min_score 4 und max_score 7 decken
 * die Punktzahlen 4, 5, 6 und 7 ab.
 */
class ResultSnapshot
{
    public function __construct(
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
        return new self(
            minScore: (int) ($result['min_score'] ?? 0),
            maxScore: (int) ($result['max_score'] ?? 0),
            title: (string) ($result['title'] ?? ''),
            body: isset($result['body']) ? (string) $result['body'] : null,
            ctaLabel: isset($result['cta_label']) ? (string) $result['cta_label'] : null,
            ctaUrl: isset($result['cta_url']) ? (string) $result['cta_url'] : null,
            showContactForm: (bool) ($result['show_contact_form'] ?? true),
        );
    }

    public function covers(int $score): bool
    {
        return $score >= $this->minScore && $score <= $this->maxScore;
    }

    public function range(): string
    {
        return $this->minScore.'-'.$this->maxScore;
    }
}
