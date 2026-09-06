<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Leads;

use App\Constants\LeadState;
use App\Constants\LeadTransitions;
use App\Filament\Admin\Resources\Leads\Pages\ListLeads;
use App\Models\Lead;
use App\Models\User;
use App\Services\ManualLeadStateService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * FB-036: Lead-Uebersicht im Admin-Panel mit der manuellen Statussetzung.
 *
 * Bewusst nur Lesen und die eine Aktion. Die Arbeitsoberflaeche des Betreibers
 * -- Lead-Liste und Lead-Detail mit Filtern, Suche und Maskierung -- baut
 * FB-034 in Livewire; hier geht es allein um den Notausgang des
 * Plattform-Admins.
 */
class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 11;

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public static function getModelLabel(): string
    {
        return __('funnel.lead.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('funnel.lead.resource.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('funnel.lead.resource.empty_heading'))
            ->emptyStateDescription(__('funnel.lead.resource.empty_description'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('funnel.lead.fields.id'))
                    ->sortable(),
                TextColumn::make('tenant.name')
                    ->label(__('funnel.lead.fields.tenant'))
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('lead_state')
                    ->label(__('funnel.lead.fields.lead_state'))
                    ->badge()
                    ->formatStateUsing(fn (LeadState $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('settled_price')
                    ->label(__('funnel.lead.fields.settled_price'))
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('funnel.lead.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('anonymized_at')
                    ->label(__('funnel.lead.fields.anonymized_at'))
                    ->dateTime()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('lead_state')
                    ->label(__('funnel.lead.fields.lead_state'))
                    ->options(fn (): array => LeadState::labels()),
            ])
            ->recordActions([
                self::forceStateAction(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
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

    /**
     * Zwangsstatuswechsel mit Pflichtbegruendung.
     *
     * Die Aktion bietet nur Zustaende an, die LeadTransitions vom aktuellen
     * Zustand aus erlaubt, und ist unsichtbar, sobald es keine gibt (Lead in
     * einem Endzustand). Die eigentliche Pruefung macht trotzdem der Dienst --
     * die Oberflaeche ist Bequemlichkeit, nicht die Absicherung.
     */
    private static function forceStateAction(): Action
    {
        return Action::make('forceState')
            ->label(__('funnel.lead.force_state.action'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->modalHeading(__('funnel.lead.force_state.heading'))
            ->modalDescription(__('funnel.lead.force_state.description'))
            ->modalSubmitActionLabel(__('funnel.lead.force_state.submit'))
            ->visible(fn (Lead $record): bool => LeadTransitions::allowedFrom($record->lead_state) !== []
                && Gate::allows('leads.force-state', $record))
            ->schema([
                Select::make('lead_state')
                    ->label(__('funnel.lead.force_state.target'))
                    ->options(fn (Lead $record): array => self::allowedTargetOptions($record))
                    ->native(false)
                    ->required(),
                Textarea::make('justification')
                    ->label(__('funnel.lead.force_state.justification'))
                    ->helperText(__('funnel.lead.force_state.justification_helper', [
                        'min' => self::minimumJustificationLength(),
                    ]))
                    ->minLength(self::minimumJustificationLength())
                    ->rows(3)
                    ->required(),
            ])
            ->action(function (array $data, Lead $record): void {
                $actor = auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                app(ManualLeadStateService::class)->force(
                    $record,
                    LeadState::from((string) $data['lead_state']),
                    (string) $data['justification'],
                    $actor,
                );

                Notification::make()
                    ->success()
                    ->title(__('funnel.lead.force_state.done', [
                        'state' => $record->lead_state->label(),
                    ]))
                    ->send();
            });
    }

    /**
     * @return array<string, string>
     */
    private static function allowedTargetOptions(Lead $lead): array
    {
        $options = [];

        foreach (LeadTransitions::allowedFrom($lead->lead_state) as $state) {
            $options[$state->value] = $state->label();
        }

        return $options;
    }

    private static function minimumJustificationLength(): int
    {
        return (int) config('funnel.lead.manual_state_min_justification_length');
    }
}
