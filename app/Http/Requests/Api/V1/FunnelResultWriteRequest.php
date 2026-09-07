<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ergebnis-Screen anlegen oder aendern (FB-030b, Schema
 * `FunnelResultWriteRequest`).
 */
class FunnelResultWriteRequest extends FormRequest
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
            'min_score' => [$creating ? 'required' : 'sometimes', 'integer'],
            'max_score' => [$creating ? 'required' : 'sometimes', 'integer', 'gte:min_score'],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string'],
            'cta_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cta_url' => ['sometimes', 'nullable', 'url'],
            'show_contact_form' => ['sometimes', 'boolean'],
        ];
    }
}
