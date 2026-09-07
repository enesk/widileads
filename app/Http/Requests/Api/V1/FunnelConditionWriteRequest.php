<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Constants\ConditionOperator;
use App\Models\Funnel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Verzweigungsregel anlegen oder aendern (FB-030b, Schema
 * `FunnelConditionWriteRequest`).
 *
 * Quellfrage und Zielschritt muessen zum Funnel im Pfad gehoeren -- sonst
 * zeigte eine Regel auf einen fremden Funnel.
 */
class FunnelConditionWriteRequest extends FormRequest
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
        $creating = $this->isMethod('POST');
        $funnel = $this->route('funnel');
        $funnelId = $funnel instanceof Funnel ? $funnel->getKey() : 0;

        return [
            'source_question_id' => [
                $creating ? 'required' : 'sometimes', 'integer',
                Rule::exists('funnel_questions', 'id')->where('funnel_id', $funnelId),
            ],
            'operator' => [$creating ? 'required' : 'sometimes', Rule::enum(ConditionOperator::class)],
            'value' => ['sometimes', 'nullable'],
            'target_step_id' => [
                $creating ? 'required' : 'sometimes', 'integer',
                Rule::exists('funnel_steps', 'id')->where('funnel_id', $funnelId),
            ],
            'evaluate_at_step_position' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'priority' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
