<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Resources\Leads;

use App\Constants\LeadState;
use App\Constants\TenancyPermissionConstants;
use App\Dto\LeadContact;
use App\Filament\Dashboard\Resources\Leads\Pages\ListLeads;
use App\Filament\Dashboard\Resources\Leads\Pages\ViewLead;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LeadListQuery;
use App\Services\TenantPermissionService;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Leads des Betreibers (FB-034, seit FB-090 als Filament-Resource).
 *
 * Kontaktdaten kommen ausschliesslich ueber `Lead::contactFor()` -- auch in
 * einer Tabellenspalte. Eine Filament-Column, die direkt auf `email_normalized`
 * oder `postal_code` zugreift, umgeht die Maskierung; der Architektur-Test aus
 * FB-032 schlaegt darauf an.
 *
 * Die Filterlogik bleibt im LeadListQuery: Sie ist Fachlogik und soll ohne
 * Oberflaeche pruefbar bleiben. Die Resource ruft sie auf, statt sie zu
 * wiederholen.
 */
class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('leads.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('leads.list.heading');
    }

    public static function getNavigationLabel(): string
    {
        return __('leads.list.nav_label');
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant || ! auth()->user() instanceof User) {
            return false;
        }

        return Gate::allows('funnels.manage', $tenant);
    }

    public static function form(Schema $schema): Schema
    {
        // Leads entstehen aus Funnel-Einreichungen, nicht von Hand.
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        $leads = app(LeadListQuery::class);

        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('leads.list.empty'))
            ->recordUrl(fn (Lead $record): string => ViewLead::getUrl(['record' => $record]))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('leads.list.received_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('contact_name')
                    ->label(__('leads.contact.name'))
                    // Auch hier ueber contactFor(): Die Spalte entscheidet nicht,
                    // was sie zeigen darf.
                    ->state(fn (Lead $record): string => self::contactOf($record)->fullName() ?? __('leads.contact.unknown'))
                    ->description(fn (Lead $record): ?string => $leads->isStale($record)
                        ? __('leads.list.stale_hint', ['days' => (int) config('funnel.lead.stale_after_days')])
                        : null)
                    ->badge(fn (Lead $record): bool => $leads->isStale($record))
                    ->color(fn (Lead $record): string => $leads->isStale($record) ? 'warning' : 'gray')
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchBy($query, $search)),
                TextColumn::make('funnel.name')
                    ->label(__('leads.list.funnel'))
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('lead_state')
                    ->label(__('leads.list.state'))
                    ->badge()
                    ->formatStateUsing(fn (LeadState $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('score')
                    ->label(__('leads.list.score'))
                    ->sortable(),
                TextColumn::make('contact_postal_code')
                    ->label(__('leads.contact.postal_code'))
                    ->state(fn (Lead $record): string => self::contactOf($record)->postalCode ?? '-'),
            ])
            ->filters([
                SelectFilter::make('funnel')
                    ->label(__('leads.list.funnel'))
                    ->relationship('funnel', 'name'),
                SelectFilter::make('lead_state')
                    ->label(__('leads.list.state'))
                    ->options(fn (): array => LeadState::labels()),
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label(__('leads.list.from')),
                        DatePicker::make('until')->label(__('leads.list.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $from): Builder => $query->whereDate('created_at', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $query, string $until): Builder => $query->whereDate('created_at', '<=', $until))),
                Filter::make('postal_prefix')
                    ->schema([
                        TextInput::make('prefix')
                            ->label(__('leads.list.postal_prefix'))
                            ->maxLength(5),
                    ])
                    // Gefiltert wird serverseitig auf dem Klartext -- auch wenn
                    // die Postleitzahl in der Spalte maskiert erscheint.
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['prefix'] ?? null, fn (Builder $query, string $prefix): Builder => $query
                            ->where('postal_code', 'like', $prefix.'%'))),
                Filter::make('score_range')
                    ->schema([
                        TextInput::make('min')->label(__('leads.list.score_min'))->numeric(),
                        TextInput::make('max')->label(__('leads.list.score_max'))->numeric(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['min'] ?? null, fn (Builder $query, string $min): Builder => $query->where('score', '>=', (int) $min))
                        ->when($data['max'] ?? null, fn (Builder $query, string $max): Builder => $query->where('score', '<=', (int) $max))),
                Filter::make('stale')
                    ->label(__('leads.list.stale'))
                    ->query(fn (Builder $query): Builder => $query
                        ->where('lead_state', LeadState::VERFUEGBAR->value)
                        ->where('created_at', '<', app(LeadListQuery::class)->staleBefore())),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
            'view' => ViewLead::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->where('tenant_id', Filament::getTenant()?->getKey())
            ->with(['answers', 'funnel']);
    }

    /**
     * Kontaktdaten aus Sicht des angemeldeten Benutzers -- maskiert oder nicht.
     */
    public static function contactOf(Lead $lead): LeadContact
    {
        $viewer = auth()->user();

        return $lead->contactFor($viewer instanceof User ? $viewer : null);
    }

    /**
     * Volltextsuche. Der Umfang haengt an der Berechtigung: Verwalter suchen
     * ueber Name, E-Mail und Telefon, alle anderen nur ueber den Namen
     * (FB-034).
     *
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    private static function searchBy(Builder $query, string $search): Builder
    {
        $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim($search)).'%';
        $tenant = Filament::getTenant();
        $viewer = auth()->user();

        $searchesContacts = $tenant instanceof Tenant
            && $viewer instanceof User
            && app(TenantPermissionService::class)->tenantUserHasPermissionTo(
                $tenant,
                $viewer,
                TenancyPermissionConstants::PERMISSION_SEARCH_LEAD_CONTACTS,
            );

        return $query->where(function (Builder $query) use ($needle, $searchesContacts): void {
            $query->whereHas('answers', function ($answers) use ($needle): void {
                $answers->whereIn('field_key', ['vorname', 'nachname', 'name'])
                    ->where('value', 'like', $needle);
            });

            if ($searchesContacts) {
                $query->orWhere('email_normalized', 'like', $needle)
                    ->orWhere('phone_e164', 'like', $needle);
            }
        });
    }
}
