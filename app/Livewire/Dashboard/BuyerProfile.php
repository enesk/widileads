<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Marketplace\MarketplaceCatalog;
use App\Models\BuyerProfile as BuyerProfileModel;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Kaufkriterien des Kaeufer-Mandanten pflegen (FB-051).
 *
 * Reines Livewire mit Tailwind und daisyUI, keine Filament-Komponenten -- die
 * Filament-Page daneben ist nur die Huelle fuer Routing und Navigation.
 *
 * Die Kriterien liegen im Formular in der Form, in der man sie tippt
 * (Praefixe als Liste, Antwortfilter als Zeilen), und werden beim Speichern in
 * die Form gebracht, in der der LeadMatcher sie liest. Die Auswertung selbst
 * steht ausschliesslich im Matcher; diese Komponente entscheidet nichts.
 */
class BuyerProfile extends Component
{
    /**
     * Gewaehlte Funnels. Leer heisst: alle.
     *
     * @var list<int>
     */
    public array $funnelIds = [];

    /**
     * Postleitzahl-Praefixe, eines je Zeile oder mit Komma getrennt.
     */
    public string $postalPrefixes = '';

    /**
     * Antwortfilter als Zeilen: Feldschluessel und erlaubte Werte.
     *
     * @var list<array{field_key: string, values: string}>
     */
    public array $answerFilters = [];

    public ?int $minScore = null;

    public ?int $dailyLimit = null;

    public bool $autoBuy = false;

    public string $notifyEmail = '';

    public bool $saved = false;

    public function mount(): void
    {
        $profile = $this->profile();

        $this->funnelIds = $profile->funnelIds();
        $this->postalPrefixes = implode(', ', $profile->postalPrefixes());
        $this->minScore = $profile->min_score;
        $this->dailyLimit = $profile->daily_limit;
        $this->autoBuy = (bool) $profile->auto_buy;
        $this->notifyEmail = (string) ($profile->notify_email ?? '');

        foreach ($profile->answerFilters() as $fieldKey => $values) {
            $this->answerFilters[] = ['field_key' => $fieldKey, 'values' => implode(', ', $values)];
        }
    }

    public function addAnswerFilter(): void
    {
        $this->answerFilters[] = ['field_key' => '', 'values' => ''];
    }

    public function removeAnswerFilter(int $index): void
    {
        unset($this->answerFilters[$index]);

        $this->answerFilters = array_values($this->answerFilters);
    }

    public function save(): void
    {
        $this->validate();
        $this->validatePostalPrefixes();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $profile = $this->profile();

        $profile->fill([
            'funnel_ids' => array_values(array_map('intval', $this->funnelIds)),
            'postal_prefixes' => $this->parsedPostalPrefixes(),
            'answer_filters' => $this->parsedAnswerFilters(),
            'min_score' => $this->minScore,
            'daily_limit' => $this->dailyLimit,
            'auto_buy' => $this->autoBuy,
            'notify_email' => $this->notifyEmail === '' ? null : $this->notifyEmail,
        ]);

        $profile->save();

        $this->saved = true;
    }

    public function render(): View
    {
        return view('livewire.dashboard.buyer-profile', [
            'availableFunnels' => app(MarketplaceCatalog::class)->publishedFunnels(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'funnelIds' => ['array'],
            'funnelIds.*' => ['integer'],
            'postalPrefixes' => ['nullable', 'string', 'max:1000'],
            'answerFilters' => ['array'],
            'answerFilters.*.field_key' => ['nullable', 'string', 'max:64'],
            'answerFilters.*.values' => ['nullable', 'string', 'max:1000'],
            'minScore' => ['nullable', 'integer'],
            'dailyLimit' => ['nullable', 'integer', 'min:0'],
            'autoBuy' => ['boolean'],
            'notifyEmail' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'notifyEmail.email' => __('marketplace.profile.validation.notify_email'),
            'minScore.integer' => __('marketplace.profile.validation.min_score'),
            'dailyLimit.integer' => __('marketplace.profile.validation.daily_limit'),
            'dailyLimit.min' => __('marketplace.profile.validation.daily_limit'),
        ];
    }

    /**
     * Die Praefixe werden als ein Textfeld eingegeben, geprueft aber einzeln.
     * Deshalb von Hand und nicht ueber rules(): so haengt die Meldung am Feld,
     * in das der Kaeufer getippt hat, statt an einem Hilfsnamen.
     */
    private function validatePostalPrefixes(): void
    {
        $prefixes = $this->parsedPostalPrefixes();
        $maximum = (int) config('funnel.marketplace.profile.max_postal_prefixes');
        $maxLength = (int) config('funnel.marketplace.profile.postal_prefix_max_length');

        if (count($prefixes) > $maximum) {
            $this->addError('postalPrefixes', __('marketplace.profile.validation.too_many_prefixes', [
                'max' => $maximum,
            ]));

            return;
        }

        foreach ($prefixes as $prefix) {
            if (preg_match('/^[0-9]{1,'.$maxLength.'}$/', $prefix) === 1) {
                continue;
            }

            $this->addError('postalPrefixes', __('marketplace.profile.validation.prefix_format', [
                'prefix' => $prefix,
                'max' => $maxLength,
            ]));

            return;
        }
    }

    /**
     * Praefixe aus der Eingabe: Komma oder Zeilenumbruch trennen, Leerraum und
     * Doppelte fallen weg.
     *
     * @return list<string>
     */
    private function parsedPostalPrefixes(): array
    {
        $parts = preg_split('/[\s,;]+/', $this->postalPrefixes) ?: [];

        return array_values(array_unique(array_filter(
            array_map(static fn (string $part): string => trim($part), $parts),
            static fn (string $part): bool => $part !== '',
        )));
    }

    /**
     * Zeilen des Formulars als Feldschluessel => erlaubte Werte. Zeilen ohne
     * Schluessel oder ohne Werte schraenken nicht ein und fallen weg -- sie
     * wuerden sonst jeden Lead ausschliessen.
     *
     * @return array<string, list<string>>
     */
    private function parsedAnswerFilters(): array
    {
        $filters = [];

        foreach ($this->answerFilters as $row) {
            $fieldKey = trim((string) ($row['field_key'] ?? ''));

            if ($fieldKey === '') {
                continue;
            }

            $values = array_values(array_unique(array_filter(
                array_map(
                    static fn (string $value): string => trim($value),
                    preg_split('/[\n,;]+/', (string) ($row['values'] ?? '')) ?: [],
                ),
                static fn (string $value): bool => $value !== '',
            )));

            if ($values !== []) {
                $filters[$fieldKey] = $values;
            }
        }

        return $filters;
    }

    /**
     * Das Profil des aktiven Mandanten -- bei Bedarf frisch angelegt, ohne
     * Einschraenkung. Ein Kaeufer soll nach der Freischaltung alles sehen und
     * dann eingrenzen, nicht umgekehrt.
     */
    private function profile(): BuyerProfileModel
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return BuyerProfileModel::query()->firstOrNew(
            ['tenant_id' => $tenant->getKey()],
            [
                'funnel_ids' => [],
                'postal_prefixes' => [],
                'answer_filters' => [],
                'daily_limit' => ((int) config('funnel.marketplace.profile.default_daily_limit')) ?: null,
                'auto_buy' => false,
            ],
        );
    }
}
