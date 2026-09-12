<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Constants\WalletTransactionType;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\InvoiceService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Die Seite nach der Zahlung (Entwurf `guthaben-erfolg.html`).
 *
 * Sie loest die alte Dankeseite `checkout/product-thank-you` fuer Aufladungen
 * ab. Der Unterschied ist nicht die Gestaltung, sondern die Ehrlichkeit: Die
 * Gutschrift passiert nicht hier, sondern im Zuhoerer CreditWalletAfterPayment,
 * und der laeuft, wenn der Zahlungsanbieter seinen Webhook schickt. Zwischen
 * "bezahlt" und "gutgeschrieben" liegen also Sekunden, in denen der Kaeufer
 * schon auf dieser Seite steht.
 *
 * Deshalb drei Zustaende statt einer Erfolgsmeldung:
 *
 *   pending  Zahlung da, Gutschrift laeuft. Die Seite fragt alle zwei Sekunden nach.
 *   done     Die Buchung steht im Journal. Guthaben und Rechnung sind da.
 *   slow     Nach 30 Sekunden ist die Buchung immer noch nicht da.
 *
 * **Diese Komponente bucht nichts und rechnet nichts.** Sie sieht ausschliesslich
 * nach, ob die Aufladebuchung im Wallet-Journal steht -- dieselbe Buchung, die
 * der Zuhoerer anlegt, gefunden ueber ihren Verweis auf die Bestellung. Eine
 * zweite Rechenweise waere genau die Stelle, an der Anzeige und Journal
 * auseinanderlaufen (LP-WALLET-009).
 *
 * "slow" ist bewusst keine Fehlermeldung: Es ist nichts schiefgegangen, was der
 * Kaeufer richten koennte. Das Geld ist da, die Buchung kommt, und die Seite
 * sagt beides.
 */
#[Layout('components.layouts.portal-app')]
class TopUpSuccess extends Component
{
    use InteractsWithPortalTenant;

    /** So lange gilt eine ausstehende Gutschrift als normal. */
    private const SLOW_AFTER_SECONDS = 30;

    /**
     * Die Bestellung, um die es geht. Kommt als Adressparameter vom
     * Zahlungsanbieter zurueck.
     */
    #[Url(as: 'bestellung')]
    public string $orderUuid = '';

    /**
     * Wann der Kaeufer hier angekommen ist. Gesperrt, damit die Uhr nicht vom
     * Browser gestellt werden kann -- sonst liesse sich "slow" erzwingen.
     */
    #[Locked]
    public string $arrivedAt = '';

    public function mount(): void
    {
        abort_unless(Gate::allows('marketplace.access', $this->portalTenant()), 403);

        $this->arrivedAt = now()->toIso8601String();
    }

    public function render(): View
    {
        $order = $this->order();
        $credit = $order instanceof Order ? $this->creditTransaction($order) : null;
        $state = $this->state($order, $credit);

        return view('livewire.portal.top-up-success', [
            'state' => $state,
            'amount' => $order instanceof Order
                ? Money::format((int) $order->total_amount_after_discount)
                : null,
            'balance' => Money::format($this->balanceCents()),
            'leadsCovered' => $this->leadsCovered(),
            // Nur wenn die Buchung steht: Vorher gibt es keine Rechnung, und
            // ein Knopf, der ins Leere fuehrt, ist schlimmer als keiner.
            'invoiceUrl' => $credit instanceof WalletTransaction ? $this->invoiceUrl($order) : null,
            'reference' => $this->paymentReference($credit),
            'marketplaceUrl' => route('portal.marketplace', ['tenant' => $this->portalTenant()->uuid]),
            'walletUrl' => route('portal.wallet', ['tenant' => $this->portalTenant()->uuid]),
            'email' => $this->portalUser()?->email,
            'supportEmail' => (string) config('app.support_email'),
            // Nachgefragt wird nur, solange etwas offen ist. Eine Seite, die
            // fuer immer alle zwei Sekunden anklopft, kostet Rechenzeit ohne
            // Nutzen.
            'shouldPoll' => $state === 'pending',
        ])->title(__('marketplace.wallet.top_up.success.heading'));
    }

