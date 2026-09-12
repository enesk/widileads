<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Constants\PurchaseStatus;
use App\Constants\WalletTransactionType;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\InvalidPurchaseTransitionException;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Die Geldseite eines Leadkaufs (LP-WALLET-006).
 *
 * Vier Vorgaenge, mehr gibt es nicht: reservieren, abbuchen, aufloesen,
 * erstatten. Jeder von ihnen bewegt Guthaben ausschliesslich ueber
 * WalletService::post() und schreibt im selben Atemzug `lead_purchases.status`
 * fort. Beides gehoert zusammen: Ein Kaufbeleg auf "reserved", dessen
 * Reservierung nie gebucht wurde, waere ein blockierter Lead ohne geblocktes
 * Geld; eine Buchung ohne passenden Stand im Beleg waere Geld ohne Vorgang.
 *
 * Zwei Zustandsmaschinen, die nicht verwechselt werden duerfen: LeadState sagt,
 * wo der Lead steht, PurchaseStatus nur, wo das Geld steht. Dieser Dienst
 * ruehrt den Lead nicht an -- die Verzahnung mit der Kaufstrecke und dem
 * Ereignis LeadResolved machen LP-WALLET-007 und LP-WALLET-008.
 *
 * **Wiederholung ist harmlos.** Jeder Vorgang findet einen Kauf, der schon im
 * Zielzustand steht, und gibt ihn unveraendert zurueck; jede Buchung traegt
 * zusaetzlich einen Idempotenzschluessel nach der Konvention aus
 * WalletService::keyFor(). Ein zweimal zugestellter Job bucht deshalb nicht
 * zweimal ab. Ein Wechsel dagegen, den die Zustandsmaschine nicht kennt -- ein
 * `release` auf einen abgebuchten Kauf --, bricht mit
 * InvalidPurchaseTransitionException ab: Das ist ein Fehler im Aufrufer und
 * wuerde den Saldo des Kaeufers verfaelschen.
 *
 * **Postpaid kostet einen Aufschlag (LP-POSTPAID-007).** Kauft der Kaeufer
 * gegen Kreditrahmen, wird beim Reservieren `surcharge_cents` neben dem
 * Leadpreis festgeschrieben. Das Kaeufer-Wallet bewegt immer Preis plus
 * Aufschlag, die Verkaeuferseite bleibt unberuehrt: Erloes und Provision
 * rechnen weiter gegen `price_cents`, der Aufschlag geht als eigene Buchung
 * vollstaendig an die Plattform.
 *
 * **Der Kauf wird gesperrt gelesen.** Zustandspruefung, Buchungen und
 * Zustandswechsel laufen unter `lockForUpdate()` auf der Kaufzeile. Ohne die
 * Sperre koennten Abbuchung und Aufloesung desselben Kaufs gleichzeitig die
 * Pruefung passieren.
 */
class PurchaseService
{
    public function __construct(private readonly WalletService $wallets) {}

