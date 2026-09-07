<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Funnel;
use App\Models\FunnelQuestion;
use App\Models\FunnelStep;
use Illuminate\Support\Facades\DB;

/**
 * Ersetzt die Struktur eines BESTEHENDEN Funnels (FB-030b).
 *
 * Abgrenzung zum FunnelStructureWriter (FB-018/FB-019): Der legt aus einer
 * Struktur einen NEUEN Funnel an -- Vorlage importieren, Funnel duplizieren.
 * Hier bleibt der Funnel samt Token, Slug und Zustand bestehen, nur sein
 * Aufbau wird ausgetauscht. Beide lesen dasselbe Format aus
 * docs/funnel-builder/snapshot-format.md.
 *
 * Ersetzen statt Zusammenfuehren: Was die Struktur nicht enthaelt, verschwindet.
 * Ein Zusammenfuehren muesste raten, ob ein fehlender Schritt geloescht oder nur
 * nicht mitgeschickt wurde -- und beides waere einmal falsch.
 *
 * Alles laeuft in einer Transaktion: Eine halb geschriebene Struktur waere
 * schlimmer als eine abgelehnte, weil der Funnel dann Fragen ohne Optionen oder
 * Regeln ohne Ziel enthielte.
 */
class FunnelStructureReplacer
{
    /**
     * @param  array<string, mixed>  $structure
     */
    public function replace(Funnel $funnel, array $structure): void
    {
        DB::transaction(function () use ($funnel, $structure): void {
            // Die Fremdschluessel kaskadieren: Mit den Schritten gehen Fragen
            // und Optionen, mit dem Funnel die Regeln und Ergebnisse.
            $funnel->steps()->each(fn (FunnelStep $step) => $step->delete());
            $funnel->conditions()->delete();
            $funnel->results()->delete();

            $questionsByFieldKey = [];
            $stepsByPosition = [];

            foreach ($structure['steps'] ?? [] as $stepData) {
                $step = $funnel->steps()->create([
                    'position' => $stepData['position'],
                    'title' => $stepData['title'],
                    'description' => $stepData['description'] ?? null,
                ]);

                $stepsByPosition[(int) $stepData['position']] = $step;

                foreach ($stepData['questions'] ?? [] as $index => $questionData) {
                    $question = $step->questions()->create([
                        'funnel_id' => $funnel->getKey(),
                        'position' => $questionData['position'] ?? $index + 1,
                        'type' => $questionData['type'],
                        'field_key' => $questionData['field_key'],
                        'label' => $questionData['label'],
                        'help_text' => $questionData['help_text'] ?? null,
                        'required' => $questionData['required'] ?? true,
                        'validation' => $questionData['validation'] ?? null,
                        'meta' => $questionData['meta'] ?? null,
                    ]);

                    // Der Feldschluessel wird beim Speichern vereinheitlicht
                    // (FB-010) -- gemerkt wird der Wert, der wirklich in der
                    // Datenbank steht, sonst finden die Regeln ihre Frage nicht.
                    $questionsByFieldKey[$question->field_key] = $question;

                    foreach ($questionData['options'] ?? [] as $optionIndex => $optionData) {
                        $question->options()->create([
                            'position' => $optionData['position'] ?? $optionIndex + 1,
                            'label' => $optionData['label'],
                            'value' => $optionData['value'],
                            'score' => $optionData['score'] ?? null,
                            'image_path' => $optionData['image_path'] ?? null,
                        ]);
                    }
                }
            }

            foreach ($structure['conditions'] ?? [] as $conditionData) {
                $question = $questionsByFieldKey[$conditionData['source_field_key']] ?? null;
                $targetStep = $stepsByPosition[(int) $conditionData['target_step_position']] ?? null;

                // Eine Regel ohne aufloesbare Frage oder ohne Zielschritt wuerde
                // die Strecke ins Leere schicken; sie wird uebergangen.
                if (! $question instanceof FunnelQuestion || ! $targetStep instanceof FunnelStep) {
                    continue;
                }

                $funnel->conditions()->create([
                    'source_question_id' => $question->getKey(),
                    'operator' => $conditionData['operator'],
                    'value' => $conditionData['value'] ?? null,
                    'target_step_id' => $targetStep->getKey(),
                    'evaluate_at_step_position' => $conditionData['evaluate_at_step_position'] ?? null,
                    'priority' => $conditionData['priority'] ?? 0,
                ]);
            }

            foreach ($structure['results'] ?? [] as $resultData) {
                $funnel->results()->create([
                    'min_score' => $resultData['min_score'],
                    'max_score' => $resultData['max_score'],
                    'title' => $resultData['title'],
                    'body' => $resultData['body'] ?? null,
                    'cta_label' => $resultData['cta_label'] ?? null,
                    'cta_url' => $resultData['cta_url'] ?? null,
                    'show_contact_form' => $resultData['show_contact_form'] ?? true,
                ]);
            }
        });
    }
}
