<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Constants\SettlementStatus;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Models\Settlement;
use App\Models\Wallet;
use App\Support\Money;
use App\Support\PostpaidTerms;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Die Seite "Abrechnungen" im Portal (LP-POSTPAID-010).
 *
 * Jeder Einzug des offenen Betrags als eine Zeile: wann, wieviel, ueber welches
 * Mittel, mit welchem Ausgang. Die Liste zeigt alles und filtert nichts -- ein
 * fehlgeschlagener Einzug ist genau die Zeile, die ein Kaeufer sucht.
 *
 * Eine reine Ansicht: Gebucht, eingezogen und wiederholt wird im
 * SettlementService (LP-POSTPAID-008), hier steht keine Zustandsaenderung.
 */
#[Layout('components.layouts.portal-app')]
class Settlements extends Component
{
    use InteractsWithPortalTenant;
    use WithPagination;

    private const PER_PAGE = 20;

    public function render(): View
    {
        $tenant = $this->portalTenant();

        abort_unless(Gate::allows('marketplace.access', $tenant), 403);

        return view('livewire.portal.settlements', [
            'settlements' => $this->settlements(),
            'walletUrl' => route('portal.wallet', ['tenant' => $tenant->uuid]),
        ]);
    }

    public function money(int $cents): string
    {
        return Money::format($cents);
    }

    /**
     * Die Beschriftung des Abzeichens. Der Wiederholungsversuch nennt sein
     * Datum, sofern eines feststeht -- "Erneuter Versuch" ohne Termin laesst
     * den Kaeufer raten.
     */
    public function statusLabel(Settlement $settlement): string
    {
        if ($settlement->status === SettlementStatus::RETRY_PENDING) {
            $date = $settlement->next_attempt_at;

            return $date === null
                ? (string) __('portal.postpaid.settlements.status_labels.retry_pending_short')
                : (string) __('portal.postpaid.settlements.status_labels.retry_pending', [
                    'date' => $date->format('d.m.Y'),
                ]);
        }

        return (string) __('portal.postpaid.settlements.status_labels.'.$settlement->status->value);
    }

    /**
     * Farbe des Abzeichens: bezahlt gruen, gescheitert rot, unterwegs gelb.
     */
    public function statusTone(Settlement $settlement): string
    {
        return match ($settlement->status) {
            SettlementStatus::PAID => 'green',
            SettlementStatus::FAILED, SettlementStatus::RETURNED => 'red',
            SettlementStatus::PROCESSING, SettlementStatus::RETRY_PENDING => 'amber',
            default => 'zinc',
        };
    }

    /**
     * Das Mittel, ueber das eingezogen wurde. Der Kaeufer kann es inzwischen
     * entfernt haben -- die Forderung besteht dann weiter, die Zeile auch.
     */
    public function methodLabel(Settlement $settlement): string
    {
        return $settlement->paymentMethod?->label()
            ?? (string) __('portal.postpaid.settlements.method_unknown');
    }

    public function surchargeHint(): string
    {
        return PostpaidTerms::surchargeHint();
    }

    /**
     * @return LengthAwarePaginator<int, Settlement>
     */
    private function settlements(): LengthAwarePaginator
    {
        return Settlement::query()
            ->with('paymentMethod')
            ->where('wallet_id', Wallet::forBuyer($this->portalTenant())->getKey())
            ->orderByDesc('created_at')
            // Zweites Merkmal: Zwei Einzuege derselben Sekunde stuenden sonst
            // in zufaelliger Reihenfolge.
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
    }
}
