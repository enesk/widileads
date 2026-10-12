<?php

declare(strict_types=1);

namespace App\Livewire\Buyer;

use App\Marketplace\MarketplaceCatalog;
use App\Models\BuyerRegistration;
use App\Models\User;
use App\Services\BuyerOnboardingService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Oeffentliche Registrierung als Kaeufer (FB-050).
 *
 * Reines Livewire mit Tailwind und daisyUI, keine Filament-Komponenten -- die
 * Seite liegt ausserhalb jedes Panels.
 *
 * Das Benutzerkonto legt die Registrierung bewusst nicht selbst an: dafuer gibt
 * es die Anmeldung von SaaSykit samt Passwortregeln, E-Mail-Bestaetigung und
 * reCAPTCHA. Wer hier ankommt, ist angemeldet; dieses Formular ergaenzt nur die
 * kaufmaennischen Angaben und legt daraus den Kaeufer-Mandanten an.
 */
class Register extends Component
{
    public string $companyName = '';

    public string $contactName = '';

    public string $contactEmail = '';

    public string $contactPhone = '';

    /**
     * Vermittlerregister-Nummer nach Paragraf 34d GewO. Optional -- nicht jeder
     * Kaeufer ist Versicherungsvermittler.
     */
    public string $brokerRegisterNumber = '';

    public string $vatId = '';

    /**
     * Die Branche, in der der Kaeufer Anfragen sucht -- die Kennung eines
     * veroeffentlichten Funnels (LP-PORTAL-004).
     *
     * Als String, weil ein <select> genau das schickt. Sie landet nicht am
     * Registrierungssatz, sondern als erster Eintrag in den Kaufkriterien des
     * neuen Workspaces (BuyerProfile::funnel_ids) -- dort liest sie der erste
     * Wizard-Schritt wieder (App\Livewire\Portal\Onboarding\StepIndustries).
     */
    public string $industryFunnelId = '';

    /**
     * Zustimmung zum Auftragsverarbeitungsvertrag. Pflicht: ohne sie entsteht
     * keine Registrierung.
     */
    public bool $avAccepted = false;

    /**
     * Die eingegangene Registrierung, sobald das Formular abgeschickt wurde.
     * Danach zeigt die Seite die Bestaetigung statt des Formulars.
     */
    public ?BuyerRegistration $registration = null;

    /**
     * Das Angebot, innerhalb einer Anfrage einmal geholt.
     *
     * @var array<int, string>|null
     */
    private ?array $industries = null;

    public function mount(): void
    {
        $user = $this->user();

        // Vorbelegen, was wir schon wissen -- der angemeldete Benutzer ist im
        // Regelfall auch der Ansprechpartner.
        $this->contactName = $user->name ?? '';
        $this->contactEmail = $user->email ?? '';

        $this->registration = $this->existingRegistration();
    }

    public function submit(BuyerOnboardingService $service): void
    {
        // Eine zweite Registrierung desselben Benutzers waere ein zweiter
        // Kaeufer-Mandant mit denselben Daten -- und der Plattform-Admin haette
        // zwei Vorgaenge zu entscheiden.
        if ($this->existingRegistration() !== null) {
            $this->addError('companyName', __('marketplace.buyer.form.already_registered'));

            return;
        }

        $data = $this->validate();

        $this->registration = $service->register($this->user(), [
            'company_name' => $data['companyName'],
            'contact_name' => $data['contactName'],
            'contact_email' => $data['contactEmail'],
            'contact_phone' => $data['contactPhone'] === '' ? null : $data['contactPhone'],
            'broker_register_number' => $data['brokerRegisterNumber'] === ''
                ? null
                : $data['brokerRegisterNumber'],
            'vat_id' => $data['vatId'],
            'funnel_id' => $this->chosenFunnelId($data['industryFunnelId']),
        ]);
    }

