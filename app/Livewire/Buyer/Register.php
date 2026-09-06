<?php

declare(strict_types=1);

namespace App\Livewire\Buyer;

use App\Models\BuyerRegistration;
use App\Models\User;
use App\Services\BuyerOnboardingService;
use Illuminate\Contracts\View\View;
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
     * Zustimmung zum Auftragsverarbeitungsvertrag. Pflicht: ohne sie entsteht
     * keine Registrierung.
     */
    public bool $avAccepted = false;

    /**
     * Die eingegangene Registrierung, sobald das Formular abgeschickt wurde.
     * Danach zeigt die Seite die Bestaetigung statt des Formulars.
     */
    public ?BuyerRegistration $registration = null;

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
        ]);
    }

    public function render(): View
    {
        return view('livewire.buyer.register');
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
            'avAccepted' => __('marketplace.buyer.form.av_accepted'),
        ];
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
