<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Constants\FunnelFieldKey;
use App\Funnel\Snapshots\SnapshotLabels;
use App\Marketplace\MarketplaceListing;
use App\Models\BuyerProfile;
use App\Models\Lead;
use App\Models\Scopes\TenantScopes;
use App\Models\Tenant;
use App\Models\User;
use App\Presenters\LeadPresenter;
use App\Services\CreditLedgerService;
use App\Services\LeadPurchaseAction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;

/**
 * Der Lead-Marktplatz eines Kaeufers (FB-053, seit FB-090 als Filament-Tabelle).
 *
 * **Diese Seite maskiert nichts.** Kontaktdaten kommen ausschliesslich ueber
 * den LeadPresenter, der `Lead::contactFor()` aufruft; wer welche Fassung
 * sieht, entscheidet allein der LeadContactResolver aus FB-032. Eine zweite
 * Maskierlogik waere ein Verstoss gegen Architekturleitsatz 5 -- und der
 * Architektur-Test aus FB-042 schlaegt darauf an.
 *
 * Ebenso wenig kauft sie: Der Knopf fragt die austauschbare Zusage
 * LeadPurchaseAction.
 *
 * Welche Leads sichtbar sind, entscheidet weiterhin das MarketplaceListing --
 * der LeadMatcher ist eine reine PHP-Funktion und laesst sich nicht in eine
 * Tabellenabfrage uebersetzen. Die Tabelle bekommt deshalb die bereits
 * geprueften Kennungen und arbeitet darauf weiter.
 */
