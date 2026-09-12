<?php

declare(strict_types=1);

namespace App\Actions;

use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Events\Lead\LeadPurchased;
use App\Exceptions\IllegalLeadTransition;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\LeadNotPurchasableException;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Scopes\TenantScopes;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\LeadStateService;
use App\Services\TenantTypeService;
use App\Services\Wallet\PurchaseService;
use Illuminate\Support\Facades\DB;

/**
 * Der Kauf eines Leads durch einen Kaeufer-Mandanten (FB-054, Geldseite seit
 * LP-WALLET-007).
 *
 * Der Vorgang laeuft in zwei Schritten, und das ist Absicht.
 *
 * **Erst reservieren, dann kaufen.** Die Reservierung ist eine eigene,
 * abgeschlossene Transaktion. Erst dadurch sieht ein zweiter Kaeufer sofort,
 * dass der Lead vergeben ist -- laege sie in derselben Transaktion wie der
 * Kauf, waere sie bis zum Ende unsichtbar, und zwei gleichzeitige Klicks
 * wuerden beide bis zur Guthabenpruefung laufen. Wer die Reservierung gewinnt,
 * entscheidet die Zustandsmaschine: LeadStateService::transition() sperrt den
 * Lead und weist einen Wechsel ab, dessen Ausgangszustand sich inzwischen
 * geaendert hat. Genau einer gewinnt.
 *
 * **Kauf, Geldreservierung und Zustandswechsel in einer Transaktion.** Die
 * Geldseite gehoert seit LP-WALLET-006 vollstaendig dem PurchaseService: Er
 * legt den Kaufbeleg an, schreibt Preis und Provision fest und blockt den
 * Kaufpreis auf dem Kaeufer-Wallet. Diese Action ruehrt weder Wallet noch
 * Ledger an -- sie klammert den Geldvorgang mit dem Lead zusammen. Ein
 * reservierter Betrag ohne zugeordneten Lead waere Geld, das der Kaeufer nicht
 * mehr ausgeben kann, ohne etwas dafuer bekommen zu haben.
 *
 * **Scheitert der Kauf, wird die Reservierung zurueckgegeben.** Reicht das
 * Guthaben nicht, bliebe der Lead sonst bis zum Ablauf der Frist blockiert,
 * obwohl niemand ihn kaufen wird.
 *
 * **Der angezeigte Preis wird geprueft.** Der Kaeufer kauft zu dem Preis, den
 * die Seite ihm genannt hat. Erhoeht der Verkaeufer seinen Preis, waehrend die
 * Seite noch offen steht, wird der Kauf abgewiesen statt stillschweigend teurer
 * abgerechnet.
 */
class PurchaseLead
{
    public function __construct(
        private readonly LeadStateService $states,
        private readonly PurchaseService $purchases,
        private readonly TenantTypeService $tenantTypes,
    ) {}

    /**
     * Gemerkter Zahlungsmodus je Kaeufer-Mandant. Der Marktplatz erfragt den
     * Preis fuer jeden Lead der Liste einzeln; ohne das Merken waere das eine
     * Wallet-Abfrage je Zeile.
     *
     * @var array<int, bool>
     */
    private array $buysOnCredit = [];

    /**
     * Ohne handelnden Benutzer gekauft wird beim Autokauf (FB-056) -- dort
     * entscheidet das Kaufprofil, nicht ein Mensch. Das Zustandsprotokoll
     * traegt dann keinen Akteur, was genau richtig ist: Es war keiner.
     *
     * @param  int|null  $priceShownCents  Preis, den die Oberflaeche dem Kaeufer genannt hat; null beim Autokauf
     *
     * @throws LeadNotPurchasableException wenn der Kaeufer oder der Lead nicht in Frage kommt
     * @throws InsufficientFundsException wenn das verfuegbare Guthaben nicht reicht
     */
    public function handle(Tenant $buyer, Lead $lead, ?User $actor = null, ?int $priceShownCents = null): LeadPurchase
    {
        $this->guardBuyer($buyer);
        $this->guardLead($buyer, $lead);
        $this->guardPrice($lead, $priceShownCents, $buyer);

        $this->reserve($buyer, $lead, $actor);

        try {
            $purchase = $this->settle($buyer, $lead, $actor, $priceShownCents);
        } catch (\Throwable $exception) {
            // Ein Lead, den niemand kaufen kann, soll nicht bis zum Ablauf der
            // Frist blockiert bleiben.
            $this->release($lead, $actor);

            throw $exception;
        }

        event(new LeadPurchased($lead, $buyer, $purchase, $actor, automatic: $actor === null));

        return $purchase;
    }

