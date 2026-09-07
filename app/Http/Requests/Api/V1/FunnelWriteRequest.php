<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Funnel anlegen oder aendern (FB-030b, Schema `FunnelWriteRequest`).
 *
 * `status` und `public_token` lassen sich nicht setzen: Der Zustand aendert
 * sich ueber publish/archive (FB-030c), der Token wird serverseitig vergeben.
 */
class FunnelWriteRequest extends FormRequest
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
        $tenantId = $this->user()?->getKey();

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'min:1', 'max:255'],
            'slug' => [
                'sometimes', 'nullable', 'string', 'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('funnels', 'slug')
                    ->where('tenant_id', $tenantId)
                    ->ignore($this->route('funnel')?->getKey()),
            ],
            'lead_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999.99'],
            'contact_step_position' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
