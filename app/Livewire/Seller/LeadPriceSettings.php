<?php

declare(strict_types=1);

namespace App\Livewire\Seller;

use App\Models\Tenant;
use App\Services\Wallet\PurchaseService;
use App\Support\Money;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Livewire\Component;

/**
 * Der Leadpreis des Verkaeufers (LP-WALLET-012).
 *
 * Geschrieben wird genau eine Spalte: `tenants.lead_price_cents`. Was der
 * Verkaeufer davon behaelt, rechnet diese Komponente nicht selbst aus, sondern
 * fragt dieselbe Stelle, die beim Kauf rechnet -- PurchaseService::
 * commissionCents() und commissionPercentFor(). Eine zweite Rechnung fuer die
 * Vorschau waere die Stelle, an der Anzeige und Abrechnung auseinanderlaufen.
 *
 * Die Vorschau ist eine Vorschau und kein Versprechen: Der Preis wird beim Kauf
 * im Beleg festgeschrieben, eine spaetere Aenderung wirkt nur auf neue Kaeufe
 * (PurchaseService::reserve). Genau das sagt der Hinweis unter dem Feld.
 *
 * Eingegeben wird in Euro, gespeichert in Cent -- im Ledger wird nie mit einem
 * Float gerechnet. Das Komma als Dezimaltrenner ist zugelassen: Wer einen Preis
 * in Euro eintraegt, tippt "12,50".
 */
class LeadPriceSettings extends Component implements HasForms
{
    use InteractsWithForms;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->getForm('form')?->fill([
            'lead_price_euro' => $this->euroOf((int) $this->currentTenant()->lead_price_cents),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('marketplace.wallet.seller.price.heading'))
                    ->description(__('marketplace.wallet.seller.price.description'))
                    ->schema([
                        TextInput::make('lead_price_euro')
                            ->label(__('marketplace.wallet.seller.price.label'))
                            ->required()
                            ->suffix('€')
                            ->inputMode('decimal')
                            // Eigene Regel statt `numeric()`: Der Preis wird
                            // in Euro mit Komma eingegeben, und die Spanne
                            // steht in Cent in config('wallet.*').
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                $cents = $this->centsOf($value);

                                if ($cents === null || $cents < $this->minCents() || $cents > $this->maxCents()) {
                                    $fail(__('marketplace.wallet.seller.price.out_of_range', [
                                        'min' => $this->money($this->minCents()),
                                        'max' => $this->money($this->maxCents()),
                                    ]));
                                }
                            })
                            ->live(debounce: 400)
                            ->helperText(fn (Get $get): Htmlable => $this->preview($get('lead_price_euro'))),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = (array) $this->getForm('form')?->getState();

        $cents = $this->centsOf($data['lead_price_euro'] ?? null);

        if ($cents === null || $cents < $this->minCents() || $cents > $this->maxCents()) {
            Notification::make()
                ->title(__('marketplace.wallet.seller.price.out_of_range', [
                    'min' => $this->money($this->minCents()),
                    'max' => $this->money($this->maxCents()),
                ]))
                ->danger()
                ->send();

            return;
        }

        $this->currentTenant()->update(['lead_price_cents' => $cents]);

        // Zurueck in die gespeicherte Form: "12,5" wird nach dem Speichern zu
        // "12,50", damit Feld und Datenbank dasselbe zeigen.
        $this->getForm('form')?->fill(['lead_price_euro' => $this->euroOf($cents)]);

        Notification::make()
            ->title(__('marketplace.wallet.seller.price.saved'))
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('livewire.seller.lead-price-settings');
    }

    /**
     * Vorschau und Hinweis unter dem Feld: was beim Verkaeufer ankommt, und
     * dass bereits gekaufte Leads ihren Preis behalten.
     */
    private function preview(mixed $input): Htmlable
    {
        $hint = '<span class="block">'.e(__('marketplace.wallet.seller.price.change_hint')).'</span>';

        $cents = $this->centsOf($input);

        if ($cents === null || $cents < $this->minCents() || $cents > $this->maxCents()) {
            $range = '<span class="block">'.e(__('marketplace.wallet.seller.price.range', [
                'min' => $this->money($this->minCents()),
                'max' => $this->money($this->maxCents()),
            ])).'</span>';

            return new HtmlString($range.$hint);
        }

        $percent = $this->commissionPercent();
        $net = $cents - PurchaseService::commissionCents($cents, $percent);

        $line = '<span class="block font-medium text-gray-950 dark:text-white">'.e(__('marketplace.wallet.seller.price.preview', [
            'net' => $this->money($net),
            'percent' => $this->percent($percent),
        ])).'</span>';

        return new HtmlString($line.$hint);
    }

    /**
     * Der Provisionssatz, mit dem auch abgerechnet wird.
     */
    private function commissionPercent(): float
    {
        return app(PurchaseService::class)->commissionPercentFor($this->currentTenant());
    }

    /**
     * Eingabe in Euro zu Cent. Null, wenn nichts Brauchbares dasteht.
     */
    private function centsOf(mixed $input): ?int
    {
        if (! is_string($input) && ! is_numeric($input)) {
            return null;
        }

        $value = str_replace(',', '.', trim((string) $input));

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) round(((float) $value) * 100);
    }

    private function euroOf(int $cents): string
    {
        return Money::decimal($cents);
    }

    private function money(int $cents): string
    {
        return Money::format($cents);
    }

    /**
     * Provisionssatz ohne ueberfluessige Nullen: "20 %" statt "20,00 %".
     */
    private function percent(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, ',', '.'), '0'), ',');
    }

    private function minCents(): int
    {
        return (int) config('wallet.lead_price_min_cents');
    }

    private function maxCents(): int
    {
        return (int) config('wallet.lead_price_max_cents');
    }

    private function currentTenant(): Tenant
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }
}