    /**
     * Legt den Lead auf diesen Kaeufer fest.
     *
     * Bei zwei gleichzeitigen Klicks kommt genau einer durch; der andere
     * bekommt eine IllegalLeadTransition, weil der Ausgangszustand beim
     * Sperren nicht mehr `verfuegbar` ist.
     *
     * @throws LeadNotPurchasableException wenn ein anderer Kaeufer schneller war
     */
    private function reserve(Tenant $buyer, Lead $lead, ?User $actor): void
    {
        try {
            DB::transaction(function () use ($buyer, $lead, $actor): void {
                $this->states->transition(
                    $lead,
                    LeadState::RESERVIERT,
                    LeadTransitionReason::RESERVED_BY_BUYER,
                    $actor,
                    ['buyer_tenant_id' => $buyer->getKey()],
                );

                $lead->update([
                    'reserved_by' => $buyer->getKey(),
                    'reserved_until' => now()->addMinutes((int) config('funnel.lead.reservation_ttl')),
                ]);
            });
        } catch (IllegalLeadTransition $exception) {
            throw LeadNotPurchasableException::alreadyTaken($exception);
        }
    }

    /**
     * Kaufbeleg samt Geldreservierung und Zustandswechsel -- alles oder nichts.
     */
    private function settle(Tenant $buyer, Lead $lead, ?User $actor, ?int $priceShownCents): LeadPurchase
    {
        return DB::transaction(function () use ($buyer, $lead, $actor, $priceShownCents): LeadPurchase {
            // Der Lead ist reserviert; die Sperre haelt ihn bis zum Ende der
            // Transaktion fest.
            // Ohne Mandanten-Scope: Der Kauf laeuft im Kontext des Kaeufers,
            // der Lead gehoert dem Betreiber. Mit Scope faende diese Abfrage
            // den Lead nicht -- und zwar mit einer Ausnahme, nicht mit einer
            // Fehlermeldung, die einem Kaeufer etwas sagt (FB-055a).
            $locked = Lead::query()
                ->withoutGlobalScopes(TenantScopes::names())
                ->whereKey($lead->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->lead_state !== LeadState::RESERVIERT
                || (int) $locked->reserved_by !== (int) $buyer->getKey()) {
                throw LeadNotPurchasableException::alreadyTaken();
            }

            // Zwischen Anzeige und Sperre kann der Verkaeufer seinen Preis
            // geaendert haben; unter der Sperre ist die Pruefung verbindlich.
            $this->guardPrice($locked, $priceShownCents, $buyer);

            // Legt den Beleg an, schreibt Preis, Provisionssatz und
            // Verkaeuferanteil fest und blockt den Kaufpreis auf dem
            // Kaeufer-Wallet. Reicht das verfuegbare Guthaben nicht, wirft der
            // Dienst eine InsufficientFundsException -- dann faellt die
            // gesamte Transaktion zurueck, samt Kaufbeleg.
            $purchase = $this->purchases->reserve($locked, $buyer);

            // Mit dem ersten Kauf ist der Lead ausgeliefert -- ab hier laeuft
            // die Frist der Erreichbarkeitspruefung (FB-084, Ticket #11). Bei
            // einem geteilten Lead (FB-055) zaehlt der erste Kauf: Die Frist
            // gehoert dem Lead, nicht dem einzelnen Kaeufer, genau wie die
            // Versuche im LeadResolver leadweit gezaehlt werden.
            if ($locked->delivered_at === null) {
                $deliveredAt = $purchase->purchased_at ?? now();

                $locked->forceFill([
                    'delivered_at' => $deliveredAt,
                    'deadline_at' => $deliveredAt->copy()
                        ->addDays((int) config('lead_calls.deadline_days')),
                ])->save();

                // Die Instanz des Aufrufers auf denselben Stand bringen, ohne
                // sie beim naechsten update() erneut schreiben zu lassen.
                $lead->forceFill($locked->only(['delivered_at', 'deadline_at']))
                    ->syncOriginalAttributes(['delivered_at', 'deadline_at']);
            }

            $maxBuyers = $this->funnelOf($lead)?->effectiveMaxBuyers() ?? 1;
            $sold = LeadPurchase::query()->where('lead_id', $lead->getKey())->count();
            $slotsLeft = max(0, $maxBuyers - $sold);

            // Im Mehrfachverkauf bleibt der Lead im Angebot, bis die
            // Hoechstzahl erreicht ist (FB-055). Der Grund der Buchung bleibt
            // trotzdem "gekauft" -- es ist ein Kauf, der ihn zurueckgibt, keine
            // aufgegebene Reservierung. Ohne diese Unterscheidung waere im
            // Protokoll spaeter nicht mehr zu sehen, dass an dieser Stelle Geld
            // geflossen ist.
            $this->states->transition(
                $lead,
                $slotsLeft > 0 ? LeadState::VERFUEGBAR : LeadState::VERKAUFT,
                LeadTransitionReason::PURCHASED,
                $actor,
                [
                    'buyer_tenant_id' => $buyer->getKey(),
                    'lead_purchase_id' => $purchase->getKey(),
                    'buyers' => $sold,
                    'max_buyers' => $maxBuyers,
                ],
            );

            // Die Reservierung hat ihren Zweck erfuellt. Bleibt der Lead im
            // Angebot, muss auch der Kaeufer daraus verschwinden -- sonst
            // haengt an einem verfuegbaren Lead ein fremder Name.
            $lead->update($slotsLeft > 0
                ? ['reserved_by' => null, 'reserved_until' => null]
                : ['reserved_until' => null]);

            return $purchase;
        });
    }

