<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\PublicV1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Abschluss einer Anfrage (FB-026).
 */
class SubmitSessionRequest extends FormRequest
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
            'answers' => ['nullable', 'array'],
            // Honigtopf wie in FB-023: Ein fremdes Frontend soll das Feld
            // mitfuehren koennen, ohne dass ein Mensch es je ausfuellt.
            'website' => ['nullable', 'string'],
        ];
    }
}