class Marketplace extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.dashboard.pages.marketplace';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?int $navigationSort = -10;

    public function getHeading(): string|Htmlable
    {
        return __('marketplace.listing.heading');
    }

    public function getTitle(): string|Htmlable
    {
        return __('marketplace.listing.heading');
    }

    public function getSubheading(): string|Htmlable|null
    {
        $balance = __('marketplace.purchase.balance', [
            'credits' => app(CreditLedgerService::class)->balanceFor($this->tenant()),
        ]);

        return $this->profile() === null
            ? $balance.' — '.__('marketplace.listing.no_profile')
            : $balance;
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.marketplace');
    }

    public static function getNavigationLabel(): string
    {
        return __('marketplace.listing.nav_label');
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        return Gate::allows('marketplace.access', $tenant);
    }

    public function table(Table $table): Table
    {
        $purchase = app(LeadPurchaseAction::class);

        return $table
            ->query(fn (): Builder => $this->visibleLeadsQuery())
            ->defaultSort('created_at', 'desc')
            ->paginated([(int) config('funnel.marketplace.listing.per_page')])
            ->description(__('marketplace.listing.description'))
            ->emptyStateHeading(__('marketplace.listing.empty'))
            // Karten statt Tabellenzeilen: Ein Angebot ist etwas, das man
            // ansieht und kauft -- keine Zeile, die man mit anderen vergleicht.
            // Die frueheren Spalten standen ohnehin fast leer, seit Region,
            // E-Mail und Telefon draussen sind.
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->columns([
                // Panel gibt der Karte Rahmen und Innenabstand -- ohne ihn
                // schweben die Angaben frei im Raster.
                Panel::make([
                    Stack::make([
                        TextColumn::make('contact_name')
                            ->label(__('leads.contact.name'))
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large)
                            // Kontaktdaten ausschliesslich ueber den Presenter.
                            ->state(fn (Lead $record): string => $this->presenter($record)->name()),
                        TextColumn::make('created_at')
                            ->label(__('leads.list.received_at'))
                            ->icon(Heroicon::OutlinedClock)
                            ->color('gray')
                            ->size(TextSize::Small)
                            ->dateTime('d.m.Y H:i')
                            ->sortable(),
                        TextColumn::make('masked_hint')
                            ->color('gray')
                            ->size(TextSize::Small)
                            ->state(fn (Lead $record): ?string => $this->presenter($record)->isContactMasked()
                                ? __('marketplace.listing.masked_hint')
                                : null),
                        TextColumn::make('qualification')
                            ->label(__('leads.detail.answers'))
                            ->badge()
                            ->color('gray')
                            ->state(fn (Lead $record): array => $this->qualificationAnswers($record))
                            ->listWithLineBreaks()
                            ->limitList(4)
                            ->expandableLimitedList(),
                    ])->space(3),
                ]),
            ])
            ->filters([
                Filter::make('watchlisted')
                    ->label(__('marketplace.listing.only_watchlisted'))
                    ->toggle()
                    // Die Merkliste engt schon die Vorauswahl ein (siehe
                    // visibleLeadsQuery), sonst wuerde die Obergrenze aus
                    // config('funnel.marketplace.listing.candidate_limit')
                    // vorgemerkte Leads abschneiden, bevor der Filter greift.
                    ->query(fn (Builder $query): Builder => $query),
            ])
            ->recordActions([
                Action::make('purchase')
                    ->label(__('marketplace.listing.purchase'))
                    ->icon(Heroicon::OutlinedShoppingCart)
                    ->button()
                    // Eigene Farbe, im Panel als 'pastel' hinterlegt.
                    ->color('pastel')
                    ->requiresConfirmation()
                    ->modalDescription(__('marketplace.purchase.confirm'))
                    ->disabled(fn (Lead $record): bool => ! $purchase->isAvailable()
                        || ! $purchase->canPurchase($this->tenant(), $record))
                    ->tooltip(fn (): ?string => $purchase->isAvailable()
                        ? null
                        : __('marketplace.listing.purchase_unavailable'))
                    ->action(fn (Lead $record) => $this->purchase($record)),
            ]);
    }

    /**
     * Die Leads, die dieser Kaeufer sehen darf -- als Abfrage, damit Filament
     * sortieren und blaettern kann.
     *
     * @return Builder<Lead>
     */
    private function visibleLeadsQuery(): Builder
    {
        $matched = app(MarketplaceListing::class)->for(
            $this->tenant(),
            $this->profile(),
            $this->getTableSortColumn() === 'score'
                ? MarketplaceListing::SORT_SCORE
                : MarketplaceListing::SORT_NEWEST,
            (bool) ($this->getTableFilterState('watchlisted')['isActive'] ?? false),
        );

        return Lead::query()
            // Der Kaeufer besitzt keinen dieser Leads -- ohne das Abschalten
            // des Mandanten-Scopes waere der Marktplatz immer leer. Welche
            // Leads das sind, hat das MarketplaceListing entschieden.
            ->withoutGlobalScopes(TenantScopes::names())
            ->with([
                'answers',
                // Der Funnel gehoert dem Betreiber, gelesen wird im Kontext des
                // Kaeufers -- ohne das Abschalten der Mandanten-Scopes bliebe
                // die Spalte "Fragebogen" leer (FB-055a).
                'funnel' => static fn (Relation $funnel) => $funnel->withoutGlobalScopes(TenantScopes::names()),
                'funnelVersion' => static fn (Relation $version) => $version->withoutGlobalScopes(TenantScopes::names()),
            ])
            ->whereIn('id', $matched->map(static fn (Lead $lead): int => (int) $lead->getKey())->all());
    }

    private function presenter(Lead $lead): LeadPresenter
    {
        return new LeadPresenter($lead, $this->viewer());
    }

    /**
     * Die Qualifizierungsantworten eines Leads -- **ohne** die reservierten
     * Kontaktfelder.
     *
     * Das ist der Punkt, an dem eine Marktplatzliste am ehesten Klartext
     * ausplaudert: `lead_answers` enthaelt auch die Antworten auf `email`,
     * `telefon` und `plz`. Wer sie ungefiltert ausgibt, umgeht die Maskierung,
     * ohne je eine Kontaktspalte anzufassen.
     *
     * @return array<int, string>
     */
    private function qualificationAnswers(Lead $lead): array
    {
        // Beschriftungen aus der Fassung, unter der der Lead entstanden ist:
        // Ein Kaeufer soll lesen, was der Kunde angeklickt hat, nicht
        // "rasse_groesse: gross".
        $snapshot = $lead->funnelVersion?->snapshot;
        $labels = SnapshotLabels::questions(is_array($snapshot) ? $snapshot : null);
        $options = SnapshotLabels::options(is_array($snapshot) ? $snapshot : null);

        $answers = [];

        foreach ($lead->answers as $answer) {
            if (FunnelFieldKey::isReserved($answer->field_key)) {
                continue;
            }

            $readable = static fn (mixed $single): string => $options[$answer->field_key][(string) $single]
                ?? (string) $single;

            $value = $answer->value;

            $answers[] = ($labels[$answer->field_key] ?? $answer->field_key).': '.(is_array($value)
                ? implode(', ', array_map($readable, $value))
                : $readable($value));
        }

        return $answers;
    }

    private function profile(): ?BuyerProfile
    {
        return BuyerProfile::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->where('tenant_id', $this->tenant()->getKey())
            ->first();
    }

    private function tenant(): Tenant
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }

    private function viewer(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