    /**
     * Gibt eine Reservierung zurueck, deren Kauf nicht zustande kam.
     */
    private function release(Lead $lead, ?User $actor): void
    {
        $lead->refresh();

        if ($lead->lead_state !== LeadState::RESERVIERT) {
            return;
        }

        DB::transaction(function () use ($lead, $actor): void {
            $this->states->transition(
                $lead,
                LeadState::VERFUEGBAR,
                LeadTransitionReason::RESERVATION_RELEASED,
                $actor,
            );

            $lead->update(['reserved_by' => null, 'reserved_until' => null]);
        });
    }

    /**
     * Der Preis, den der Kaeufer gesehen hat, muss der Preis sein, den der
     * Verkaeufer heute verlangt.
     *
     * Ohne diese Klammer koennte ein Verkaeufer den Preis erhoehen, waehrend
     * der Kaeufer die Marktplatzseite noch offen hat -- der Klick buchte dann
     * mehr ab als angezeigt. Beim Autokauf (FB-056) gibt es keine angezeigte
     * Seite; dort entscheidet das Kaufprofil und die Pruefung entfaellt.
     *
     * Bei einem Postpaid-Kaeufer (LP-POSTPAID-007) zahlt der Kaeufer Leadpreis
     * plus Aufschlag. Geprueft wird deshalb gegen den Gesamtpreis; der reine
     * Leadpreis bleibt zusaetzlich zulaessig, solange eine Oberflaeche noch
     * ohne Aufschlag auszeichnet. Beide Werte leiten sich aus demselben
     * heutigen Verkaeuferpreis ab -- die Klammer gegen eine Preisaenderung
     * waehrend der offenen Seite bleibt damit unveraendert wirksam.
     *
     * @throws LeadNotPurchasableException wenn sich der Preis geaendert hat
     */
    private function guardPrice(Lead $lead, ?int $priceShownCents, Tenant $buyer): void
    {
        if ($priceShownCents === null) {
            return;
        }

        $netCents = $this->currentPriceCentsOf($lead);
        $totalCents = $this->currentPriceCentsOf($lead, $buyer);

        if (! in_array($priceShownCents, [$netCents, $totalCents], true)) {
            throw LeadNotPurchasableException::priceChanged();
        }
    }

