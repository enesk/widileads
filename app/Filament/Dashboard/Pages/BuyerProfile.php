<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Marketplace\MarketplaceCatalog;
use App\Models\BuyerProfile as BuyerProfileModel;
use App\Models\Tenant;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;

/**
 * Kaufkriterien des Kaeufer-Mandanten pflegen (FB-051, seit FB-090 als
 * Filament-Formular).
 *
 * Die Kriterien liegen im Formular in der Form, in der man sie eingibt
 * (Praefixe als Schlagworte, Antwortfilter als Zeilen), und werden beim
 * Speichern in die Form gebracht, in der der LeadMatcher sie liest. Die
 * Auswertung selbst steht ausschliesslich im Matcher; diese Seite entscheidet
 * nichts.
 *
 * Die Seite sieht nur, wer den Marktplatz sehen darf -- also ein
 * freigeschalteter Kaeufer (FB-050). Geprueft wird ueber dieselbe Methode wie
 * Gate und Middleware, nicht ueber eine eigene Bedingung.
 */
class BuyerProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.dashboard.pages.buyer-profile';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function getHeading(): string|Htmlable
    {
        return __('marketplace.profile.heading');
    }

    public function getTitle(): string|Htmlable
    {
        return __('marketplace.profile.heading');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('marketplace.profile.description');
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.marketplace');
    }

    public static function getNavigationLabel(): string
    {
        return __('marketplace.profile.nav_label');
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        return Gate::allows('marketplace.access', $tenant);
    }

    public function mount(): void
    {
        $profile = $this->profile();

        $answerFilters = [];

        foreach ($profile->answerFilters() as $fieldKey => $values) {
            $answerFilters[] = ['field_key' => $fieldKey, 'values' => $values];
        }

        $this->getSchema('form')?->fill([
            'funnel_ids' => $profile->funnelIds(),
            'postal_prefixes' => $profile->postalPrefixes(),
            'answer_filters' => $answerFilters,
            'min_score' => $profile->min_score,
            'daily_limit' => $profile->daily_limit,
            'auto_buy' => (bool) $profile->auto_buy,
            'notify_email' => $profile->notify_email,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $maximum = (int) config('funnel.marketplace.profile.max_postal_prefixes');
        $maxLength = (int) config('funnel.marketplace.profile.postal_prefix_max_length');
        $funnels = app(MarketplaceCatalog::class)->publishedFunnels();

        return $schema
            ->components([
                Section::make(__('marketplace.profile.heading'))
                    ->schema([
                        Select::make('funnel_ids')
                            ->label(__('marketplace.profile.funnels'))
                            ->helperText($funnels === []
                                ? __('marketplace.profile.no_funnels')
                                : __('marketplace.profile.funnels_helper'))
                            ->multiple()
                            ->options($funnels),
                        TagsInput::make('postal_prefixes')
                            ->label(__('marketplace.profile.postal_prefixes'))
                            ->helperText(__('marketplace.profile.postal_prefixes_helper'))
                            // Komma und Leerzeichen schliessen ein Schlagwort
                            // ab -- getippt wie in einem Textfeld, gespeichert
                            // als Liste.
                            ->splitKeys([',', ' '])
                            ->rules(['array', 'max:'.$maximum])
                            // Jedes Praefix einzeln geprueft: die Meldung soll
                            // sagen, welche Eingabe nicht passt.
                            ->nestedRecursiveRules(['regex:/^[0-9]{1,'.$maxLength.'}$/'])
                            ->validationMessages([
                                'max' => __('marketplace.profile.validation.too_many_prefixes', ['max' => $maximum]),
                                'regex' => __('marketplace.profile.validation.prefix_format', [
                                    'prefix' => ':input',
                                    'max' => $maxLength,
                                ]),
                            ]),
                        Repeater::make('answer_filters')
                            ->label(__('marketplace.profile.answer_filters'))
                            ->helperText(__('marketplace.profile.answer_filters_helper'))
                            ->addActionLabel(__('marketplace.profile.add_filter'))
                            ->schema([
                                TextInput::make('field_key')
                                    ->label(__('marketplace.profile.field_key_placeholder'))
                                    ->maxLength(64),
                                TagsInput::make('values')
                                    ->label(__('marketplace.profile.values_placeholder'))
                                    ->splitKeys([',', ' ']),
                            ])
                            ->columns(2)
                            ->defaultItems(0),
                        TextInput::make('min_score')
                            ->label(__('marketplace.profile.min_score'))
                            ->helperText(__('marketplace.profile.min_score_helper'))
                            ->integer()
                            ->validationMessages(['integer' => __('marketplace.profile.validation.min_score')]),
                        TextInput::make('daily_limit')
                            ->label(__('marketplace.profile.daily_limit'))
                            ->helperText(__('marketplace.profile.daily_limit_helper'))
                            ->integer()
                            ->minValue(0)
                            ->validationMessages([
                                'integer' => __('marketplace.profile.validation.daily_limit'),
                                'min' => __('marketplace.profile.validation.daily_limit'),
                            ]),
                        Toggle::make('auto_buy')
                            ->label(__('marketplace.profile.auto_buy'))
                            ->helperText(__('marketplace.profile.auto_buy_helper')),
                        TextInput::make('notify_email')
                            ->label(__('marketplace.profile.notify_email'))
                            ->helperText(__('marketplace.profile.notify_email_helper'))
                            ->email()
                            ->maxLength(255)
                            ->validationMessages(['email' => __('marketplace.profile.validation.notify_email')]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        /** @var array<string, mixed> $data */
        $data = $this->getSchema('form')?->getState() ?? [];

        $profile = $this->profile();

        $profile->fill([
            'funnel_ids' => array_values(array_map('intval', (array) ($data['funnel_ids'] ?? []))),
            'postal_prefixes' => $this->cleanList((array) ($data['postal_prefixes'] ?? [])),
            'answer_filters' => $this->parsedAnswerFilters((array) ($data['answer_filters'] ?? [])),
            'min_score' => $data['min_score'] === null || $data['min_score'] === '' ? null : (int) $data['min_score'],
            'daily_limit' => $data['daily_limit'] === null || $data['daily_limit'] === '' ? null : (int) $data['daily_limit'],
            'auto_buy' => (bool) ($data['auto_buy'] ?? false),
            'notify_email' => ($data['notify_email'] ?? '') === '' ? null : (string) $data['notify_email'],
        ]);

        $profile->save();

        Notification::make()
            ->success()
            ->title(__('marketplace.profile.saved'))
            ->send();
    }

    /**
     * Zeilen des Formulars als Feldschluessel => erlaubte Werte. Zeilen ohne
     * Schluessel oder ohne Werte schraenken nicht ein und fallen weg -- sie
     * wuerden sonst jeden Lead ausschliessen.
     *
     * @param  array<int, mixed>  $rows
     * @return array<string, list<string>>
     */
    private function parsedAnswerFilters(array $rows): array
    {
        $filters = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $fieldKey = trim((string) ($row['field_key'] ?? ''));

            if ($fieldKey === '') {
                continue;
            }

            $values = $this->cleanList((array) ($row['values'] ?? []));

            if ($values !== []) {
                $filters[$fieldKey] = $values;
            }
        }

        return $filters;
    }

    /**
     * Leerraum und Doppelte fallen weg.
     *
     * @param  array<int|string, mixed>  $values
     * @return list<string>
     */
    private function cleanList(array $values): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $values),
            static fn (string $value): bool => $value !== '',
        )));
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
