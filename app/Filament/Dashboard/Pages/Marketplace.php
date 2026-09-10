<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Constants\FunnelFieldKey;
use App\Constants\LeadState;
use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\LeadNotPurchasableException;
use App\Marketplace\MarketplaceListing;
use App\Models\BuyerProfile;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\LeadWatchlistEntry;
use App\Models\Scopes\TenantScopes;
use App\Models\Tenant;
use App\Models\User;
use App\Presenters\LeadPresenter;
use App\Services\CreditLedgerService;
use App\Services\LeadPurchaseAction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
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
            ->columns([
                TextColumn::make('contact_name')
                    ->label(__('leads.contact.name'))
                    // Kontaktdaten ausschliesslich ueber den Presenter.
                    ->state(fn (Lead $record): string => $this->presenter($record)->name())
                    ->description(fn (Lead $record): ?string => $this->presenter($record)->isContactMasked()
                        ? __('marketplace.listing.masked_hint')
                        : null),
                TextColumn::make('funnel.name')
                    ->label(__('leads.list.funnel'))
                    ->placeholder(__('marketplace.listing.unknown_funnel')),
                TextColumn::make('created_at')
                    ->label(__('leads.list.received_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('score')
                    ->label(__('leads.list.score'))
                    ->sortable(),
                TextColumn::make('contact_postal_code')
                    ->label(__('marketplace.listing.region'))
                    ->state(fn (Lead $record): string => $this->presenter($record)->postalCode()),
                TextColumn::make('contact_email')
                    ->label(__('marketplace.listing.email'))
                    ->state(fn (Lead $record): string => $this->presenter($record)->email()),
                TextColumn::make('contact_phone')
                    ->label(__('marketplace.listing.phone'))
                    ->state(fn (Lead $record): string => $this->presenter($record)->phone()),
                TextColumn::make('availability')
                    ->label(__('marketplace.sale_mode.exclusive'))
                    ->badge()
                    ->state(fn (Lead $record): ?string => $this->availabilityLabel($record))
                    ->color(fn (Lead $record): string => $record->lead_state === LeadState::RESERVIERT ? 'warning' : 'info'),
                TextColumn::make('qualification')
                    ->label(__('leads.detail.answers'))
                    ->state(fn (Lead $record): array => $this->qualificationAnswers($record))
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->toggleable(),
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
                Action::make('watch')
                    ->label(fn (Lead $record): string => $this->isWatchlisted($record)
                        ? __('marketplace.listing.unwatch')
                        : __('marketplace.listing.watch'))
                    ->icon(Heroicon::OutlinedBookmark)
                    ->link()
                    ->action(fn (Lead $record) => $this->toggleWatchlist($record)),
                Action::make('purchase')
                    ->label(__('marketplace.listing.purchase'))
                    ->icon(Heroicon::OutlinedShoppingCart)
                    ->button()
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
     * Wechselt die Vormerkung eines Leads. Sie ist eine private Notiz des
     * Kaeufers und aendert am Lead nichts -- ein vorgemerkter Lead kann
     * jederzeit von jemand anderem gekauft werden.
     */
    private function toggleWatchlist(Lead $lead): void
    {
        $existing = LeadWatchlistEntry::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->where('tenant_id', $this->tenant()->getKey())
            ->where('lead_id', $lead->getKey())
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return;
        }

        LeadWatchlistEntry::query()->create([
            'tenant_id' => $this->tenant()->getKey(),
            'lead_id' => $lead->getKey(),
        ]);
    }

    /**
     * Kauft einen Lead.
     *
     * Die Seite entscheidet nichts: Sie reicht an die PurchaseLead-Action
     * weiter, die Reservierung, Guthaben und Zustand unter Sperre prueft. Was
     * hier abgefangen wird, sind die beiden alltaeglichen Ausgaenge -- ein
     * anderer war schneller, oder das Guthaben reicht nicht. Beides ist ein
     * Hinweis an den Kaeufer, kein Fehler.
     */
    private function purchase(Lead $lead): void
    {
        $actor = $this->viewer();

        if (! $actor instanceof User) {
            return;
        }

        try {
            app(LeadPurchaseAction::class)->purchase($this->tenant(), $lead, $actor);
        } catch (LeadNotPurchasableException|InsufficientCreditsException $exception) {
            Notification::make()
                ->warning()
                ->title($exception->getMessage())
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title(__('marketplace.purchase.done'))
            ->send();
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
            ])
            ->whereIn('id', $matched->map(static fn (Lead $lead): int => (int) $lead->getKey())->all());
    }

    private function presenter(Lead $lead): LeadPresenter
    {
        return new LeadPresenter($lead, $this->viewer());
    }

    /**
     * Vergriffen, oder im Mehrfachverkauf: wie viele den Lead schon haben
     * (FB-055).
     */
    private function availabilityLabel(Lead $lead): ?string
    {
        if ($lead->lead_state === LeadState::RESERVIERT) {
            return __('marketplace.listing.taken');
        }

        $funnel = $lead->funnel;

        if (! $funnel instanceof Funnel || ! $funnel->sale_mode->isShared()) {
            return null;
        }

        return __('marketplace.sale_mode.buyers', [
            'buyers' => LeadPurchase::query()->where('lead_id', $lead->getKey())->count(),
            'max' => $funnel->effectiveMaxBuyers(),
        ]);
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
        $answers = [];

        foreach ($lead->answers as $answer) {
            if (FunnelFieldKey::isReserved($answer->field_key)) {
                continue;
            }

            $value = $answer->value;

            $answers[] = $answer->field_key.': '.(is_array($value)
                ? implode(', ', array_map(static fn (mixed $part): string => (string) $part, $value))
                : (string) $value);
        }

        return $answers;
    }

    private function isWatchlisted(Lead $lead): bool
    {
        return LeadWatchlistEntry::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->where('tenant_id', $this->tenant()->getKey())
            ->where('lead_id', $lead->getKey())
            ->exists();
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
