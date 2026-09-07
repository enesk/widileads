<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Constants\ComplaintStatus;
use App\Constants\LeadState;
use App\Models\LeadComplaint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadComplaint>
 */
class LeadComplaintFactory extends Factory
{
    protected $model = LeadComplaint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => ComplaintStatus::PENDING,
            'requested_state' => LeadState::UNERREICHBAR,
            'reason' => 'Dreimal angerufen, niemand erreichbar.',
        ];
    }
}
