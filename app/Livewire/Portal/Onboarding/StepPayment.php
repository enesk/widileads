<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Onboarding;

use App\Constants\OnboardingStep;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Models\BuyerProfile;
use App\Services\Onboarding\IndustryStatsService;
use App\Services\Onboarding\OnboardingService;
use App\Support\PostpaidTerms;
use App\Support\TopupPackages;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Wizard-Schritt 3: die Zahlungsweise (LP-ONBOARD-010).
 *
 * Der letzte Pflichtschritt macht den Kaeufer kaufbereit. Zur Wahl stehen die
 * Guthabenpakete aus config('wallet.topup_presets_cents') und -- nur wenn
 * Postpaid produktiv ist -- Pay as you go. Wer noch nichts zahlen will, nimmt
 * den Verweis darunter.
 *
 * **Dieser Schritt rechnet kein Geld und bucht nichts.** Er haelt die Wahl
 * fest und gibt an den vorhandenen Weg weiter: Prepaid in die Aufladeseite
 * (LP-PORTAL-014) mit vorbelegtem Betrag, Pay as you go in die
 * Zahlungsmittelverwaltung (LP-POSTPAID-005). Ein zweiter Checkout waere ein
 * zweiter Zahlungsweg mit eigener Bestellung, eigenem Beleg und eigener
 * Gutschrift -- und damit die Stelle, an der Guthaben und Journal
 * auseinanderlaufen.
 *
 * **Warum der Schritt vor der Zahlung abgehakt wird.** Der Haken sagt "der
 * Kaeufer hat sich entschieden", nicht "er hat bezahlt". Bezahlt hat er, wenn
 * die Gutschrift im Journal steht; das haelt der eigene Schritt `first_topup`
 * fest, den der Wallet-Zuhoerer setzt (LP-ONBOARD-011). Haengte der Haken an
 * der Zahlung, stuende der Kaeufer nach dem Abbruch im Checkout wieder im
 * Wizard -- und die Gate-Middleware liesse ihn auch nicht zur Aufladeseite,
 * denn die ist nur fuer einen Kaeufer ohne offenen Wizard erreichbar.
 *
 * **Die Zahl "reicht fuer ca. n Leads"** kommt aus dem mittleren Leadpreis der
 * in Schritt 1 gewaehlten Branchen (IndustryStatsService). Eine Orientierung
 * und keine Zusage: Jeder Verkaeufer setzt seinen Preis selbst, und berechnet
 * wird am Ende der Preis des gekauften Leads.
 */
class StepPayment extends Component
{
    use InteractsWithPortalTenant;

    /** Der Wert der Pay-as-you-go-Karte in $choice. */
    public const CHOICE_PAYG = 'payg';

    /**
     * Die gewaehlte Karte: der Paketbetrag in ganzen Euro als Text oder
     * `payg`.
     *
     * Nicht gesperrt -- das ist das Feld dieses Schritts, und der Browser darf
     * es setzen. Was nicht im Angebot steht, faellt beim Speichern durch die
     * Pruefung.
     */
    public string $choice = '';

    /**
     * Innerhalb einer Anfrage einmal gebaut. Nicht oeffentlich, wandert also
     * nicht in den Zustand der Komponente.
     *
     * @var list<array{euro: int, leads: int, popular: bool}>|null
     */
    private ?array $packages = null;

    public function mount(): void
    {
        $this->choice = $this->preselection();
    }

    /**
     * "Weiter zur Zahlung" aus der Huelle.
     *
     * Erst pruefen, dann die Wahl festhalten, dann weitergeben. Gemeldet wird
     * `onboarding-step-saved` ausdruecklich NICHT: Dieser Schritt leitet
     * selbst weiter, und die Huelle wuerde den Kaeufer sonst zusaetzlich ins
     * Dashboard schicken.
     */
    #[On('onboarding-step-submit')]
    public function save(): void
    {
        $tenant = $this->portalTenant();

        if ($this->choice === self::CHOICE_PAYG && $this->isPaygEnabled()) {
            $this->rememberChoice(['mode' => 'payg']);

            // Pay as you go braucht ein hinterlegtes Zahlungsmittel, bevor es
            // beantragt werden kann (LP-POSTPAID-005). Der Antrag selbst hat
            // keine eigene Seite -- er steht auf der Guthabenseite.
            $this->redirect(
                route('portal.payment-methods', ['tenant' => $tenant->uuid]),
                navigate: false,
            );

            return;
        }

        $euro = $this->selectedPackageEuro();

        if ($euro === null) {
            $this->addError('choice', __('portal.onboarding.payment.error'));

            return;
        }

        $this->resetErrorBag();
        $this->rememberChoice(['mode' => 'prepaid', 'package' => $euro]);

        $this->redirect(
            route('portal.wallet', [
                'tenant' => $tenant->uuid,
                'betrag' => $euro,
                'onboarding' => 1,
            ]),
            navigate: false,
        );
    }

