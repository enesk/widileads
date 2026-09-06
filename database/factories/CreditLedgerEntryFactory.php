<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Constants\CreditLedgerType;
use App\Constants\TenantType;
use App\Models\CreditLedgerEntry;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditLedgerEntry>
 */
class CreditLedgerEntryFactory extends Factory
{
    protected $model = CreditLedgerEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory()->state(['type' => TenantType::BUYER]),
            'type' => CreditLedgerType::PURCHASE,
            'credits' => 10,
            'amount_cents' => 15000,
            'reference_type' => null,
            'reference_id' => null,
        ];
    }

    public function debit(int $credits = 1): static
    {
        return $this->state(fn (): array => [
            'type' => CreditLedgerType::DEBIT,
            'credits' => -abs($credits),
            'amount_cents' => null,
        ]);
    }
}