    /**
     * pending, done oder slow.
     */
    private function state(?Order $order, ?WalletTransaction $credit): string
    {
        if ($credit instanceof WalletTransaction) {
            return 'done';
        }

        // Ohne Bestellung gibt es nichts zu erwarten -- etwa bei einem alten
        // Link. Die Ansicht zeigt dann denselben Kasten wie bei "dauert
        // laenger", nur ohne Referenz.
        if (! $order instanceof Order) {
            return 'slow';
        }

        return $this->arrived()->diffInSeconds(now()) > self::SLOW_AFTER_SECONDS
            ? 'slow'
            : 'pending';
    }

    /**
     * Die Bestellung -- nur die eigene.
     *
     * Eine fremde Kennung ist hier nicht "verboten", sondern schlicht nicht
     * vorhanden: Sonst liesse sich an der Antwort ablesen, welche Bestellungen
     * es gibt.
     */
    private function order(): ?Order
    {
        if ($this->orderUuid === '') {
            return null;
        }

        return Order::query()
            ->where('uuid', $this->orderUuid)
            ->where('user_id', $this->portalUser()?->getKey())
            ->first();
    }

    /**
     * Die Aufladebuchung zu dieser Bestellung -- oder null, solange sie noch
     * nicht da ist.
     *
     * Gesucht wird ueber den Verweis, den der Zuhoerer setzt, und ausdruecklich
     * im Wallet dieses Mandanten: Ein Kaeufer soll nicht am fremden Journal
     * ablesen koennen, ob dort etwas gebucht wurde.
     */
    private function creditTransaction(Order $order): ?WalletTransaction
    {
        return WalletTransaction::query()
            ->where('wallet_id', Wallet::forBuyer($this->portalTenant())->getKey())
            ->where('type', WalletTransactionType::TOPUP)
            ->where('reference_type', $order->getMorphClass())
            ->where('reference_id', $order->getKey())
            ->first();
    }

    private function balanceCents(): int
    {
        return Wallet::forBuyer($this->portalTenant())->balance_cents;
    }

    /**
     * Fuer wie viele Leads das Guthaben reicht. Reine Anzeige -- der Preis
     * eines Leads haengt am Verkaeufer, hier steht der Vorgabepreis.
     */
    private function leadsCovered(): int
    {
        $price = (int) config('wallet.default_lead_price_cents');

        return $price > 0 ? intdiv($this->balanceCents(), $price) : 0;
    }

    /**
     * Die Rechnung zur Zahlung dieser Bestellung -- oder null, wenn es keine
     * gibt (fehlende Verkaeuferangaben, siehe InvoiceService).
     */
    private function invoiceUrl(?Order $order): ?string
    {
        if (! $order instanceof Order) {
            return null;
        }

        $transaction = Transaction::query()
            ->where('order_id', $order->getKey())
            ->where('user_id', $this->portalUser()?->getKey())
            ->latest('id')
            ->first();

        if (! $transaction instanceof Transaction) {
            return null;
        }

        return app(InvoiceService::class)->canGenerateInvoices($transaction)
            ? route('invoice.generate', ['transactionUuid' => $transaction->uuid])
            : null;
    }

    /**
     * Die Zahlungsreferenz, gekuerzt. Sie steht im Kasten "dauert laenger",
     * damit der Kaeufer dem Support etwas nennen kann, ohne dass die volle
     * Kennung auf dem Bildschirm steht.
     */
    private function paymentReference(?WalletTransaction $credit): ?string
    {
        $paymentId = $credit?->meta['payment_id'] ?? null;

        if (! is_string($paymentId) || $paymentId === '') {
            return $this->orderUuid === '' ? null : 'order_…'.mb_substr($this->orderUuid, -4);
        }

        return mb_substr($paymentId, 0, 3).'…'.mb_substr($paymentId, -4);
    }

    private function arrived(): Carbon
    {
        return $this->arrivedAt === '' ? now() : Carbon::parse($this->arrivedAt);
    }
}
