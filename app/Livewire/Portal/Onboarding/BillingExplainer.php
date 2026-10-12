<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Onboarding;

use App\Support\Money;
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

        // Ein Beispiel, in dem das Guthaben nicht reicht, erzaehlt die falsche
        // Geschichte: Die Zahl soll herunterlaufen und nicht ins Minus.
        $balance = max($this->balanceCents, $price);

        return view('livewire.portal.onboarding.billing-explainer', [
            'autoplay' => $this->autoplay,
            'steps' => $this->steps($price, $balance),
            // Die Zahlen der Mini-Animation gehen in Cent an den Browser und
            // kommen als Betrag zurueck: Das Herunterlaufen ist eine Reihe von
            // Zwischenstaenden, und jeden davon hier zu formatieren waere eine
            // Liste von hundert Zeichenketten.
            'animation' => [
                'from' => $balance,
                'to' => $balance - $price,
                'currency' => Money::symbol(),
            ],
        ]);
    }

    /**
     * Die drei Schritte: Motivzustand, Ueberschrift, Satz.
     *
     * @return list<array{state: string, title: string, text: string, charge: ?string}>
     */
    private function steps(int $price, int $balance): array
    {
        return [
            [
                'state' => 'ringing',
                'title' => __('portal.onboarding.done.explainer.steps.ringing.title'),
                'text' => __('portal.onboarding.done.explainer.steps.ringing.text'),
                'charge' => null,
            ],
            [
                'state' => 'answered',
                'title' => __('portal.onboarding.done.explainer.steps.answered.title', [
                    'seconds' => (int) config('lead_calls.answered_min_seconds'),
                ]),
                'text' => __('portal.onboarding.done.explainer.steps.answered.text', [
                    'seconds' => (int) config('lead_calls.answered_min_seconds'),
                    'amount' => Money::format($price),
                ]),
                'charge' => Money::formatSigned(-$price),
            ],
            [
                'state' => 'missed',
                'title' => __('portal.onboarding.done.explainer.steps.missed.title', [
                    'attempts' => (int) config('lead_calls.unreachable_attempts'),
                ]),
                'text' => __('portal.onboarding.done.explainer.steps.missed.text', [
                    'attempts' => (int) config('lead_calls.unreachable_attempts'),
                    'days' => (int) config('lead_calls.unreachable_min_days'),
                    'hours' => (int) config('lead_calls.retry_min_hours'),
                ]),
                'charge' => Money::formatSigned($price),
            ],
        ];
    }
}
