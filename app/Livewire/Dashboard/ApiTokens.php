<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Constants\TenantApiAbility;
use App\Exceptions\TenantApiTokenLimitReachedException;
use App\Models\Tenant;
use App\Services\TenantApiTokenService;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Seite "API-Zugaenge" im Tenant-Dashboard (FB-006).
 *
 * Bewusst reines Livewire ohne Filament-Komponenten. Der Klartext eines Tokens
 * wird genau einmal angezeigt: gespeichert ist nur der Hash, nach dem
 * Ausblenden ist der Klartext nicht wiederherstellbar.
 */
class ApiTokens extends Component
{
    public string $name = '';

    /**
     * @var list<string>
     */
    public array $abilities = [];

    /**
     * Der zuletzt erzeugte Token im Klartext - nur fuer die aktuelle Anzeige.
     */
    public ?string $plainTextToken = null;

    public function createToken(TenantApiTokenService $service): void
    {
        $data = $this->validate();

        try {
            $token = $service->create($this->tenant(), $data['name'], array_values($data['abilities']));
        } catch (TenantApiTokenLimitReachedException $exception) {
            $this->addError('name', $exception->getMessage());

            return;
        }

        $this->plainTextToken = $token->plainTextToken;

        $this->reset(['name', 'abilities']);
    }

    public function revoke(int $tokenId, TenantApiTokenService $service): void
    {
        $service->revoke($this->tenant(), $tokenId);
    }

    public function dismissPlainTextToken(): void
    {
        $this->plainTextToken = null;
    }

    public function render(TenantApiTokenService $service): View
    {
        // Bewusst kein Computed Property: Livewire wuerde den Wert innerhalb
        // einer Anfrage zwischenspeichern und nach revoke() ein bereits
        // geloeschtes Token weiter anzeigen.
        return view('livewire.dashboard.api-tokens', [
            'tokens' => $service->tokensFor($this->tenant()),
            'abilityOptions' => TenantApiAbility::options(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in(TenantApiAbility::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('funnel.api_token.name'),
            'abilities' => __('funnel.api_token.abilities'),
        ];
    }

    private function tenant(): Tenant
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }
}
