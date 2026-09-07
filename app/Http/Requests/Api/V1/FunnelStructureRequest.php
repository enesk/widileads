<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Constants\ConditionOperator;
use App\Constants\QuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Komplette Funnel-Struktur (FB-030b, Schema `FunnelStructure`).
 *
 * Dasselbe Format wie Snapshot und Seed-Vorlage: Positionen statt IDs, Fragen
 * ueber ihren Feldschluessel. Nur so bleibt eine exportierte Struktur woanders
 * einspielbar.
 */
class FunnelStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'steps' => ['required', 'array'],
            'steps.*.position' => ['required', 'integer', 'min:1'],
            'steps.*.title' => ['required', 'string', 'max:255'],
            'steps.*.description' => ['sometimes', 'nullable', 'string'],
            'steps.*.questions' => ['sometimes', 'array'],
            'steps.*.questions.*.field_key' => ['required', 'string', 'max:64'],
            'steps.*.questions.*.type' => ['required', Rule::enum(QuestionType::class)],
            'steps.*.questions.*.label' => ['required', 'string', 'max:255'],
            'steps.*.questions.*.position' => ['sometimes', 'integer', 'min:1'],
            'steps.*.questions.*.required' => ['sometimes', 'boolean'],
            'steps.*.questions.*.help_text' => ['sometimes', 'nullable', 'string'],
            'steps.*.questions.*.validation' => ['sometimes', 'nullable', 'array'],
            'steps.*.questions.*.meta' => ['sometimes', 'nullable', 'array'],
            'steps.*.questions.*.options' => ['sometimes', 'array'],
            'steps.*.questions.*.options.*.value' => ['required', 'string', 'max:255'],
            'steps.*.questions.*.options.*.label' => ['required', 'string', 'max:255'],
            'steps.*.questions.*.options.*.score' => ['sometimes', 'nullable', 'integer'],
            'steps.*.questions.*.options.*.position' => ['sometimes', 'integer', 'min:1'],
            'steps.*.questions.*.options.*.image_path' => ['sometimes', 'nullable', 'string'],

            'conditions' => ['sometimes', 'array'],
            'conditions.*.source_field_key' => ['required', 'string', 'max:64'],
            'conditions.*.operator' => ['required', Rule::enum(ConditionOperator::class)],
            'conditions.*.value' => ['sometimes', 'nullable'],
            'conditions.*.target_step_position' => ['required', 'integer', 'min:1'],
            'conditions.*.evaluate_at_step_position' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'conditions.*.priority' => ['sometimes', 'integer', 'min:0'],

            'results' => ['required', 'array'],
            'results.*.min_score' => ['required', 'integer'],
            'results.*.max_score' => ['required', 'integer'],
            'results.*.title' => ['required', 'string', 'max:255'],
            'results.*.body' => ['sometimes', 'nullable', 'string'],
            'results.*.cta_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'results.*.cta_url' => ['sometimes', 'nullable', 'url'],
            'results.*.show_contact_form' => ['sometimes', 'boolean'],
        ];
    }
}
