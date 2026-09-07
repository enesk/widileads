<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Constants\LeadState;
use App\Constants\TenantType;
use App\Models\Tenant;
use App\Services\BuyerBillingService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * FB-059: Monatsabrechnung je Kaeufer.
 *
 * Rechnet nichts ab, sondern legt Rechenschaft ab: Was hat der Kaeufer in
 * diesem Monat bekommen, was ist daraus geworden, und wie hat sich sein
 * Guthaben bewegt. Bezahlt wird im Voraus per Guthaben (Entscheidung 1); die
 * Rechnungen dazu stellt SaaSykit beim Kauf des Pakets aus und werden hier nur
 * verknuepft.
 */
class BuyerBilling extends Page
{
    protected string $view = 'filament.admin.pages.buyer-billing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?int $navigationSort = 14;

    public ?int $buyerId = null;

    public string $month = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->buyerId = $this->buyers()->keys()->first();
    }

    public function getHeading(): string|Htmlable
    {
        return __('marketplace.billing.heading');
    }

    public function getTitle(): string|Htmlable
    {
        return __('marketplace.billing.heading');
    }

    public static function getNavigationLabel(): string
    {
        return __('marketplace.billing.nav_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    /**
     * Kaeufer als Kennung => Name.
     *
     * @return Collection<int, string>
     */
    public function buyers(): Collection
    {
        return Tenant::query()
            ->where('type', TenantType::BUYER->value)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function statement(): ?array
    {
        $buyer = $this->selectedBuyer();

        if ($buyer === null) {
            return null;
        }

        return app(BuyerBillingService::class)->statementFor($buyer, $this->selectedMonth());
    }

    /**
     * @return array<string, string>
     */
    public function stateLabels(): array
    {
        $labels = [];

        foreach (LeadState::cases() as $state) {
            $labels[$state->value] = $state->label();
        }

        return $labels;
    }

    /**
     * Die Uebersicht als CSV -- eine Zeile je Zustand plus die Summen.
     */
    public function exportCsv(): ?StreamedResponse
    {
        $buyer = $this->selectedBuyer();
        $statement = $this->statement();

        if ($buyer === null || $statement === null) {
            return null;
        }

        $labels = $this->stateLabels();

        return response()->streamDownload(function () use ($statement, $labels, $buyer): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [__('marketplace.billing.csv.buyer'), $buyer->name], ';');
            fputcsv($handle, [__('marketplace.billing.csv.month'), $statement['month']], ';');
            fputcsv($handle, [], ';');
            fputcsv($handle, [__('marketplace.billing.csv.state'), __('marketplace.billing.csv.count')], ';');

            foreach ($statement['states'] as $state => $count) {
                fputcsv($handle, [$labels[$state] ?? $state, $count], ';');
            }

            fputcsv($handle, [], ';');
            fputcsv($handle, [__('marketplace.billing.purchases'), $statement['purchases']], ';');
            fputcsv($handle, [__('marketplace.billing.revenue'), $statement['revenue_cents'] / 100, $statement['currency']], ';');
            fputcsv($handle, [__('marketplace.billing.credits_debited'), $statement['credits_debited']], ';');
            fputcsv($handle, [__('marketplace.billing.credits_refunded'), $statement['credits_refunded']], ';');
            fputcsv($handle, [__('marketplace.billing.credits_purchased'), $statement['credits_purchased']], ';');

            fclose($handle);
        }, sprintf('abrechnung-%s-%s.csv', $buyer->getKey(), $statement['month']), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function selectedBuyer(): ?Tenant
    {
        if ($this->buyerId === null) {
            return null;
        }

        $buyer = Tenant::query()->find($this->buyerId);

        return $buyer instanceof Tenant ? $buyer : null;
    }

    private function selectedMonth(): Carbon
    {
        return Carbon::createFromFormat('Y-m', $this->month ?: now()->format('Y-m'))
            ?: now();
    }
}
