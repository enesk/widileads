<?php

declare(strict_types=1);

namespace App\Listeners\Order;

use App\Constants\WalletTransactionType;
use App\Events\Order\Ordered;
use App\Events\Order\OrderRefunded;
use App\Mail\Wallet\WalletTopupConfirmedMail;
use App\Models\OneTimeProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentProvider;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Mail;

/**
 * Schreibt die bezahlte Aufladung ins Kaeufer-Wallet (LP-WALLET-009).
 *
 * Ersetzt den bisherigen Zuhoerer BookCreditsOnOrder: Aufgeladen wird kein
 * Guthabenpaket mehr, sondern ein frei gewaehlter Betrag in Euro. Abgerechnet
 * wird weiterhin ueber den vorhandenen Einmalkauf-Checkout von SaaSykit -- das
 * Aufladeprodukt (config('wallet.topup_product_slug')) kostet einen Euro, die
 * Menge im Warenkorb ist der Betrag.
 *
 * Gutgeschrieben wird der **tatsaechlich bezahlte** Betrag der Bestellung
 * (`total_amount_after_discount`) und nicht die Menge im Warenkorb. Damit
 * stimmt die Gutschrift auch dann mit dem Geldeingang ueberein, wenn ein
 * Rabattcode gezogen hat -- eine zweite Rechenweise waere genau die Stelle, an
 * der Wallet und Zahlungsanbieter auseinanderlaufen.
 *
 * Bewusst **nicht** in die Queue gegeben: Zwischen bezahlter Bestellung und
 * gutgeschriebenem Guthaben soll nichts liegen, das fehlschlagen und unbemerkt
 * liegenbleiben kann. Scheitert die Buchung, scheitert die
 * Webhook-Verarbeitung -- und der Zahlungsanbieter stellt erneut zu. Dass
 * daraus keine doppelte Gutschrift wird, sichert der Idempotenzschluessel
 * 'topup:{provider}:{payment_id}': Ein wiederholter Webhook findet die
 * vorhandene Buchung und bucht nichts.
 *
 * Fehlgeschlagene Zahlungen brauchen keinen eigenen Weg: Ohne den Uebergang
 * nach `success` feuert `Ordered` nicht, es wird also nie gutgeschrieben.
 * Rueckbuchungen und Erstattungen fangen wir dagegen ab (handleRefund) --
 * dort ist das Geld schon im Wallet und muss wieder heraus, notfalls ins Minus.
 *
 * Abweichung von der Ticketvorgabe: Die Datei liegt in `app/Listeners/Order/`
 * und nicht direkt unter `app/Listeners/`, wo alle uebrigen Zuhoerer der
 * Bestellereignisse liegen. Die Ereigniserkennung von Laravel durchsucht das
 * Verzeichnis rekursiv.
 */
class CreditWalletAfterPayment
{
    public function __construct(private readonly WalletService $wallet) {}

    /**
     * Bestellung bezahlt: Betrag ins Kaeufer-Wallet gutschreiben.
     */
    public function handle(Ordered $event): void
    {
        $order = $event->order;

        if (! $this->isTopup($order)) {
            return;
        }

        $tenant = $this->tenantOf($order);

        if (! $tenant instanceof Tenant) {
            return;
        }

        $amountCents = (int) $order->total_amount_after_discount;

        // Eine Bestellung ueber 0,00 EUR (voller Rabatt) ist keine Aufladung.
        // Eine Buchung ohne Wirkung gehoert nicht ins Journal.
        if ($amountCents <= 0) {
            return;
        }

        $wallet = Wallet::forBuyer($tenant);

        $transaction = $this->wallet->post(
            $wallet,
            WalletTransactionType::TOPUP,
            $amountCents,
            __('marketplace.wallet.descriptions.topup', ['order' => (string) $order->uuid]),
            $order,
            $this->topupKey($order),
            [
                'order_uuid' => (string) $order->uuid,
                'provider' => $this->providerSlug($order),
                'payment_id' => $this->paymentId($order),
            ],
        );

        // Nur wenn diese Buchung neu ist: Ein wiederholter Webhook soll keine
        // zweite Bestaetigungsmail ausloesen.
        if (! $transaction->wasRecentlyCreated) {
            return;
        }

        $this->sendConfirmation($order, $tenant, $transaction);
    }

