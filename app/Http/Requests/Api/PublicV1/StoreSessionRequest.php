<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\PublicV1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Beginn einer Sitzung ueber die oeffentliche API (FB-026).
 */
class StoreSessionRequest extends FormRequest
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
            // Eine bestehende Sitzung darf fortgesetzt werden -- das fremde
            // Frontend haelt den Token selbst, wir kennen kein Cookie.
            'session_token' => ['nullable', 'string', 'size:26'],
        ];
    }
}
