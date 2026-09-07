<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Constants\WebhookEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Webhook anlegen oder aendern (FB-030e).
 *
 * Nur https: Ein Ereignis kann Kontaktdaten enthalten, und ueber http laege es
 * unterwegs offen -- die Signatur schuetzt vor Faelschung, nicht vor Mitlesen.
 */
class FunnelWebhookWriteRequest extends FormRequest
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
            'url' => [$creating ? 'required' : 'sometimes', 'url', 'starts_with:https://', 'max:2048'],
            'events' => [$creating ? 'required' : 'sometimes', 'array', 'min:1'],
            'events.*' => [Rule::enum(WebhookEvent::class)],
            'active' => ['sometimes', 'boolean'],
            'rotate_secret' => ['sometimes', 'boolean'],
        ];
    }
}
