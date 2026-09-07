<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\PublicV1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Antworten eines Schritts (FB-026).
 *
 * Geprueft wird hier nur die Form; die fachlichen Regeln haengen am Fragetyp
 * und kommen aus dem FunnelRunService -- derselbe Satz Regeln wie in der
 * eigenen Strecke.
 */
class UpdateAnswersRequest extends FormRequest
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
            'answers' => ['required', 'array'],
        ];
    }
}
