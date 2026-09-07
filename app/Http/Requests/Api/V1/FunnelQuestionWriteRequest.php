<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Constants\QuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Frage anlegen oder aendern (FB-030b, Schema `FunnelQuestionWriteRequest`).
 *
 * Der Feldschluessel wird vom Model vereinheitlicht und auf reservierte
 * Schluessel abgebildet (FB-010); "E-Mail" ist also eine zulaessige Eingabe.
 */
class FunnelQuestionWriteRequest extends FormRequest
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

        return [
            'position' => ['sometimes', 'integer', 'min:1'],
            'type' => [$creating ? 'required' : 'sometimes', Rule::enum(QuestionType::class)],
            'field_key' => [$creating ? 'required' : 'sometimes', 'string', 'max:64'],
            'label' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'help_text' => ['sometimes', 'nullable', 'string'],
            'required' => ['sometimes', 'boolean'],
            'validation' => ['sometimes', 'nullable', 'array'],
            'meta' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