    /**
     * Kauf anlegen und den Kaufpreis auf dem Kaeufer-Wallet blocken.
     *
     * Preis und Provisionssatz werden hier festgeschrieben und danach nie
     * wieder nachgeschlagen (Architekturleitsatz 4): Der Kaeufer hat zu diesem
     * Preis und dieser Aufteilung gekauft, auch wenn der Verkaeufer seinen
     * Preis am naechsten Tag aendert.
     *
     * Beleg und Buchung entstehen in einer Transaktion. Reicht das Guthaben
     * nicht, faellt beides weg -- ein Kaufbeleg ohne Deckung waere ein
     * verschenkter Lead.
     *
     * @throws InsufficientFundsException wenn das freie Guthaben des Kaeufers nicht reicht
     * @throws InvalidPurchaseTransitionException wenn dieser Kaeufer den Lead schon abgerechnet hat
     */
    public function reserve(Lead $lead, Tenant $buyer): LeadPurchase
    {
        $existing = LeadPurchase::query()
            ->where('lead_id', $lead->getKey())
            ->where('buyer_tenant_id', $buyer->getKey())
            ->first();

        if ($existing !== null) {
            return $this->guardTransition($existing, PurchaseStatus::RESERVED, 'reserve');
        }

        $seller = $lead->tenant()->firstOrFail();

        $priceCents = (int) $seller->lead_price_cents;
        $commissionPercent = $this->commissionPercentFor($seller);
        $commissionCents = self::commissionCents($priceCents, $commissionPercent);

        return DB::transaction(function () use ($lead, $buyer, $seller, $priceCents, $commissionPercent, $commissionCents): LeadPurchase {
            $buyerWallet = Wallet::forBuyer($buyer);
            $paymentMode = $buyerWallet->payment_mode;
            $surchargeCents = $paymentMode->hasSurcharge()
                ? self::surchargeCents($priceCents, $this->surchargePercent())
                : 0;

            $purchase = LeadPurchase::query()->create([
                'lead_id' => $lead->getKey(),
                'buyer_tenant_id' => $buyer->getKey(),
                'seller_tenant_id' => $seller->getKey(),
                'price_cents' => $priceCents,
                'commission_percent' => $commissionPercent,
                'commission_cents' => $commissionCents,
                'seller_net_cents' => $priceCents - $commissionCents,
                'surcharge_cents' => $surchargeCents,
                'payment_mode' => $paymentMode,
                'status' => PurchaseStatus::RESERVED,
                'currency' => config('wallet.currency'),
                'purchased_at' => now(),
                'reserved_at' => now(),
            ]);

            $this->wallets->post(
                wallet: $buyerWallet,
                type: WalletTransactionType::RESERVE,
                amountCents: self::buyerTotalCents($purchase),
                description: __('marketplace.wallet.descriptions.reserve', ['lead' => $lead->getKey()]),
                reference: $purchase,
                idempotencyKey: WalletService::keyFor(WalletTransactionType::RESERVE, $purchase),
                meta: $this->metaFor($purchase),
            );

            return $purchase;
        });
    }

    /**
     * Reservierten Kaufpreis abrechnen: Der Kaeufer zahlt, der Verkaeufer
     * bekommt seinen Netto-Erloes, die Plattform ihre Provision.
     *
     * Auf dem Kaeufer-Wallet entstehen bewusst zwei Zeilen: `release` gibt die
     * Reservierung frei, `capture` bucht vom Saldo ab. Nur so bleiben beide
     * Salden -- geblockt und frei -- im Journal nachvollziehbar; eine einzelne
     * Zeile muesste zwei Spalten gleichzeitig bewegen und waere nicht mehr
     * gegen den Saldo pruefbar (LP-WALLET-015).
     *
     * Alles in einer Transaktion: Ein abgebuchter Kaeufer ohne gutgeschriebenen
     * Verkaeufer waere Geld, das die Plattform stillschweigend behaelt.
     *
     * @throws InvalidPurchaseTransitionException wenn der Kauf nicht reserviert ist
     */
    public function capture(LeadPurchase $purchase): LeadPurchase
    {
        $captured = $this->transition($purchase, PurchaseStatus::RESERVED, PurchaseStatus::CAPTURED, 'capture', 'captured_at', function (LeadPurchase $locked): void {
            $buyerWallet = Wallet::forBuyer($locked->buyer);
            $leadId = $locked->lead_id;

            $this->wallets->post(
                wallet: $buyerWallet,
                type: WalletTransactionType::RELEASE,
                amountCents: -self::buyerTotalCents($locked),
                description: __('marketplace.wallet.descriptions.release_for_capture', ['lead' => $leadId]),
                reference: $locked,
                // Suffix, weil auch die Aufloesung eines geplatzten Kaufs eine
                // `release`-Zeile zu demselben Beleg schreibt.
                idempotencyKey: WalletService::keyFor(WalletTransactionType::RELEASE, $locked, 'capture'),
                meta: $this->metaFor($locked),
            );

            $this->wallets->post(
                wallet: $buyerWallet,
                type: WalletTransactionType::CAPTURE,
                amountCents: -self::buyerTotalCents($locked),
                description: __('marketplace.wallet.descriptions.capture', ['lead' => $leadId]),
                reference: $locked,
                idempotencyKey: WalletService::keyFor(WalletTransactionType::CAPTURE, $locked),
                meta: $this->metaFor($locked),
            );

            // Betrag null wird nicht gebucht: Bei einer Provision von 0 oder
            // 100 Prozent gaebe es sonst eine Zeile ohne Wirkung, und der
            // WalletService weist sie zu Recht ab.
            if ($locked->seller_net_cents > 0) {
                $this->wallets->post(
                    wallet: Wallet::forSeller($locked->seller),
                    type: WalletTransactionType::EARNING,
                    amountCents: $locked->seller_net_cents,
                    description: __('marketplace.wallet.descriptions.earning', ['lead' => $leadId]),
                    reference: $locked,
                    idempotencyKey: WalletService::keyFor(WalletTransactionType::EARNING, $locked),
                    meta: $this->metaFor($locked),
                );
            }

            if ($locked->commission_cents > 0) {
                $this->wallets->post(
                    wallet: Wallet::forPlatform(),
                    type: WalletTransactionType::COMMISSION,
                    amountCents: $locked->commission_cents,
                    description: __('marketplace.wallet.descriptions.commission', ['lead' => $leadId]),
                    reference: $locked,
                    idempotencyKey: WalletService::keyFor(WalletTransactionType::COMMISSION, $locked),
                    meta: $this->metaFor($locked),
                );
            }

            // Der Aufschlag ist Plattformertrag wie die Provision, wird aber
            // bewusst als eigene Zeile gebucht (LP-POSTPAID-007): Nur so ist
            // im Journal ablesbar, was Pay as you go einbringt, ohne es aus
            // der Provision herausrechnen zu muessen.
            if ($locked->surcharge_cents > 0) {
                $this->wallets->post(
                    wallet: Wallet::forPlatform(),
                    type: WalletTransactionType::SURCHARGE,
                    amountCents: $locked->surcharge_cents,
                    description: __('marketplace.wallet.descriptions.surcharge', ['lead' => $leadId]),
                    reference: $locked,
                    idempotencyKey: WalletService::keyFor(WalletTransactionType::SURCHARGE, $locked),
                    meta: $this->metaFor($locked),
                );
            }
        });

        $this->checkSettlementThreshold($captured);

        return $captured;
    }

