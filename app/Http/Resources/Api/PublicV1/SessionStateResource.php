<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\PublicV1;

use App\Funnel\Runtime\StepOutcome;
use App\Models\PublicSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stand einer Sitzung samt naechstem Schritt (FB-026).
 *
 * Adressiert wird die Sitzung ueber ihren Token, nie ueber die ID -- wie beim
 * Funnel selbst. Eine fortlaufende Nummer nach aussen zu geben hiesse, fremde
 * Sitzungen erraten zu koennen.
 *
 * @mixin PublicSession
 */
class SessionStateResource extends JsonResource
{
    public function __construct(
        PublicSession $session,
        private readonly ?StepOutcome $outcome = null,
    ) {
        parent::__construct($session);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'session_token' => $this->token,
            'answers' => $this->answers ?? [],
            'current_step' => $this->current_step,
            'completed' => $this->completed_at !== null,
            'phase' => $this->outcome instanceof StepOutcome
                ? $this->outcome->phase
                : ($this->completed_at !== null ? 'done' : 'step'),
            'step' => $this->stepPayload(),
            'result' => $this->resultPayload(),
            'score' => $this->outcome?->score,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function stepPayload(): ?array
    {
        $step = $this->outcome?->step;

        if ($step === null) {
            return null;
        }

        return [
            'position' => $step->position,
            'title' => $step->title,
            'description' => $step->description,
            'field_keys' => $step->fieldKeys(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resultPayload(): ?array
    {
        $result = $this->outcome?->result;

        if ($result === null) {
            return null;
        }

        return [
            'key' => $result->key,
            'title' => $result->title,
            'body' => $result->body,
            'cta_label' => $result->ctaLabel,
            'cta_url' => $result->ctaUrl,
            'show_contact_form' => $result->showContactForm,
        ];
    }
}
