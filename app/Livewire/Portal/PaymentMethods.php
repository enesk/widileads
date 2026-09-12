<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Constants\PaymentMethodType;
use App\Exceptions\PaymentMethodNotAllowedException;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Livewire\Portal\Concerns\ShowsToasts;
use App\Models\PaymentMethod;
use App\Models\Wallet;
use App\Services\Payments\PaymentMethodService;
use App\Support\PostpaidTerms;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Die Seite "Zahlungsmittel" im Portal (LP-POSTPAID-010).
 *
 * **Arbeitsteilung mit dem Browser.** Das Hinterlegen selbst laeuft nicht ueber
 * Livewire, sondern ueber die vorhandenen JSON-Adressen
 * (App\Http\Controllers\Buyer\PaymentMethodController) und Stripe Elements:
 * IBAN und Kartennummer duerfen diese Anwendung nicht beruehren, sie gehen im
 * Browser direkt an Stripe. Der Stepper steht deshalb als unveraenderliches
 * Markup (`wire:ignore`) in der Ansicht und wird von
 * resources/js/modules/payment-method.js bedient; nach dem Speichern meldet das
 * Skript `payment-method-added`, und diese Komponente zeichnet ihre Liste neu.
 *
 * Livewire fuehrt nur, was den Server angeht: die Liste und das Entfernen. Das
 * Entfernen laeuft dabei ueber denselben Dienst wie der JSON-Weg und behaelt
 * damit dieselbe Sperre -- das letzte einsatzbereite Mittel eines
 * Postpaid-Kaeufers bleibt stehen, solange ein Betrag offen ist.
 */
#[Layout('components.layouts.portal-app', ['stripe' => true])]
class PaymentMethods extends Component
{
    use InteractsWithPortalTenant;
    use ShowsToasts;

    /**
     * Der Hinweis an der Liste, wenn ein Mittel nicht entfernt werden darf.
     * Kein Toast: Er nennt einen Grund, der stehen bleiben soll.
     */
    public ?string $removalNotice = null;

    /**
     * Das Skript hat ein Mittel hinterlegt -- die Liste ist veraltet.
     */
    #[On('payment-method-added')]
    public function paymentMethodAdded(): void
    {
        $this->removalNotice = null;
        $this->toast(__('portal.postpaid.payment_methods.added'));
    }

    /**
     * Entfernt ein Zahlungsmittel, sofern es entbehrlich ist.
     */
    public function remove(int $paymentMethodId): void
    {
        $this->removalNotice = null;

        $method = $this->wallet()->paymentMethods()->whereKey($paymentMethodId)->first();

        // Ein fremdes Mittel ist hier nicht "verboten", sondern unbekannt.
        if (! $method instanceof PaymentMethod) {
            abort(404);
        }

        try {
            app(PaymentMethodService::class)->revoke($method);
        } catch (PaymentMethodNotAllowedException $exception) {
            $this->removalNotice = $exception->getMessage();

            return;
        }

        $this->toast(__('portal.postpaid.payment_methods.removed'));
    }

    public function render(): View
    {
        $tenant = $this->portalTenant();

        abort_unless(Gate::allows('marketplace.access', $tenant), 403);

        $wallet = $this->wallet();

        return view('livewire.portal.payment-methods', [
            'tenant' => $tenant,
            'methods' => $wallet->paymentMethods()->active()->get(),
            'types' => $this->types(),
            'mandate' => app(PaymentMethodService::class)->mandate(),
            'walletUrl' => route('portal.wallet', ['tenant' => $tenant->uuid]),
            'isPostpaid' => PostpaidTerms::isPostpaid($wallet),
        ]);
    }

    /**
     * Die waehlbaren Arten samt erklaerendem Satz. SEPA steht zuerst, keine
     * ist vorausgewaehlt -- die Wahl gehoert dem Kaeufer.
     *
     * @return list<array{value: string, label: string, hint: string, icon: string}>
     */
    private function types(): array
    {
        return array_values(array_map(
            static fn (PaymentMethodType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'hint' => (string) __('marketplace.wallet.payment_methods.type_hints.'.$type->value),
                'icon' => $type === PaymentMethodType::CARD ? 'card' : 'bank',
            ],
            PaymentMethodType::cases(),
        ));
    }

    private function wallet(): Wallet
    {
        return Wallet::forBuyer($this->portalTenant());
    }
}