    public function render(): View
    {
        return view('livewire.buyer.register', [
            'industries' => $this->industries(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'companyName' => ['required', 'string', 'max:255'],
            'contactName' => ['required', 'string', 'max:255'],
            'contactEmail' => ['required', 'email', 'max:255'],
            'contactPhone' => ['nullable', 'string', 'max:50'],
            'brokerRegisterNumber' => ['nullable', 'string', 'max:100'],

            // Umsatzsteuer-Identifikationsnummer: zwei Buchstaben Laendercode,
            // danach die nationale Kennung. Bewusst nicht enger geprueft -- die
            // Formate der Mitgliedstaaten unterscheiden sich, und eine zu enge
            // Regel sperrt zulaessige Kaeufer aus.
            'vatId' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z]{2}[A-Za-z0-9]{2,18}$/'],

            'industryFunnelId' => $this->industryRules(),

            'avAccepted' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'required' => __('marketplace.buyer.validation.required'),
            'accepted' => __('marketplace.buyer.validation.av_accepted'),
            'email' => __('marketplace.buyer.validation.email'),
            'max' => __('marketplace.buyer.validation.max'),
            'vatId.regex' => __('marketplace.buyer.validation.vat_id'),
            'industryFunnelId.required' => __('marketplace.buyer.validation.industry'),
            'industryFunnelId.in' => __('marketplace.buyer.validation.industry'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'companyName' => __('marketplace.buyer.form.company_name'),
            'contactName' => __('marketplace.buyer.form.contact_name'),
            'contactEmail' => __('marketplace.buyer.form.contact_email'),
            'contactPhone' => __('marketplace.buyer.form.contact_phone'),
            'brokerRegisterNumber' => __('marketplace.buyer.form.broker_register_number'),
            'vatId' => __('marketplace.buyer.form.vat_id'),
            'industryFunnelId' => __('marketplace.buyer.form.industry'),
            'avAccepted' => __('marketplace.buyer.form.av_accepted'),
        ];
    }

    /**
     * Pflichtfeld -- aber nur, solange es ueberhaupt etwas zu waehlen gibt.
     *
     * Ohne veroeffentlichten Funnel waere die Pflicht eine Sperre, an der
     * niemand vorbeikaeme: Es gibt dann keine zulaessige Antwort, und die
     * Registrierung liesse sich nicht absenden. Derselbe Gedanke wie im ersten
     * Wizard-Schritt, der die Branchenpflicht aus demselben Grund aussetzt.
     *
     * @return list<mixed>
     */
    private function industryRules(): array
    {
        $industries = $this->industries();

        if ($industries === []) {
            return ['nullable'];
        }

        return [
            'required',
            Rule::in(array_map('strval', array_keys($industries))),
        ];
    }

    /**
     * Die gewaehlte Branche als Funnel-Kennung -- oder null.
     *
     * Gegen das Angebot geprueft und nicht nur umgewandelt: Steht nichts zur
     * Wahl, laesst die Pruefung jeden Wert durch (siehe industryRules), und
     * eine Kennung aus dem Browser landete sonst ungeprueft in den
     * Kaufkriterien.
     */
    private function chosenFunnelId(string $value): ?int
    {
        if ($value === '' || ! array_key_exists((int) $value, $this->industries())) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Das Angebot als Funnel-Kennung => Name. MarketplaceCatalog zieht dabei
     * die Grenze dessen, was ein Kaeufer vom Angebot sehen darf.
     *
     * @return array<int, string>
     */
    private function industries(): array
    {
        return $this->industries ??= app(MarketplaceCatalog::class)->publishedFunnels();
    }

    /**
     * Die Registrierung, die dieser Benutzer bereits eingereicht hat -- oder
     * null, wenn es noch keine gibt.
     */
    private function existingRegistration(): ?BuyerRegistration
    {
        return BuyerRegistration::query()
            ->whereIn('tenant_id', $this->user()->tenants()->select('tenants.id'))
            ->first();
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