    /**
     * "Spaeter, erst mal umschauen".
     *
     * Der Schritt gilt als erledigt und der Wizard damit als abgeschlossen --
     * das ist der Unterschied zu "Spaeter einrichten" der Huelle, das den
     * Wizard offen laesst. Eine Entscheidung ist getroffen, sie heisst nur
     * "noch nicht zahlen": `first_topup` bleibt offen und steht als Aufgabe in
     * der Aktivierungskarte (LP-ONBOARD-011).
     */
    public function later(): void
    {
        $this->rememberChoice(['mode' => 'deferred']);

        $this->redirect(
            route('portal.onboarding.done', ['tenant' => $this->portalTenant()->uuid]),
            navigate: false,
        );
    }

    public function render(): View
    {
        $packages = $this->packages();

        return view('livewire.portal.onboarding.step-payment', [
            'packages' => $packages,
            'paygEnabled' => $this->isPaygEnabled(),
            'paygChoice' => self::CHOICE_PAYG,
            // Der Aufschlag gehoert auf die Karte: Eine Zahlungsweise, die je
            // Kauf etwas kostet, darf das nicht erst im Checkout sagen. Satz
            // und Schreibweise kommen aus PostpaidTerms -- derselbe Text steht
            // am Preis im Marktplatz.
            'paygSurcharge' => PostpaidTerms::surchargePercentLabel(),
            // Mehr Symbole als das sagen nichts mehr; der Rest steht als
            // "+x" daneben.
            'maxLeadIcons' => 10,
        ]);
    }

    /**
     * Haelt die Wahl am Onboarding-Schritt fest.
     *
     * Zwei Wege, weil der Schritt wiederholt werden kann: Beim ersten Mal
     * entsteht der Haken samt Meta, danach ist nur noch die Meta zu
     * berichtigen -- ein zweites complete() tut nichts, und in der Meta
     * stuende weiter das alte Paket.
     *
     * @param  array<string, mixed>  $meta
     */
    private function rememberChoice(array $meta): void
    {
        $onboarding = app(OnboardingService::class);
        $tenant = $this->portalTenant();

        $created = $onboarding->complete(
            $tenant,
            OnboardingStep::PAYMENT_SETUP,
            $this->portalUser(),
            $meta,
        );

        if (! $created) {
            $onboarding->updateMeta($tenant, OnboardingStep::PAYMENT_SETUP, $meta);
        }
    }

    /**
     * Der gewaehlte Paketbetrag in ganzen Euro -- null, wenn die Wahl kein
     * angebotenes Paket ist.
     */
    private function selectedPackageEuro(): ?int
    {
        if (! ctype_digit($this->choice)) {
            return null;
        }

        $euro = (int) $this->choice;

        foreach ($this->packages() as $package) {
            if ($package['euro'] === $euro) {
                return $euro;
            }
        }

        return null;
    }

    /**
     * Was beim Oeffnen gewaehlt ist.
     *
     * Erst die Wahl aus einem frueheren Durchlauf (Abbruch im Checkout), dann
     * das hervorgehobene Paket. Vorausgewaehlt wird bewusst etwas: Ein Schritt
     * ohne Vorauswahl verlangt eine Entscheidung, bevor der Kaeufer die Karten
     * gelesen hat.
     */
    private function preselection(): string
    {
        $meta = app(OnboardingService::class)->meta($this->portalTenant(), OnboardingStep::PAYMENT_SETUP) ?? [];
        $mode = $meta['mode'] ?? null;

        if ($mode === 'payg' && $this->isPaygEnabled()) {
            return self::CHOICE_PAYG;
        }

        $package = isset($meta['package']) ? (int) $meta['package'] : 0;

        foreach ($this->packages() as $offered) {
            if ($offered['euro'] === $package) {
                return (string) $package;
            }
        }

        foreach ($this->packages() as $offered) {
            if ($offered['popular']) {
                return (string) $offered['euro'];
            }
        }

        return (string) ($this->packages()[0]['euro'] ?? '');
    }

    /**
     * Die Pakete mit ihrem Gegenwert in Leads.
     *
     * @return list<array{euro: int, leads: int, popular: bool}>
     */
    private function packages(): array
    {
        return $this->packages ??= TopupPackages::forLeadPrice(
            app(IndustryStatsService::class)->averageLeadPriceCents($this->selectedFunnelIds()),
        );
    }

    /**
     * Die in Schritt 1 gewaehlten Branchen. Leer heisst: Der mittlere Preis
     * ist der Vorgabepreis.
     *
     * @return list<int>
     */
    private function selectedFunnelIds(): array
    {
        $profile = BuyerProfile::query()
            ->where('tenant_id', $this->portalTenant()->getKey())
            ->first();

        return $profile instanceof BuyerProfile ? $profile->funnelIds() : [];
    }

    /**
     * Ist Pay as you go produktiv (LP-POSTPAID)?
     *
     * Der Zweig wird nicht gerendert, solange der Hauptschalter aus ist -- und
     * zwar ohne Platzhaltertext: Eine Zahlungsweise anzukuendigen, die es noch
     * nicht gibt, ist eine Frage an den Kaeufer, die er nicht beantworten
     * kann.
     */
    private function isPaygEnabled(): bool
    {
        return (bool) config('wallet.postpaid.enabled');
    }
}
