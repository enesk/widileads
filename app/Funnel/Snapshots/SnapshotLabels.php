<?php

declare(strict_types=1);

namespace App\Funnel\Snapshots;

/**
 * Beschriftungen aus einem Funnel-Snapshot.
 *
 * Gespeichert wird der technische Schluessel einer Frage ("rasse_groesse") und
 * der Wert einer Option ("gross"). Gelesen werden soll, was der Kunde gesehen
 * und angeklickt hat. Beides steht im Snapshot der Fassung, unter der der Lead
 * entstanden ist -- nicht im Entwurf: Eine spaeter umformulierte Frage darf
 * einen alten Lead nicht nachtraeglich umdeuten.
 *
 * Die Klasse liest nur den Snapshot. Sie fasst keine Antworten an, damit die
 * Maskierung der Kontaktfelder eine Sache des Aufrufers bleibt.
 */
final class SnapshotLabels
{
    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, string> field_key => Beschriftung der Frage
     */
    public static function questions(?array $snapshot): array
    {
        $labels = [];

        foreach (self::questionsOf($snapshot) as $question) {
            $fieldKey = $question['field_key'] ?? null;
            $label = $question['label'] ?? null;

            if (is_string($fieldKey) && is_string($label) && $label !== '') {
                $labels[$fieldKey] = $label;
            }
        }

        return $labels;
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, array<string, string>> field_key => [Wert => Beschriftung]
     */
    public static function options(?array $snapshot): array
    {
        $labels = [];

        foreach (self::questionsOf($snapshot) as $question) {
            $fieldKey = $question['field_key'] ?? null;

            if (! is_string($fieldKey)) {
                continue;
            }

            foreach ($question['options'] ?? [] as $option) {
                $value = $option['value'] ?? null;
                $label = $option['label'] ?? null;

                if (is_scalar($value) && is_string($label) && $label !== '') {
                    $labels[$fieldKey][(string) $value] = $label;
                }
            }
        }

        return $labels;
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return list<array<string, mixed>>
     */
    private static function questionsOf(?array $snapshot): array
    {
        $questions = [];

        foreach ($snapshot['steps'] ?? [] as $step) {
            foreach ($step['questions'] ?? [] as $question) {
                if (is_array($question)) {
                    $questions[] = $question;
                }
            }
        }

        return $questions;
    }
}
