<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Onboarding;

use App\Constants\WalletOwnerType;
use App\Models\Tenant;
use App\Models\Wallet;
use App\Services\Wallet\PurchaseService;
use App\Support\Money;
use App\Support\PostpaidTerms;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Der 3-Schritte-Player "So wird abgerechnet" (LP-ONBOARD-011).
 *
 * Drei Zustaende des Motivs `call`, je ein Satz dazu: es klingelt, der Anruf
 * wird angenommen (und ab der Mindestdauer abgebucht), oder er wird verpasst
 * (und nach der dritten Fehlanzeige zurueckgebucht). Mehr sagt die Erklaerung
 * nicht -- sie ist der Ersatz fuer die Textwand, nicht ihre bewegte Fassung.
 *
 * **Eigene Komponente, weil sie an drei Stellen steht:** auf dem
 * Abschluss-Screen (LP-ONBOARD-011), im Dialog von Wizard-Schritt 3
 * (LP-ONBOARD-010) und auf der Seite "Rufnummer bestaetigen"
 * (LP-PORTAL-017). Eine Blade-Komponente haette gereicht, solange nur Texte
 * zu setzen sind; Livewire ist es geworden, weil die Werte aus der
 * Konfiguration und die Geldformatierung auf dem Server bleiben sollen -- im
 * Markup steht keine Zahl und kein Betrag.
 *
 * **Diese Komponente rechnet nichts und bucht nichts.** Sie liest
 * ausschliesslich config('lead_calls.*') -- dieselbe Quelle, aus der
 * AttemptClassifier und LeadResolver ihre Schwellwerte nehmen. Steht der
 * Mindestabstand morgen auf drei Stunden, sagt dieser Player es mit.
 *
 * **Zwei Geschichten, je Zahlungsweise (Ticket #3).** Bei Guthaben geht der
 * Preis vom Guthaben ab, die Zahl laeuft herunter. Bei Pay as you go geht
 * nichts ab: Der Preis kommt zuzueglich Aufschlag auf die naechste
 * Abrechnung, und die Zahl ist der offene Betrag, der nach oben laeuft. Den
 * Modus holt die Komponente selbst aus dem Wallet des Mandanten, statt ihn
 * sich von drei Aufrufern mitgeben zu lassen -- sonst erzaehlt sie an einer
 * der drei Stellen die falsche Geschichte, und das faellt dort niemandem auf.
 *
 * Das Auf und Ab zwischen den Schritten gehoert dem Browser
 * (resources/js/modules/billing-explainer.js): Ein Schrittwechsel ist eine
 * Ansicht und kein Zustand, den der Server kennen muesste -- sonst waere jedes
 * "Weiter" eine Anfrage, und die automatische Vorfuehrung eine Anfrage je
 * vier Sekunden.
 */
class BillingExplainer extends Component
{
    /**
     * Der Beispielbetrag, der im Schritt 2 abgebucht und im Schritt 3
     * zurueckgebucht wird -- in Cent.
     *
     * Ein Demo-Wert und kein Preis: Der Abschluss-Screen gibt den Preis seines
     * Beispiel-Leads mit, damit Karte und Player dieselbe Zahl zeigen. Wo der
     * Player ohne Zusammenhang steht (Dialog, Rufnummernseite), bleibt der
     * Vorgabewert stehen.
     */
    #[Locked]
    public int $priceCents = self::DEMO_PRICE_CENTS;

    /**
     * Der Guthabenstand, von dem die Zahl in Schritt 2 herunterlaeuft -- in
     * Cent. Ohne Angabe ein Beispielstand, denn die Erklaerung soll auch dem
     * Kaeufer ohne Guthaben zeigen, was passiert.
     */
    #[Locked]
    public int $balanceCents = self::DEMO_BALANCE_CENTS;

    /**
     * Laeuft die Vorfuehrung von selbst los?
     *
     * Aus, wo der Player im geschlossenen Dialog mit im Markup steht
     * (Wizard-Schritt 3): Er liefe dort hinter der Seite weiter und stuende
     * beim Oeffnen schon auf dem letzten Schritt. Wer Bewegung abbestellt hat,
     * bekommt ohnehin keine -- das entscheidet der Browser.
     */
    #[Locked]
    public bool $autoplay = true;

    /** Preis des Beispiel-Leads, wenn keiner mitgegeben wird. */
    private const DEMO_PRICE_CENTS = 1890;

    /** Guthabenstand des Beispiels, wenn keiner mitgegeben wird. */
    private const DEMO_BALANCE_CENTS = 5000;

    public function render(): View
    {
        $price = max(0, $this->priceCents);
        $wallet = $this->wallet();
        $postpaid = PostpaidTerms::isPostpaid($wallet);

        $surcharge = $postpaid
            ? PurchaseService::surchargeCents($price, app(PurchaseService::class)->surchargePercent($wallet))
            : 0;

        return view('livewire.portal.onboarding.billing-explainer', [
            'autoplay' => $this->autoplay,
            'postpaid' => $postpaid,
            'steps' => $this->steps($price, $surcharge, $postpaid, $wallet),
            'balanceLabel' => $postpaid
                ? __('portal.onboarding.done.explainer.balance_postpaid')
                : __('portal.onboarding.done.explainer.balance'),
            'releaseNote' => __('portal.onboarding.done.explainer.release_note', [
                'attempts' => (int) config('lead_calls.unreachable_attempts'),
            ]),
            // Die Zahlen der Mini-Animation gehen in Cent an den Browser und
            // kommen als Betrag zurueck: Das Herunterlaufen ist eine Reihe von
            // Zwischenstaenden, und jeden davon hier zu formatieren waere eine
            // Liste von hundert Zeichenketten.
            'animation' => $this->animation($price, $surcharge, $postpaid, $wallet),
        ]);
    }

    /**
     * Die Zahl unter den Schritten: Guthaben, das herunterlaeuft -- oder der
     * offene Betrag, der nach oben laeuft.
     *
     * @return array{from: int, to: int, currency: string}
     */
    private function animation(int $price, int $surcharge, bool $postpaid, ?Wallet $wallet): array
    {
        if ($postpaid) {
            // Bei Pay as you go wird nichts abgebucht, es kommt etwas dazu.
            // Eine herunterlaufende Zahl waere hier die falsche Bewegung.
            $open = $wallet instanceof Wallet ? max(0, (int) $wallet->open_amount_cents) : 0;

            return [
                'from' => $open,
                'to' => $open + $price + $surcharge,
                'currency' => Money::symbol(),
            ];
        }

        // Ein Beispiel, in dem das Guthaben nicht reicht, erzaehlt die falsche
        // Geschichte: Die Zahl soll herunterlaufen und nicht ins Minus.
        $balance = max($this->balanceCents, $price);

        return [
            'from' => $balance,
            'to' => $balance - $price,
            'currency' => Money::symbol(),
        ];
    }

    /**
     * Das Kaeufer-Wallet des aktiven Mandanten -- oder null.
     *
     * Bewusst lesend und ohne firstOrCreate: Eine Ansicht legt kein Wallet an.
     * Ohne Mandanten im Kontext (etwa in einem Komponententest) bleibt es
     * null, und der Player erzaehlt die Guthaben-Geschichte.
     */
    private function wallet(): ?Wallet
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        return Wallet::query()
            ->where('owner_type', WalletOwnerType::BUYER->value)
            ->where('owner_id', $tenant->getKey())
            ->first();
    }

    /**
     * Die drei Schritte: Motivzustand, Ueberschrift, Satz.
     *
     * Der zweite und der dritte Satz haengen an der Zahlungsweise: Bei Pay as
     * you go geht nichts vom Guthaben ab und nichts kommt zurueck -- der
     * Betrag kommt zuzueglich Aufschlag auf die Abrechnung oder eben nicht.
     *
     * @return list<array{state: string, title: string, text: string, charge: ?string}>
     */
    private function steps(int $price, int $surcharge, bool $postpaid, ?Wallet $wallet): array
    {
        $total = $price + $surcharge;

        return [
            [
                'state' => 'ringing',
                'title' => __('portal.onboarding.done.explainer.steps.ringing.title'),
                // Der Anfragende sieht die bestaetigte Nummer des Kaeufers:
                // Die Bridge waehlt ihn mit `callerId => caller_number`
                // (CallService::bridgeInstruction), und genau dafuer wird sie
                // bestaetigt. Der Satz hat bis Ticket #3 das Gegenteil
                // behauptet.
                'text' => __('portal.onboarding.done.explainer.steps.ringing.text'),
                'charge' => null,
            ],
            [
                'state' => 'answered',
                'title' => __('portal.onboarding.done.explainer.steps.answered.title', [
                    'seconds' => (int) config('lead_calls.answered_min_seconds'),
                ]),
                'text' => $postpaid
                    ? __('portal.onboarding.done.explainer.steps.answered.text_postpaid', [
                        'seconds' => (int) config('lead_calls.answered_min_seconds'),
                        'amount' => Money::format($price),
                        'percent' => PostpaidTerms::surchargePercentLabel($wallet),
                        'total' => Money::format($total),
                    ])
                    : __('portal.onboarding.done.explainer.steps.answered.text', [
                        'seconds' => (int) config('lead_calls.answered_min_seconds'),
                        'amount' => Money::format($price),
                    ]),
                'charge' => $postpaid
                    ? Money::formatSigned($total)
                    : Money::formatSigned(-$price),
            ],
            [
                'state' => 'missed',
                'title' => __('portal.onboarding.done.explainer.steps.missed.title', [
                    'attempts' => (int) config('lead_calls.unreachable_attempts'),
                ]),
                // Der zweite Satz ist der, der Geld kostet: Laeuft die Frist
                // ohne diese Versuche ab, wird der Lead berechnet
                // (LeadResolutionReason::DEADLINE -> BILLABLE). Bis Ticket #3
                // stand hier nur die kostenlose Haelfte.
                'text' => $postpaid
                    ? __('portal.onboarding.done.explainer.steps.missed.text_postpaid', [
                        'attempts' => (int) config('lead_calls.unreachable_attempts'),
                        'days' => (int) config('lead_calls.unreachable_min_days'),
                        'hours' => (int) config('lead_calls.retry_min_hours'),
                    ])
                    : __('portal.onboarding.done.explainer.steps.missed.text', [
                        'attempts' => (int) config('lead_calls.unreachable_attempts'),
                        'days' => (int) config('lead_calls.unreachable_min_days'),
                        'hours' => (int) config('lead_calls.retry_min_hours'),
                    ]),
                'charge' => $postpaid
                    ? null
                    : Money::formatSigned($price),
            ],
        ];
    }
}
