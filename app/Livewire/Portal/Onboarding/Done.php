<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Onboarding;

use App\Constants\OnboardingStep;
use App\Dto\LeadContact;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Models\Wallet;
use App\Services\Onboarding\OnboardingService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Der Abschluss-Screen des Onboardings (LP-ONBOARD-011).
 *
 * Die letzte Seite vor dem Marktplatz. Sie verlangt nichts mehr und speichert
 * nichts: Wer hier steht, hat den Wizard hinter sich und soll einmal gesehen
 * haben, wie ein Lead aussieht, warum die Rufnummer verdeckt ist und wann Geld
 * abgebucht wird.
 *
 * **Der Beispiel-Lead ist ein statisches Feld und kein Datensatz.** Links steht
 * eine Karte in der Form, die der Marktplatz zeigt, mit erkennbar erfundenen
 * Daten ("Max Beispiel, PLZ 76437") und einer Pille "Beispiel". Einen
 * echten Lead zu zeigen hiesse, einem Kaeufer ohne Kaufkriterien und ohne
 * Guthaben eine Anfrage vorzuhalten, die er nicht kaufen kann -- und einen
 * Demo-Datensatz in die Datenbank zu legen hiesse, ihn im Marktplatz, in den
 * Statistiken und in der Zuteilung wieder herausfiltern zu muessen.
 *
 * Die verdeckte Rufnummer kommt trotzdem aus dem echten Weg
 * (LeadContact::maskPhoneToLastDigits): Das Beispiel soll zeigen, was der
 * Kaeufer gleich wirklich sieht, und nicht eine nachgebaute Maske.
 *
 * **Rechts erklaert der Player, wie abgerechnet wird** -- eine eigene
 * Komponente (BillingExplainer), weil derselbe Player im Dialog von
 * Wizard-Schritt 3 und auf der Rufnummernseite steht. Seine Zahlen stehen in
 * config('lead_calls.*'); hier steht keine Frist und kein Schwellwert.
 *
 * **Der Weg hinaus** ist "Zum Marktplatz" mit `tour=marketplace`: Die gefuehrte
 * Tour (LP-ONBOARD-012) liest den Parameter dort. Wer sich fuer "Spaeter, erst
 * mal umschauen" entschieden hat und noch kein Guthaben hat, bekommt daneben
 * den Verweis auf die Aufladung -- als Nebenweg und nicht als Sperre: Umschauen
 * darf er, kaufen kann er dann noch nicht.
 */
#[Layout('components.layouts.portal-focus')]
class Done extends Component
{
    use InteractsWithPortalTenant;

    /**
     * Der Beispiel-Lead. Erkennbar erfunden -- Name und Ort sollen niemandem
     * als echte Anfrage erscheinen.
     *
     * Der Preis steht hier ebenfalls fest und wandert in den Player, damit
     * Karte und Abrechnungs-Erklaerung dieselbe Zahl zeigen.
     */
    private const DEMO_PRICE_CENTS = 1890;

    private const DEMO_PHONE = '+4917212345678';

    public function render(): View
    {
        $tenant = $this->portalTenant();
        $wallet = Wallet::forBuyer($tenant);

        $meta = app(OnboardingService::class)->meta($tenant, OnboardingStep::PAYMENT_SETUP) ?? [];

        return view('livewire.portal.onboarding.done', [
            'demoLead' => $this->demoLead(),
            'demoPriceCents' => self::DEMO_PRICE_CENTS,
            'balanceCents' => $wallet->available_cents,
            'marketplaceUrl' => route('portal.marketplace', [
                'tenant' => $tenant->uuid,
                // Der Parameter, auf den die gefuehrte Tour wartet
                // (LP-ONBOARD-012). Steht sie noch nicht, ist er ein
                // unbeachteter Anhang und keine Fehlerquelle.
                'tour' => 'marketplace',
            ]),
            'topUpUrl' => route('portal.wallet', ['tenant' => $tenant->uuid]),
            // Der Verweis auf die Aufladung nur fuer den, der sich vertagt hat
            // UND noch kein Guthaben hat. Bei Pay as you go waere er falsch:
            // Dort wird nichts im Voraus eingezahlt.
            'showTopUp' => ($meta['mode'] ?? null) === 'deferred' && $wallet->available_cents <= 0,
            'deadlineDays' => (int) config('lead_calls.deadline_days'),
            'unreachableAttempts' => (int) config('lead_calls.unreachable_attempts'),
        ])->title(__('portal.onboarding.done.title'));
    }

    /**
     * Die Daten der Beispielkarte.
     *
     * Die Feldnamen sind die von x-app.lead-card (Ticket #20): funnel_name und
     * phone_hint statt funnel und eines eigenen Satzes im Markup. Eine Stadt
     * fuehrt die Karte nicht -- im Marktplatz steht dort "PLZ 76437", und
     * dieselbe Zeile soll das Beispiel zeigen.
     *
     * @return array{name: string, funnel_name: string, funnel_icon: string, postal_code: string, chips: list<string>, phone: string, phone_hint: string, price: string}
     */
    private function demoLead(): array
    {
        return [
            'name' => __('portal.onboarding.done.demo.name'),
            'funnel_name' => __('portal.onboarding.done.demo.funnel'),
            'funnel_icon' => 'wrench',
            'postal_code' => '76437',
            'chips' => array_values((array) __('portal.onboarding.done.demo.chips')),
            // Derselbe Weg wie im Marktplatz: "+49 172 ••• ••78".
            'phone' => (string) LeadContact::maskPhoneToLastDigits(self::DEMO_PHONE),
            'phone_hint' => __('portal.onboarding.done.demo.phone_hint'),
            'price' => Money::format(self::DEMO_PRICE_CENTS),
        ];
    }
}
