<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Livewire\Portal\Concerns\ShowsToasts;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Profil" im Portal.
 *
 * Bis hierher zeigte das Kontomenue auf die Breezy-Seite des
 * Dashboard-Panels -- ein Klick auf "Profil" warf den Kaeufer aus dem Portal in
 * das alte Filament-Dashboard. Diese Seite loest sie ab.
 *
 * Sie deckt das persoenliche Konto ab: Name, E-Mail, Passwort. Nicht hier
 * stehen zwei Dinge, die im alten Dashboard danebenlagen:
 *
 * - **Die Rechnungsanschrift.** Sie gehoert dem Workspace und nicht dem
 *   Benutzer: InvoiceService liest `tenant->address`, und genau die pflegt man
 *   in den Workspace-Einstellungen. Die Adresse des Benutzers, die das
 *   Breezy-Formular schreibt, liest keine Stelle der Anwendung.
 * - **Die Zwei-Faktor-Anmeldung.** Sie steht weiter im Dashboard-Panel; hier
 *   steht nur der Stand und der Weg dorthin. Ein zweiter Einrichtungsweg
 *   daneben waere ein zweiter Ort, an dem Wiederherstellungscodes entstehen.
 *
 * **Eine geaenderte E-Mail-Adresse gilt erst nach Bestaetigung.** Der Benutzer
 * implementiert MustVerifyEmail und die Portalrouten verlangen `verified`:
 * Wird die Adresse geaendert, faellt die Bestaetigung weg und die neue Adresse
 * bekommt den Link. Das ist der Grund, warum die Aenderung das aktuelle
 * Passwort verlangt -- an einer offen stehenden Sitzung waere sie sonst eine
 * Kontouebernahme.
 */
#[Layout('components.layouts.portal-app')]
class Profile extends Component
{
    use InteractsWithPortalTenant;
    use ShowsToasts;

    public string $name = '';

    public string $email = '';

    /** Nur bei geaenderter Adresse verlangt. */
    public string $emailPassword = '';

    public string $currentPassword = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public function mount(): void
    {
        $user = $this->user();

        $this->name = (string) $user->name;
        $this->email = (string) $user->email;
    }

    public function render(): View
    {
        $user = $this->user();
        $tenant = $this->portalTenant();

        return view('livewire.portal.profile', [
            'user' => $user,
            'initial' => mb_strtoupper(mb_substr((string) $user->name, 0, 1)),
            'emailChanged' => $this->email !== '' && $this->email !== $user->email,
            'isVerified' => $user->hasVerifiedEmail(),
            'memberSince' => $user->created_at?->translatedFormat('F Y'),
            'workspaceName' => $tenant->name,
            'showPasswordForm' => ! config('app.otp_login_enabled', false),
            'twoFactorEnabled' => (bool) config('app.two_factor_auth_enabled'),
            'twoFactorActive' => $user->hasTwoFactorEnabled(),
            'twoFactorUrl' => route('filament.dashboard.pages.two-factor-auth', ['tenant' => $tenant->uuid]),
            'settingsUrl' => route('portal.settings', ['tenant' => $tenant->uuid]),
        ])->title(__('portal.profile.heading'));
    }

    /**
     * Name und E-Mail speichern.
     */
    public function saveAccount(): void
    {
        $user = $this->user();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
        ];

        $emailChanged = $this->email !== $user->email;

        if ($emailChanged) {
            $rules['emailPassword'] = ['required', 'string'];
        }

        $this->validate($rules, [
            'emailPassword.required' => __('portal.profile.account.password_required'),
        ]);

        if ($emailChanged && ! Hash::check($this->emailPassword, (string) $user->password)) {
            $this->addError('emailPassword', __('portal.profile.account.password_wrong'));

            return;
        }

        $user->name = $this->name;

        if ($emailChanged) {
            $user->email = $this->email;
            // Die neue Adresse ist unbestaetigt, bis ihr Besitzer den Link
            // anklickt. Ohne diese Zeile waere die Adresse eines Fremden mit
            // einem Formular als bestaetigt eingetragen.
            $user->email_verified_at = null;
        }

        $user->save();

        $this->emailPassword = '';

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            $this->toast(__('portal.profile.account.email_changed'));

            return;
        }

        $this->toast(__('portal.toast.saved'));
    }

    /**
     * Passwort aendern.
     *
     * Das aktuelle Passwort ist Pflicht: Eine offen stehende Sitzung soll sich
     * nicht in eine dauerhafte Uebernahme verwandeln lassen.
     */
    public function savePassword(): void
    {
        $this->validate([
            'currentPassword' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'same:passwordConfirmation'],
            'passwordConfirmation' => ['required', 'string'],
        ], [
            'password.same' => __('portal.profile.password.mismatch'),
        ]);

        $user = $this->user();

        if (! Hash::check($this->currentPassword, (string) $user->password)) {
            $this->addError('currentPassword', __('portal.profile.account.password_wrong'));

            return;
        }

        // Das Model castet 'password' => 'hashed', hier steht deshalb das
        // Klartextpasswort und kein zweites Hash::make().
        $user->password = $this->password;
        $user->save();

        $this->reset(['currentPassword', 'password', 'passwordConfirmation']);

        $this->toast(__('portal.profile.password.saved'));
    }

    /**
     * Den Bestaetigungslink noch einmal schicken.
     */
    public function resendVerification(): void
    {
        $user = $this->user();

        if ($user->hasVerifiedEmail()) {
            return;
        }

        // Dieselbe Bremse wie an der Route `verification.send`: Der Knopf darf
        // kein Werkzeug werden, um ein fremdes Postfach zu fluten.
        $key = 'portal-verification:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->toastWarning(__('portal.profile.account.verification_throttled'));

            return;
        }

        RateLimiter::hit($key, 60);

        $user->sendEmailVerificationNotification();

        $this->toast(__('portal.profile.account.verification_sent'));
    }

    private function user(): User
    {
        $user = $this->portalUser();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
