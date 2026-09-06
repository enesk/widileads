<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Constants\AuditAction;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'user_id' => null,
            'action' => fake()->randomElement(AuditAction::cases()),
            'subject_type' => null,
            'subject_id' => null,
            'payload' => null,
            // Niemals eine echte IP: der Faktor liefert direkt einen Hash.
            'ip_hash' => hash('sha256', fake()->uuid()),
            'created_at' => now(),
        ];
    }
}