    /**
     * Der heute gueltige Verkaufspreis dieses Leads.
     *
     * Massgeblich ist die Preisvorgabe des Verkaeufers (LP-WALLET-003) -- also
     * des Mandanten, dem der Lead gehoert. Festgeschrieben wird der Preis erst
     * im Kaufbeleg durch den PurchaseService.
     *
     * Mit Kaeufer gerechnet kommt bei Pay as you go der Aufschlag hinzu
     * (LP-POSTPAID-007): Das ist der Betrag, den dieser Kaeufer tatsaechlich
     * traegt, und damit der Betrag, den seine Oberflaeche anzeigen muss.
     */
    public function currentPriceCentsOf(Lead $lead, ?Tenant $buyer = null): int
    {
        $seller = $lead->tenant;

        $priceCents = $seller instanceof Tenant
            ? (int) $seller->lead_price_cents
            : (int) config('wallet.default_lead_price_cents');

        if ($buyer === null || ! $this->buysOnCredit($buyer)) {
            return $priceCents;
        }

        return $priceCents + PurchaseService::surchargeCents($priceCents, $this->purchases->surchargePercent());
    }

    /**
     * Kauft dieser Mandant gegen Kreditrahmen?
     */
    private function buysOnCredit(Tenant $buyer): bool
    {
        $key = (int) $buyer->getKey();

        return $this->buysOnCredit[$key] ??= Wallet::forBuyer($buyer)->isPostpaid();
    }

    /**
     * Der Funnel eines Leads -- ohne Mandanten-Scope.
     *
     * Der Kauf laeuft im Kontext des KAEUFERS, der Funnel gehoert aber dem
     * BETREIBER. Ueber die gewoehnliche Beziehung zielte der Scope aus FB-010
     * auf die tenant_id des Kaeufers und lieferte null -- ohne Fehler, einfach
     * nichts. Die Verkaufsart waere damit immer als `exclusive` gelesen worden
     * und der Mehrfachverkauf haette nie gegriffen (FB-055a).
     *
     * Bewusst hier und benannt am Scope vorbei, nicht global aufgeweicht: Der
     * Scope ist an jeder anderen Stelle richtig.
     */
    private function funnelOf(Lead $lead): ?Funnel
    {
        if ($lead->funnel_id === null) {
            return null;
        }

        $funnel = Funnel::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->find($lead->funnel_id);

        return $funnel instanceof Funnel ? $funnel : null;
    }

    /**
     * @throws LeadNotPurchasableException
     */
    private function guardBuyer(Tenant $buyer): void
    {
        if (! $this->tenantTypes->canAccessMarketplace($buyer)) {
            throw LeadNotPurchasableException::buyerNotApproved();
        }
    }

    /**
     * @throws LeadNotPurchasableException
     */
    private function guardLead(Tenant $buyer, Lead $lead): void
    {
        if ($lead->lead_state !== LeadState::VERFUEGBAR) {
            throw LeadNotPurchasableException::alreadyTaken();
        }

        if ((int) $lead->tenant_id === (int) $buyer->getKey()) {
            throw LeadNotPurchasableException::ownLead();
        }

        // Im Mehrfachverkauf bleibt der Lead im Angebot -- auch fuer den, der
        // ihn schon hat. Zweimal denselben Lead zu kaufen waere fuer den
        // Kaeufer nichts als eine zweite Abbuchung.
        $alreadyBought = LeadPurchase::query()
            ->where('lead_id', $lead->getKey())
            ->where('buyer_tenant_id', $buyer->getKey())
            ->exists();

        if ($alreadyBought) {
            throw LeadNotPurchasableException::alreadyBought();
        }
    }
}
