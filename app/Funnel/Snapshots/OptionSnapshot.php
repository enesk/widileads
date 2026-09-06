<?php

declare(strict_types=1);

namespace App\Funnel\Snapshots;

/**
 * Eine Antwortoption aus dem Funnel-Snapshot (FB-013).
 *
 * `score` traegt die Punkte, mit denen die Option in die Auswertung eingeht;
 * ohne Punktwert bleibt sie neutral.
 */
class OptionSnapshot
{
    public function __construct(
        public readonly string $value,
        public readonly string $label,
        public readonly ?int $score,
        public readonly int $position,
        public readonly ?string $imagePath,
    ) {}

    /**
     * @param  array<string, mixed>  $option
     */
    public static function fromArray(array $option): self
    {
        return new self(
            value: (string) ($option['value'] ?? ''),
            label: (string) ($option['label'] ?? ''),
            score: isset($option['score']) ? (int) $option['score'] : null,
            position: (int) ($option['position'] ?? 0),
            imagePath: isset($option['image_path']) ? (string) $option['image_path'] : null,
        );
    }

    public function points(): int
    {
        return $this->score ?? 0;
    }

    /**
     * Entspricht eine gegebene Antwort dieser Option? Verglichen wird tolerant,
     * weil der Browser alles als String liefert und der Funnel-Ersteller die
     * Werte von Hand pflegt.
     */
    public function matches(mixed $answer): bool
    {
        if (! is_scalar($answer)) {
            return false;
        }

        return mb_strtolower(trim((string) $answer)) === mb_strtolower(trim($this->value));
    }
}
