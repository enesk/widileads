<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Antwortoption anlegen oder aendern (FB-030b, Schema `FunnelOptionWriteRequest`).
 */
class FunnelOptionWriteRequest extends FormRequest
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
            'label' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'value' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'score' => ['sometimes', 'nullable', 'integer'],
            'image_path' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
