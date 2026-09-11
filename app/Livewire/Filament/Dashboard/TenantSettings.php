<?php

namespace App\Livewire\Filament\Dashboard;

use App\Models\Tenant;
use App\Services\TenantService;
use App\Services\Wallet\PayoutService;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Component;
use Parfaitementweb\FilamentCountryField\Forms\Components\Country;
use RuntimeException;

class TenantSettings extends Component implements HasForms
{
    use InteractsWithForms;

    private TenantService $tenantService;

    public ?array $data = [];

    public function render()
    {
        return view('livewire.filament.dashboard.tenant-settings');
    }

    public function boot(TenantService $tenantService): void
    {
        $this->tenantService = $tenantService;
    }

    public function mount(): void
    {
        $tenant = Filament::getTenant();

        $fields = [
            'tenant_name' => $tenant->name,
        ];

        $address = $tenant->address()->first();

        if ($address) {
            $fields = array_merge($fields, [
                'address_line_1' => $address->address_line_1,
                'address_line_2' => $address->address_line_2,
                'city' => $address->city,
                'state' => $address->state,
                'zip' => $address->zip,
                'country_code' => $address->country_code,
                'phone' => $address->phone,
                'tax_number' => $address->tax_number,
                'tenant_name' => $tenant->name,
            ]);
        }

        $this->form->fill($fields);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tenant_name')
                    ->label(__('Workspace Name'))
                    ->helperText(__('Edit the name of your workspace'))
                    ->required(),

                Section::make([
                    TextInput::make('address_line_1')
                        ->label(__('Address Line 1'))
                        ->helperText(__('Street address, company name, c/o')),
                    TextInput::make('address_line_2')
                        ->label(__('Address Line 2'))
                        ->helperText(__('Apartment, suite, unit, building, floor, etc.')),
                    TextInput::make('city')
                        ->label(__('City')),
                    TextInput::make('state')
                        ->label(__('State')),
                    TextInput::make('zip')
                        ->label(__('Zip')),
                    Country::make('country_code')
                        ->label(__('Country')),
                    TextInput::make('phone')
                        ->label(__('Phone')),
                    TextInput::make('tax_number')
                        ->label(__('Tax Number')),
                ])->heading(__('Organization Address'))
                    ->description(__('This address will be used for issuing invoices')),

                // Bankverbindung fuer die Auszahlung der Lead-Einnahmen
                // (LP-WALLET-010). Nur fuer Verkaeufer: Kaeufer-Mandanten
                // zahlen ein, sie bekommen nichts ausgezahlt.
                Section::make([
                    TextInput::make('payout_iban')
                        ->label(__('marketplace.wallet.payout.profile.iban'))
                        ->helperText(fn (): string => $this->ibanHelperText())
                        ->autocomplete(false)
                        ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (! is_string($value) || $value === '') {
                                return;
                            }

                            // Formatpruefung, keine Pruefziffernrechnung: Ob
                            // die IBAN wirklich existiert, sagt uns erst die
                            // Bank bei der Ueberweisung.
                            if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{10,30}$/', PayoutService::normalizeIban($value)) !== 1) {
                                $fail(__('marketplace.wallet.payout.profile.invalid_iban'));
                            }
                        }),
                ])->heading(__('marketplace.wallet.payout.profile.heading'))
                    ->description(__('marketplace.wallet.payout.profile.description'))
                    ->visible(fn (): bool => ! $this->currentTenant()->isBuyer()),
            ])
            ->statePath('data');
    }

    /**
     * Der Mandant des Panels, als Tenant statt als blosses Model: Die
     * Bankverbindung haengt an Feldern, die nur Tenant kennt.
     */
    private function currentTenant(): Tenant
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            // Die Seite liegt im Dashboard-Panel und ist ohne Workspace nicht
            // erreichbar; der Wurf ist die ehrliche Alternative zu einem
            // stillen Nullwert.
            throw new RuntimeException('Diese Seite braucht einen Workspace.');
        }

        return $tenant;
    }

    /**
     * Die hinterlegte IBAN wird nie ausgegeben -- auch nicht in das eigene
     * Formular. Der Hinweistext nennt nur die letzte Vierergruppe, das Feld
     * bleibt leer; wer es leer laesst, aendert nichts.
     */
    private function ibanHelperText(): string
    {
        $last4 = $this->currentTenant()->payout_iban_last4;

        return $last4 === null
            ? __('marketplace.wallet.payout.profile.iban_helper')
            : __('marketplace.wallet.payout.profile.iban_stored', ['last4' => $last4]);
    }

    /**
     * Gespeichert wird die vollstaendige IBAN, verschluesselt (Cast auf
     * Tenant). Ein leeres Feld laesst die vorhandene Bankverbindung stehen:
     * Weil sie nirgends angezeigt wird, kann der Verkaeufer sie nicht
     * abschreiben und wieder eintragen -- ein leeres Feld als Loeschbefehl zu
     * lesen, wuerde sie bei jedem Speichern der Adresse verlieren.
     */
    private function savePayoutIban(Tenant $tenant, ?string $iban): void
    {
        if (! is_string($iban) || trim($iban) === '') {
            return;
        }

        $tenant->payout_iban = PayoutService::normalizeIban($iban);
        $tenant->save();
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $tenant = Filament::getTenant();

        $this->tenantService->updateTenantName($tenant, $data['tenant_name']);

        $this->savePayoutIban($this->currentTenant(), $data['payout_iban'] ?? null);

        $address = $tenant->address()->first();

        if ($address) {
            $address->update($data);
        } else {
            $tenant->address()->create($data);
        }

        Notification::make()
            ->title(__('Settings Saved'))
            ->success()
            ->send();
    }
}
