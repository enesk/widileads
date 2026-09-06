<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Constants\SessionEventType;
use App\Models\PublicSession;
use App\Models\SessionEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionEvent>
 */
class SessionEventFactory extends Factory
{
    protected $model = SessionEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => PublicSession::factory(),
            'type' => SessionEventType::VIEW,
            'step_position' => null,
            'created_at' => now(),
        ];
    }
}
