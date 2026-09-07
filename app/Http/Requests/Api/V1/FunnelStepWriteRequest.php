<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Schritt anlegen oder aendern (FB-030b, Schema `FunnelStepWriteRequest`).
 */
class FunnelStepWriteRequest extends FormRequest
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
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