    /**
     * Reservierung aufloesen: Der Kauf kommt nicht zustande, der Kaeufer zahlt
     * nichts. Es bewegt sich nur `reserved_cents` -- abgebucht war ja nie
     * etwas.
     *
     * @throws InvalidPurchaseTransitionException wenn der Kauf nicht reserviert ist
     */
    public function release(LeadPurchase $purchase): LeadPurchase
    {
        return $this->transition($purchase, PurchaseStatus::RESERVED, PurchaseStatus::RELEASED, 'release', 'released_at', function (LeadPurchase $locked): void {
            $this->wallets->post(
                wallet: Wallet::forBuyer($locked->buyer),
                type: WalletTransactionType::RELEASE,
                amountCents: -self::buyerTotalCents($locked),
                description: __('marketplace.wallet.descriptions.release', ['lead' => $locked->lead_id]),
                reference: $locked,
                idempotencyKey: WalletService::keyFor(WalletTransactionType::RELEASE, $locked),
                meta: $this->metaFor($locked),
            );
        });
    }

    /**
     * Abgerechneten Kauf zurueckabwickeln: Der Kaeufer bekommt sein Geld,
     * Verkaeufer und Plattform geben Erloes und Provision wieder her.
     *
     * Das Verkaeufer-Wallet darf dabei ins Minus laufen. Der Erloes kann
     * laengst ausgezahlt sein; die Erstattung daran scheitern zu lassen, hiesse
     * den Kaeufer fuer die Liquiditaet des Verkaeufers haften zu lassen. Das
     * Minus wird dem Admin in der Wallet-Uebersicht gezeigt (LP-WALLET-013).
     *
     * Grund und handelnder Admin stehen in den Buchungen: Eine Erstattung ist
     * eine Entscheidung, keine Automatik, und muss zurechenbar bleiben.
     *
     * @throws InvalidPurchaseTransitionException wenn der Kauf nicht abgebucht ist
     */
    public function refund(LeadPurchase $purchase, string $reason, User $admin): LeadPurchase
    {
        return $this->transition($purchase, PurchaseStatus::CAPTURED, PurchaseStatus::REFUNDED, 'refund', 'refunded_at', function (LeadPurchase $locked) use ($reason, $admin): void {
            $leadId = $locked->lead_id;
            $meta = $this->metaFor($locked) + [
                'reason' => $reason,
                'refunded_by' => (int) $admin->getKey(),
            ];

            $this->wallets->post(
                wallet: Wallet::forBuyer($locked->buyer),
                type: WalletTransactionType::REFUND,
                amountCents: self::buyerTotalCents($locked),
                description: __('marketplace.wallet.descriptions.refund', ['lead' => $leadId]),
                reference: $locked,
                idempotencyKey: WalletService::keyFor(WalletTransactionType::REFUND, $locked),
                meta: $meta,
                createdBy: (int) $admin->getKey(),
            );

            if ($locked->seller_net_cents > 0) {
                $this->wallets->post(
                    wallet: Wallet::forSeller($locked->seller),
                    type: WalletTransactionType::EARNING,
                    amountCents: -$locked->seller_net_cents,
                    description: __('marketplace.wallet.descriptions.earning_reversal', ['lead' => $leadId]),
                    reference: $locked,
                    idempotencyKey: WalletService::keyFor(WalletTransactionType::EARNING, $locked, 'refund'),
                    meta: $meta,
                    allowNegative: true,
                    createdBy: (int) $admin->getKey(),
                );
            }

            if ($locked->commission_cents > 0) {
                $this->wallets->post(
                    wallet: Wallet::forPlatform(),
                    type: WalletTransactionType::COMMISSION,
                    amountCents: -$locked->commission_cents,
                    description: __('marketplace.wallet.descriptions.commission_reversal', ['lead' => $leadId]),
                    reference: $locked,
                    idempotencyKey: WalletService::keyFor(WalletTransactionType::COMMISSION, $locked, 'refund'),
                    meta: $meta,
                    allowNegative: true,
                    createdBy: (int) $admin->getKey(),
                );
            }

            if ($locked->surcharge_cents > 0) {
                $this->wallets->post(
                    wallet: Wallet::forPlatform(),
                    type: WalletTransactionType::SURCHARGE,
                    amountCents: -$locked->surcharge_cents,
                    description: __('marketplace.wallet.descriptions.surcharge_reversal', ['lead' => $leadId]),
                    reference: $locked,
                    idempotencyKey: WalletService::keyFor(WalletTransactionType::SURCHARGE, $locked, 'refund'),
                    meta: $meta,
                    allowNegative: true,
                    createdBy: (int) $admin->getKey(),
                );
            }
        });
    }

