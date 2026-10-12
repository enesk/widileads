<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Constants\PhoneVerificationChannel;
use App\Constants\PhoneVerificationStatus;
use App\Models\PhoneVerificationAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhoneVerificationAttempt>
 */
class PhoneVerificationAttemptFactory extends Factory
{
    protected $model = PhoneVerificationAttempt::class;

    /**
     * Eine angeforderte SMS, so wie der Dienst sie protokolliert.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'phone_e164' => '+4917'.fake()->numerify('########'),
            'channel' => PhoneVerificationChannel::SMS,
            'verification_sid' => 'VE'.fake()->regexify('[a-f0-9]{32}'),
            'status' => PhoneVerificationStatus::PENDING,
            'created_at' => now(),
        ];
    }

    /**
     * Der Code wurde richtig eingegeben.
     */
    public function approved(): self
    {
        return $this->state(['status' => PhoneVerificationStatus::APPROVED]);
    }

    /**
     * Twilio hat die Anforderung abgelehnt -- es ging kein Code raus, der
     * Versuch zaehlt nicht gegen das Limit.
     */
    public function failed(): self
    {
        return $this->state([
            'status' => PhoneVerificationStatus::FAILED,
            'verification_sid' => null,
        ]);
    }

    /**
     * Der Ausweg per Anruf.
     */
    public function byCall(): self
    {
        return $this->state(['channel' => PhoneVerificationChannel::CALL]);
    }

    /**
     * Eine bestimmte Nummer -- fuer die Limit-Pruefungen.
     */
    public function forPhone(string $phoneE164): self
    {
        return $this->state(['phone_e164' => $phoneE164]);
    }
}
