<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Constants\BuyerNotifyInterval;
use App\Funnel\Snapshots\SnapshotLabels;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Livewire\Portal\Concerns\ShowsToasts;
use App\Marketplace\MarketplaceCatalog;
use App\Marketplace\MarketplaceListing;
use App\Models\BuyerProfile;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\Scopes\TenantScopes;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Kaufkriterien im eigenen Portal (Portal Phase 1).
 *
 * Zweitfassung der Filament-Seite App\Filament\Dashboard\Pages\BuyerProfile,
 * nach dem Entwurf `kaufkriterien.html` in fuenf Fragen sortiert. Beide Wege
 * laufen parallel, bis das Portal abgenommen ist.
 *
 * **Diese Komponente wertet nichts aus.** Ob ein Lead zu den Kriterien passt,
 * entscheidet allein der LeadMatcher -- auch die Zahl in der Spalte rechts
 * entsteht so: dieselbe Abfrage wie der Marktplatz, mit dem noch nicht
 * gespeicherten Profil. Eine zweite Zaehlweise hier waere genau die Stelle, an
 * der Vorschau und Marktplatz auseinanderlaufen.
 *
 * Die Kriterien liegen im Formular in der Form, in der man sie eingibt
 * (Praefixe und Antworten als Chips, Preise in Euro), und werden beim Speichern
 * in die Form gebracht, in der der Matcher sie liest.
 */
#[Layout('components.layouts.portal-app')]
class BuyingCriteria extends Component
{
    use InteractsWithPortalTenant;
    use ShowsToasts;

    /** @var list<int> */
    public array $funnelIds = [];

    /** @var list<string> */
    public array $postalPrefixes = [];

    public string $prefixInput = '';

    /** Eingabe der Fragebogen-Suche. */
    public string $funnelQuery = '';

    /** Steht die Vorschlagsliste der Fragebogen offen? */
    public bool $funnelOpen = false;

    /**
     * Ein Eintrag je Karte: Merkmal und die akzeptierten Antworten.
     *
     * @var list<array{field_key: string, values: list<string>}>
     */
    public array $filters = [];

    /** Fuer welche Karte gerade die Antwortauswahl offen steht. */
    public ?int $valuePickerFor = null;

    public string $minScore = '';

    /** Hoechstpreis in Euro, wie im Feld getippt. */
    public string $maxPrice = '';

    public bool $autoBuy = false;

    public string $dailyLimit = '';

    /** Wochenbudget in Euro, wie im Feld getippt. */
    public string $weeklyBudget = '';

    public string $notifyEmail = '';

    public string $notifyInterval = 'immediate';

    public ?string $notice = null;

    /**
     * Der zuletzt gespeicherte Stand. Daran haengt die Anzeige
     * "Ungespeicherte Aenderungen" -- ohne ihn muesste die Leiste raten.
     *
     * @var array<string, mixed>
     */
    public array $saved = [];

    public function mount(): void
    {
        abort_unless(Gate::allows('marketplace.access', $this->portalTenant()), 403);

        $this->fillFrom($this->profile());
        $this->saved = $this->currentState();
    }