    /**
     * Provision in Cent, kaufmaennisch gerundet.
     *
     * Gerundet wird ausschliesslich die Provision; der Erloes des Verkaeufers
     * ist der Rest. So geht die Summe immer exakt auf -- eine zweite Rundung
     * koennte einen Cent erfinden oder verschlucken, und der Ledger waere um
     * diesen Cent falsch.
     */
    public static function commissionCents(int $priceCents, float $commissionPercent): int
    {
        return (int) round($priceCents * $commissionPercent / 100, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Wirksamer Provisionssatz: der Satz des Verkaeufers, sonst der Vorgabesatz
     * der Plattform.
     */
    public function commissionPercentFor(Tenant $seller): float
    {
        return $seller->commission_percent !== null
            ? (float) $seller->commission_percent
            : (float) config('wallet.commission_percent');
    }

    /**
     * Aufschlag in Cent, gerundet wie die Provision (kaufmaennisch, ganze
     * Cent). Der Aufschlag steht neben dem Leadpreis und nicht darin: Erloes
     * des Verkaeufers und Provision rechnen weiter gegen `price_cents`, der
     * Aufschlag geht vollstaendig an die Plattform.
     */
    public static function surchargeCents(int $priceCents, float $surchargePercent): int
    {
        return (int) round($priceCents * $surchargePercent / 100, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Wirksamer Aufschlagsatz der Plattform in Prozent.
     */
    public function surchargePercent(): float
    {
        return (float) config('wallet.postpaid.surcharge_percent');
    }

    /**
     * Der Betrag, den das Kaeufer-Wallet traegt: Leadpreis plus Aufschlag.
     *
     * Reservierung, Aufloesung, Abbuchung und Erstattung bewegen immer diesen
     * Betrag -- eine Abbuchung ueber den Leadpreis allein liesse den Aufschlag
     * als Rest in `reserved_cents` stehen. Bei einem Prepaid-Kauf ist der
     * Aufschlag 0 und die Rechnung bleibt die alte.
     */
    public static function buyerTotalCents(LeadPurchase $purchase): int
    {
        return (int) $purchase->price_cents + (int) $purchase->surcharge_cents;
    }

    /**
     * Nach jeder Abbuchung auf einem Postpaid-Wallet pruefen, ob der offene
     * Betrag die Sofort-Einzugsschwelle erreicht hat (LP-POSTPAID-007).
     *
     * Die Pruefung laeuft ausserhalb der Buchungstransaktion: Ein Einzug ist
     * ein Aussenvorgang, und ein Fehler dort darf die bereits erfolgte
     * Abrechnung des Leads nicht zurueckdrehen.
     *
     * Der SettlementService entsteht in LP-POSTPAID-008; bis dahin faellt der
     * Aufruf still aus, statt jeden Kauf an einer fehlenden Klasse scheitern
     * zu lassen.
     */
    private function checkSettlementThreshold(LeadPurchase $purchase): void
    {
        if ($purchase->status !== PurchaseStatus::CAPTURED) {
            return;
        }

        $wallet = Wallet::forBuyer($purchase->buyer);

        if (! $wallet->isPostpaid()) {
            return;
        }

        if (! class_exists(SettlementService::class)) {
            return;
        }

        app(SettlementService::class)->checkThreshold($wallet);
    }

    /**
     * Fuehrt einen Zustandswechsel samt seinen Buchungen aus.
     *
     * Der Kauf wird gesperrt neu geladen, weil zwischen Aufruf und Buchung ein
     * anderer Prozess denselben Kauf abgerechnet haben kann. Steht er dann
     * schon im Zielzustand, ist der Aufruf eine Wiederholung und gibt den
     * bestehenden Stand zurueck.
     *
     * @param  Closure(LeadPurchase): void  $book
     */
    private function transition(
        LeadPurchase $purchase,
        PurchaseStatus $from,
        PurchaseStatus $to,
        string $operation,
        string $timestampColumn,
        Closure $book,
    ): LeadPurchase {
        if ($purchase->status === $to) {
            return $purchase;
        }

        return DB::transaction(function () use ($purchase, $from, $to, $operation, $timestampColumn, $book): LeadPurchase {
            $locked = LeadPurchase::query()->whereKey($purchase->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === $to) {
                $purchase->setRawAttributes($locked->getAttributes(), true);

                return $purchase;
            }

            if ($locked->status !== $from) {
                throw InvalidPurchaseTransitionException::for($locked, $from, $operation);
            }

            $book($locked);

            $locked->forceFill([
                'status' => $to,
                $timestampColumn => now(),
            ])->save();

            $purchase->setRawAttributes($locked->getAttributes(), true);

            return $purchase;
        });
    }

    /**
     * Ein Kauf, den es schon gibt: im Zielzustand eine Wiederholung, sonst ein
     * Wechsel ausserhalb der Zustandsmaschine.
     */
    private function guardTransition(LeadPurchase $purchase, PurchaseStatus $expected, string $operation): LeadPurchase
    {
        if ($purchase->status !== $expected) {
            throw InvalidPurchaseTransitionException::for($purchase, $expected, $operation);
        }

        return $purchase;
    }

    /**
     * Angaben, die jede Buchung eines Kaufs mitfuehrt. Der Provisionssatz
     * gehoert dazu, weil er sich aendern kann: Ohne ihn waere spaeter nicht
     * mehr erklaerbar, wie sich dieser eine Preis geteilt hat.
     *
     * @return array<string, mixed>
     */
    private function metaFor(LeadPurchase $purchase): array
    {
        return [
            'lead_id' => (int) $purchase->lead_id,
            'price_cents' => (int) $purchase->price_cents,
            'commission_percent' => (float) $purchase->commission_percent,
            'commission_cents' => (int) $purchase->commission_cents,
            'seller_net_cents' => (int) $purchase->seller_net_cents,
            'surcharge_cents' => (int) $purchase->surcharge_cents,
            'payment_mode' => $purchase->payment_mode->value,
        ];
    }
}
