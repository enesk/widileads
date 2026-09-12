<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Exceptions\PostpaidNotAllowedException;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Livewire\Portal\Concerns\ShowsToasts;
use App\Models\PostpaidApplication as PostpaidApplicationModel;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\PostpaidService;
use App\Support\Money;
use App\Support\PostpaidTerms;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Der Abschnitt "Pay as you go" auf der Guthabenseite (LP-POSTPAID-010).
 *
 * Ein eigener Baustein und keine Karte im Blade der Aufladeseite: Der Antrag
 * ist die einzige Stelle des Portals, die eine Zustandsaenderung ausloest,
 * waehrend die Aufladeseite selbst ein gewoehnliches Formular an den Checkout
 * ist. So bleibt die Aufladeseite ohne Livewire-Aufruf.
 *
 * Gezeigt wird der Abschnitt nur einem Prepaid-Kaeufer bei laufendem Rollout.
 * Wer bereits freigeschaltet ist, sieht stattdessen seinen Kreditrahmen
 * (App\Livewire\Portal\PostpaidBalance).
 *
 * Die Checkliste baut diese Komponente aus dem Schnappschuss der
 * Eignungspruefung und nicht aus eigenen Abfragen: Sonst stuenden im Portal
 * andere Zahlen als im Antrag, den der Admin spaeter vor sich hat. Der Dienst
 * liefert nur die NICHT erfuellten Regeln als Saetze -- fuer die Checkliste
 * braucht es beide Seiten, deshalb die Regeln hier noch einmal als Paare aus
 * Zahl und Vorgabe.
 */
class PostpaidApplication extends Component
{
    use InteractsWithPortalTenant;
    use ShowsToasts;

    /**
     * Nach einer Aufladung oder einem Kauf kann sich die Eignung geaendert
     * haben -- etwa die Zahl der abgerechneten Kaeufe.
     */
    #[On('wallet-updated')]
    public function refreshEligibility(): void
    {
        // Der Neuaufbau der Ansicht genuegt; gelesen wird ohnehin frisch.
    }

    /**
     * Stellt den Antrag. Geprueft wird ausschliesslich im PostpaidService --
     * die Checkliste dieser Seite ist eine Auskunft, kein Riegel.
     */
    public function apply(): void
    {
        $wallet = $this->wallet();
        $user = $this->portalUser();

        try {
            app(PostpaidService::class)->apply($wallet, $user instanceof User ? $user : null);
        } catch (PostpaidNotAllowedException $exception) {
            $this->toastWarning($exception->getMessage());

            return;
        }

        $this->toast(__('portal.postpaid.apply.submitted'));
    }

    public function render(): View
    {
        $wallet = $this->wallet();
        $postpaid = app(PostpaidService::class);

        // Kein Abschnitt, wenn es nichts zu beantragen gibt: abgeschalteter
        // Rollout oder bereits freigeschalteter Kaeufer.
        if (! PostpaidTerms::enabled() || $wallet->isPostpaid()) {
            return view('livewire.portal.postpaid-application', ['visible' => false]);
        }

        $eligibility = $postpaid->eligibility($wallet);
        $hasPaymentMethod = $wallet->defaultPaymentMethod()->exists();
        $pending = $postpaid->pendingApplication($wallet);
        $blockedDays = $postpaid->reapplyBlockedDays($wallet);

        return view('livewire.portal.postpaid-application', [
            'visible' => true,
            'points' => __('portal.postpaid.apply.points', [
                'limit' => Money::format(PostpaidTerms::defaultCreditLimitCents()),
                'weekday' => PostpaidTerms::settlementWeekday(),
                'threshold' => Money::format(PostpaidTerms::thresholdCents()),
                'percent' => PostpaidTerms::surchargePercentLabel(),
            ]),
            'rules' => $this->rules($eligibility->snapshot, $hasPaymentMethod),
            'pending' => $pending,
            'rejected' => $pending === null ? $this->lastRejected($wallet) : null,
            'blockedDays' => $blockedDays,
            'hasPaymentMethod' => $hasPaymentMethod,
            'canApply' => $eligibility->eligible && $hasPaymentMethod && $pending === null && $blockedDays === 0,
            'paymentMethodsUrl' => route('portal.payment-methods', ['tenant' => $this->portalTenant()->uuid]),
        ]);
    }

    /**
     * Die Eignungsregeln als Checkliste: jede Regel mit ihrem Satz und der
     * Angabe, ob sie erfuellt ist.
     *
     * Das hinterlegte Zahlungsmittel steht mit in der Liste, obwohl es keine
     * Eignungsregel ist: Fuer den Kaeufer ist es dieselbe Frage -- was fehlt
     * noch, damit ich beantragen kann.
     *
     * @param  array<string, mixed>  $snapshot
     * @return list<array{text: string, met: bool}>
     */
    private function rules(array $snapshot, bool $hasPaymentMethod): array
    {
        $purchases = (int) ($snapshot['captured_purchases'] ?? 0);
        $minPurchases = (int) ($snapshot['min_captured_purchases'] ?? 0);
        $ageDays = (int) ($snapshot['account_age_days'] ?? 0);
        $minAgeDays = (int) ($snapshot['min_account_age_days'] ?? 0);
        $cleanDays = (int) ($snapshot['clean_history_days'] ?? 0);
        $incidents = (int) ($snapshot['failed_settlements'] ?? 0) + (int) ($snapshot['chargebacks'] ?? 0);
        $downgraded = in_array(
            $snapshot['postpaid_disabled_reason'] ?? null,
            PostpaidService::DISQUALIFYING_DISABLE_REASONS,
            true,
        );

        return [
            [
                'text' => (string) __('portal.postpaid.apply.rules.purchases', [
                    'required' => $minPurchases,
                    'count' => $purchases,
                ]),
                'met' => $purchases >= $minPurchases,
            ],
            [
                'text' => (string) __('portal.postpaid.apply.rules.account_age', [
                    'required' => $minAgeDays,
                    'days' => $ageDays,
                ]),
                'met' => $ageDays >= $minAgeDays,
            ],
            [
                'text' => (string) __('portal.postpaid.apply.rules.history', ['days' => $cleanDays]),
                'met' => $incidents === 0 && ! $downgraded,
            ],
            [
                'text' => (string) __('portal.postpaid.apply.rules.not_blocked'),
                'met' => ! (bool) ($snapshot['purchase_blocked'] ?? false),
            ],
            [
                'text' => (string) __('portal.postpaid.apply.rules.payment_method'),
                'met' => $hasPaymentMethod,
            ],
        ];
    }

    /**
     * Der zuletzt abgelehnte Antrag -- fuer den Hinweis, wann wieder beantragt
     * werden darf.
     */
    private function lastRejected(Wallet $wallet): ?PostpaidApplicationModel
    {
        return $wallet->postpaidApplications()
            ->where('status', PostpaidApplicationModel::STATUS_REJECTED)
            ->orderByDesc('decided_at')
            ->first();
    }

    private function wallet(): Wallet
    {
        return Wallet::forBuyer($this->portalTenant());
    }
}