    public function render(): View
    {
        $fields = $this->availableFields();
        $matching = $this->matchingLeads();

        return view('livewire.portal.buying-criteria', [
            'funnels' => $this->funnels(),
            'funnelSuggestions' => $this->funnelSuggestions(),
            'fields' => $fields,
            'matchCount' => $matching->count(),
            'recentCount' => $matching->filter(
                static fn (Lead $lead): bool => $lead->created_at !== null && $lead->created_at->gt(now()->subDays(7)),
            )->count(),
            'summary' => $this->summary($fields),
            'isDirty' => $this->currentState() !== $this->saved,
            'marketplaceUrl' => route('portal.marketplace', ['tenant' => $this->portalTenant()->uuid]),
            'intervals' => BuyerNotifyInterval::options(),
            'maxPrefixes' => (int) config('funnel.marketplace.profile.max_postal_prefixes'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Fragebogen
    |--------------------------------------------------------------------------
    */

    public function addFunnel(int $funnelId): void
    {
        $this->funnelQuery = '';
        $this->funnelOpen = false;

        if (! array_key_exists($funnelId, $this->funnels()) || in_array($funnelId, $this->funnelIds, true)) {
            return;
        }

        $this->funnelIds[] = $funnelId;
        $this->funnelIds = array_values($this->funnelIds);
    }

    public function openFunnels(): void
    {
        $this->funnelOpen = true;
    }

    public function closeFunnels(): void
    {
        $this->funnelOpen = false;
    }

    /**
     * Uebernimmt den ersten Vorschlag. Damit reicht Tippen und Enter, ohne die
     * Liste mit der Maus anzufassen.
     */
    public function addFirstFunnel(): void
    {
        $first = array_key_first($this->funnelSuggestions());

        if ($first !== null) {
            $this->addFunnel((int) $first);
        }
    }

    /**
     * Die Vorschlaege zur Eingabe: noch nicht gewaehlte Fragebogen, deren Name
     * die Eingabe enthaelt.
     *
     * @return array<int, string>
     */
    private function funnelSuggestions(): array
    {
        $query = trim($this->funnelQuery);

        $matches = array_filter(
            $this->funnels(),
            fn (string $name, int $id): bool => ! in_array($id, $this->funnelIds, true)
                && ($query === '' || mb_stripos($name, $query) !== false),
            ARRAY_FILTER_USE_BOTH,
        );

        return array_slice($matches, 0, 8, preserve_keys: true);
    }

    public function removeFunnel(int $funnelId): void
    {
        $this->funnelIds = array_values(array_filter(
            $this->funnelIds,
            static fn (int $id): bool => $id !== $funnelId,
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Regionen
    |--------------------------------------------------------------------------
    */

    /**
     * Uebernimmt die Eingabe als Bereich. Geprueft wird dieselbe Form wie im
     * Filament-Formular: nur Ziffern, hoechstens die konfigurierte Laenge.
     */
    public function addPrefix(): void
    {
        $prefix = trim($this->prefixInput);
        $this->prefixInput = '';

        if ($prefix === '') {
            return;
        }

        $maxLength = (int) config('funnel.marketplace.profile.postal_prefix_max_length');
        $maximum = (int) config('funnel.marketplace.profile.max_postal_prefixes');

        if (preg_match('/^[0-9]{1,'.$maxLength.'}$/', $prefix) !== 1) {
            $this->notice = __('marketplace.profile.portal.region.bad_format', ['max' => $maxLength]);

            return;
        }

        if (count($this->postalPrefixes) >= $maximum) {
            $this->notice = __('marketplace.profile.portal.region.too_many', ['max' => $maximum]);

            return;
        }

        if (in_array($prefix, $this->postalPrefixes, true)) {
            return;
        }

        $this->notice = null;
        $this->postalPrefixes[] = $prefix;
        $this->postalPrefixes = array_values($this->postalPrefixes);
    }

    public function removePrefix(string $prefix): void
    {
        $this->postalPrefixes = array_values(array_filter(
            $this->postalPrefixes,
            static fn (string $existing): bool => $existing !== $prefix,
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Antwortfilter
    |--------------------------------------------------------------------------
    */

    public function addFilter(): void
    {
        $fields = $this->availableFields();

        $this->filters[] = [
            'field_key' => (string) array_key_first($fields),
            'values' => [],
        ];
        $this->filters = array_values($this->filters);
    }

    public function removeFilter(int $index): void
    {
        unset($this->filters[$index]);
        $this->filters = array_values($this->filters);
        $this->valuePickerFor = null;
    }

    public function addValue(int $index, string $value): void
    {
        $value = trim($value);

        if ($value === '' || ! isset($this->filters[$index])) {
            return;
        }

        if (! in_array($value, $this->filters[$index]['values'], true)) {
            $this->filters[$index]['values'][] = $value;
            $this->filters[$index]['values'] = array_values($this->filters[$index]['values']);
        }

        $this->valuePickerFor = null;
    }

    public function removeValue(int $index, string $value): void
    {
        if (! isset($this->filters[$index])) {
            return;
        }

        $this->filters[$index]['values'] = array_values(array_filter(
            $this->filters[$index]['values'],
            static fn (string $existing): bool => $existing !== $value,
        ));
    }

    public function openValuePicker(int $index): void
    {
        $this->valuePickerFor = $this->valuePickerFor === $index ? null : $index;
    }

    /*
    |--------------------------------------------------------------------------
    | Speichern
    |--------------------------------------------------------------------------
    */

    public function save(): void
    {
        $profile = $this->profile();

        $profile->fill([
            'funnel_ids' => array_values(array_map('intval', $this->funnelIds)),
            'postal_prefixes' => $this->postalPrefixes,
            // Filter ohne Antworten schraenken nicht ein und fallen weg -- sie
            // wuerden sonst jeden Lead ausschliessen.
            'answer_filters' => $this->parsedFilters(),
            'min_score' => $this->intOrNull($this->minScore),
            'max_price_cents' => $this->centsOrNull($this->maxPrice),
            'daily_limit' => $this->intOrNull($this->dailyLimit),
            'weekly_budget_cents' => $this->centsOrNull($this->weeklyBudget),
            'auto_buy' => $this->autoBuy,
            'notify_email' => trim($this->notifyEmail) === '' ? null : trim($this->notifyEmail),
            'notify_interval' => BuyerNotifyInterval::tryFrom($this->notifyInterval) ?? BuyerNotifyInterval::IMMEDIATE,
        ]);

        $profile->save();

        $this->saved = $this->currentState();
        $this->notice = null;

        // Ohne diese Zeile ist das Speichern unsichtbar: Die Seite sieht
        // danach genauso aus wie davor, und der Kaeufer klickt ein zweites
        // Mal, weil er nicht weiss, ob es angekommen ist.
        $this->toast(__('portal.toast.saved'));
    }

    public function discard(): void
    {
        $this->fillFrom($this->profile()->refresh());
        $this->saved = $this->currentState();
        $this->notice = null;

        $this->toast(__('portal.toast.discarded'));
    }

    /*
    |--------------------------------------------------------------------------
    | Innereien
    |--------------------------------------------------------------------------
    */

    /**
     * Die Leads, die mit dem aktuell eingetippten Stand passen -- dieselbe
     * Abfrage wie der Marktplatz, mit einem ungespeicherten Profil.
     *
     * @return Collection<int, Lead>
     */
    private function matchingLeads(): Collection
    {
        $draft = new BuyerProfile([
            'funnel_ids' => array_values(array_map('intval', $this->funnelIds)),
            'postal_prefixes' => $this->postalPrefixes,
            'answer_filters' => $this->parsedFilters(),
            'min_score' => $this->intOrNull($this->minScore),
        ]);

        return app(MarketplaceListing::class)->for($this->portalTenant(), $draft);
    }

    /**
     * Merkmale und moegliche Antworten aus den gewaehlten Fragebogen -- ohne
     * Auswahl aus allen veroeffentlichten.
     *
     * Gelesen wird die Fassung, unter der der Fragebogen gerade laeuft. Die
     * Beschriftungen stehen im Schnappschuss, nicht im Feldschluessel; ohne sie
     * stuende im Auswahlfeld "tierart" statt "Tier".
     *
     * @return array<string, array{label: string, options: array<string, string>}>
     */
    private function availableFields(): array
    {
        $funnels = Funnel::query()
            // Die Fragebogen gehoeren dem Betreiber, gelesen wird im Kontext
            // des Kaeufers -- mit Mandanten-Scope kaeme nichts heraus.
            ->withoutGlobalScopes(TenantScopes::names())
            ->published()
            ->when($this->funnelIds !== [], fn ($query) => $query->whereIn('id', $this->funnelIds))
            ->with(['currentVersion' => static fn ($version) => $version->withoutGlobalScopes(TenantScopes::names())])
            ->get();

        $fields = [];

        foreach ($funnels as $funnel) {
            $snapshot = $funnel->currentVersion?->snapshot;
            $snapshot = is_array($snapshot) ? $snapshot : null;

            $options = SnapshotLabels::options($snapshot);

            foreach (SnapshotLabels::questions($snapshot) as $fieldKey => $label) {
                $fields[$fieldKey]['label'] = $label;
                $fields[$fieldKey]['options'] = ($fields[$fieldKey]['options'] ?? []) + ($options[$fieldKey] ?? []);
            }
        }

        ksort($fields);

        return $fields;
    }

    /**
     * Die Zusammenfassung neben der Zahl: was gerade einschraenkt.
     *
     * @param  array<string, array{label: string, options: array<string, string>}>  $fields
     * @return list<array{label: string, value: string, active: bool}>
     */
    private function summary(array $fields): array
    {
        $funnels = $this->funnels();

        $names = array_values(array_map(
            static fn (int $id): string => $funnels[$id] ?? (string) $id,
            $this->funnelIds,
        ));

        $lines = [[
            'label' => $names === [] ? __('marketplace.profile.portal.match.all_funnels') : __('marketplace.profile.funnels'),
            'value' => implode(', ', $names),
            'active' => $names !== [],
        ], [
            'label' => $this->postalPrefixes === [] ? __('marketplace.profile.portal.match.all_regions') : __('marketplace.profile.portal.match.postcodes'),
            'value' => implode(', ', $this->postalPrefixes),
            'active' => $this->postalPrefixes !== [],
        ]];

        foreach ($this->parsedFilters() as $fieldKey => $values) {
            $labels = array_map(
                static fn (string $value): string => $fields[$fieldKey]['options'][$value] ?? $value,
                $values,
            );

            $lines[] = [
                'label' => $fields[$fieldKey]['label'] ?? $fieldKey,
                'value' => implode(__('marketplace.profile.portal.match.or'), $labels),
                'active' => true,
            ];
        }

        $lines[] = [
            'label' => $this->autoBuy
                ? __('marketplace.profile.portal.match.auto_on')
                : __('marketplace.profile.portal.match.auto_off'),
            'value' => '',
            'active' => $this->autoBuy,
        ];

        return $lines;
    }

    /** @return array<int, string> */
    private function funnels(): array
    {
        return app(MarketplaceCatalog::class)->publishedFunnels();
    }

    private function profile(): BuyerProfile
    {
        $tenant = $this->portalTenant();

        return BuyerProfile::query()->firstOrCreate(['tenant_id' => $tenant->getKey()]);
    }

    private function fillFrom(BuyerProfile $profile): void
    {
        $this->funnelIds = $profile->funnelIds();
        $this->postalPrefixes = $profile->postalPrefixes();

        $this->filters = [];

        foreach ($profile->answerFilters() as $fieldKey => $values) {
            $this->filters[] = ['field_key' => (string) $fieldKey, 'values' => array_values($values)];
        }

        $this->minScore = $profile->min_score === null ? '' : (string) $profile->min_score;
        $this->maxPrice = $profile->max_price_cents === null ? '' : (string) intdiv($profile->max_price_cents, 100);
        $this->autoBuy = (bool) $profile->auto_buy;
        $this->dailyLimit = $profile->daily_limit === null ? '' : (string) $profile->daily_limit;
        $this->weeklyBudget = $profile->weekly_budget_cents === null ? '' : (string) intdiv($profile->weekly_budget_cents, 100);
        $this->notifyEmail = (string) ($profile->notify_email ?? '');
        $this->notifyInterval = ($profile->notify_interval ?? BuyerNotifyInterval::IMMEDIATE)->value;
    }

    /**
     * @return array<string, list<string>>
     */
    private function parsedFilters(): array
    {
        $filters = [];

        foreach ($this->filters as $filter) {
            $fieldKey = trim($filter['field_key']);
            $values = array_values(array_filter(array_map('trim', $filter['values'])));

            if ($fieldKey === '' || $values === []) {
                continue;
            }

            $filters[$fieldKey] = $values;
        }

        return $filters;
    }

    /** @return array<string, mixed> */
    private function currentState(): array
    {
        return [
            'funnelIds' => $this->funnelIds,
            'postalPrefixes' => $this->postalPrefixes,
            'filters' => $this->parsedFilters(),
            'minScore' => $this->minScore,
            'maxPrice' => $this->maxPrice,
            'autoBuy' => $this->autoBuy,
            'dailyLimit' => $this->dailyLimit,
            'weeklyBudget' => $this->weeklyBudget,
            'notifyEmail' => $this->notifyEmail,
            'notifyInterval' => $this->notifyInterval,
        ];
    }

    private function intOrNull(string $value): ?int
    {
        return trim($value) === '' ? null : (int) $value;
    }

    /** Euro aus dem Feld, Cent in der Spalte. */
    private function centsOrNull(string $value): ?int
    {
        if (trim($value) === '') {
            return null;
        }

        return (int) round(((float) str_replace(',', '.', $value)) * 100);
    }
}
