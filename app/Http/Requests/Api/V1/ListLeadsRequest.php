<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Constants\LeadState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Die Filter von GET /api/v1/leads (FB-030d).
 *
 * Die Regeln bilden exakt die Parameter aus docs/openapi.yaml ab -- ein
 * unbekannter oder unpassender Wert fuehrt zu einer Problem-Antwort nach
 * RFC 9457 statt zu einer stillschweigend anderen Ergebnismenge.
 */
class ListLeadsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.$this->maximumPerPage()],
            'funnel' => ['sometimes', 'string', 'size:26'],
            'state' => ['sometimes', 'array'],
            'state.*' => [Rule::enum(LeadState::class)],
            'created_from' => ['sometimes', 'date'],
            'created_until' => ['sometimes', 'date'],
            'postal_code_prefix' => ['sometimes', 'string', 'regex:/^[0-9]{1,5}$/'],
            'score_min' => ['sometimes', 'integer'],
            'score_max' => ['sometimes', 'integer'],
            'stale' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', Rule::in(['created_at', 'score', '-created_at', '-score'])],
        ];
    }

    public function perPage(): int
    {
        return min(
            (int) $this->query('per_page', (string) $this->defaultPerPage()),
            $this->maximumPerPage(),
        );
    }

    private function defaultPerPage(): int
    {
        return (int) config('funnel.api.leads_per_page');
    }

    private function maximumPerPage(): int
    {
        return (int) config('funnel.api.leads_max_per_page');
    }
}