    /**
     * Bestellung erstattet oder zurueckgebucht: Betrag wieder aus dem Wallet
     * nehmen.
     *
     * Als Korrekturbuchung mit `meta.reason = chargeback` und ausdruecklich mit
     * `allowNegative`: Das Guthaben kann laengst fuer Leads ausgegeben sein.
     * Die Rueckbuchung daran scheitern zu lassen, hiesse dem Kaeufer Guthaben
     * zu lassen, fuer das kein Geld mehr da ist -- das Minus ist die ehrlichere
     * Darstellung und wird dem Admin in der Wallet-Uebersicht gezeigt.
     */
    public function handleRefund(OrderRefunded $event): void
    {
        $order = $event->order;

        if (! $this->isTopup($order)) {
            return;
        }

        $tenant = $this->tenantOf($order);

        if (! $tenant instanceof Tenant) {
            return;
        }

        // Zurueckgenommen wird genau der Betrag, der gutgeschrieben wurde --
        // nicht der Bestellbetrag. Gibt es keine Gutschrift, gibt es auch
        // nichts zurueckzunehmen (etwa bei einer Bestellung, die nie auf
        // `success` stand).
        $topup = WalletTransaction::query()
            ->where('idempotency_key', $this->topupKey($order))
            ->first();

        if (! $topup instanceof WalletTransaction) {
            return;
        }

        $this->wallet->post(
            Wallet::forBuyer($tenant),
            WalletTransactionType::ADJUSTMENT,
            -1 * (int) $topup->amount_cents,
            __('marketplace.wallet.descriptions.chargeback', ['order' => (string) $order->uuid]),
            $order,
            $this->chargebackKey($order),
            [
                'reason' => 'chargeback',
                'order_uuid' => (string) $order->uuid,
                'provider' => $this->providerSlug($order),
                'payment_id' => $this->paymentId($order),
                'topup_transaction_id' => $topup->getKey(),
            ],
            allowNegative: true,
        );
    }

    /**
     * Die Bestaetigung an den Kaeufer. Sie nennt Betrag und neuen Stand -- der
     * Stand kommt aus der Buchung selbst und wird nicht erneut abgefragt, damit
     * Mail und Journal denselben Wert zeigen.
     */
    private function sendConfirmation(Order $order, Tenant $tenant, WalletTransaction $transaction): void
    {
        $recipient = $order->user_id === null ? null : User::query()->find($order->user_id);

        if (! $recipient instanceof User) {
            return;
        }

        Mail::to($recipient)->send(new WalletTopupConfirmedMail(
            $tenant,
            $transaction,
            $recipient,
        ));
    }

    /**
     * Idempotenzschluessel der Gutschrift, providerunabhaengig aufgebaut:
     * 'topup:{provider}:{payment_id}'.
     */
    private function topupKey(Order $order): string
    {
        return sprintf('topup:%s:%s', $this->providerSlug($order), $this->paymentId($order));
    }

    private function chargebackKey(Order $order): string
    {
        return sprintf('chargeback:%s:%s', $this->providerSlug($order), $this->paymentId($order));
    }

    /**
     * Der Zahlungsanbieter der Bestellung. Ohne hinterlegten Anbieter (lokale
     * oder manuelle Bestellung) bleibt 'local' -- der Schluessel bleibt
     * eindeutig, weil die Bestellkennung dahinter steht.
     */
    private function providerSlug(Order $order): string
    {
        $slug = $order->payment_provider_id === null
            ? null
            : PaymentProvider::query()->whereKey($order->payment_provider_id)->value('slug');

        return is_string($slug) && $slug !== '' ? $slug : 'local';
    }

    /**
     * Die Zahlungskennung des Anbieters. Fehlt sie, tritt die Bestellkennung an
     * ihre Stelle: Auch dann darf es zu einer Bestellung nur eine Gutschrift
     * geben.
     */
    private function paymentId(Order $order): string
    {
        $paymentId = $order->payment_provider_order_id;

        return is_string($paymentId) && $paymentId !== '' ? $paymentId : (string) $order->uuid;
    }

    /**
     * Enthaelt die Bestellung eine Aufladung? Erkannt wird sie am Slug des
     * Aufladeprodukts -- derselbe Wert, den der Controller in den Warenkorb
     * legt.
     */
    private function isTopup(Order $order): bool
    {
        $productId = OneTimeProduct::query()
            ->where('slug', (string) config('wallet.topup_product_slug'))
            ->value('id');

        if ($productId === null) {
            return false;
        }

        return OrderItem::query()
            ->where('order_id', $order->getKey())
            ->where('one_time_product_id', $productId)
            ->exists();
    }

    /**
     * Der Workspace, dessen Wallet aufgeladen wird. Ohne Mandantenbezug gibt es
     * kein Wallet -- die Bestellung bleibt dann unberuehrt.
     */
    private function tenantOf(Order $order): ?Tenant
    {
        if ($order->tenant_id === null) {
            return null;
        }

        return Tenant::query()->withoutGlobalScopes()->find($order->tenant_id);
    }
}
