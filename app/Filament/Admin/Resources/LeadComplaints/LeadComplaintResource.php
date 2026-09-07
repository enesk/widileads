<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\LeadComplaints;

use App\Constants\ComplaintStatus;
use App\Filament\Admin\Resources\LeadComplaints\Pages\ListLeadComplaints;
use App\Models\LeadComplaint;
use App\Models\User;
use App\Services\LeadComplaintService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * FB-058: Die Pruefliste der Reklamationen.
 *
 * Hier entscheidet ein Mensch, ob eine Gutschrift fliesst. Automatisch geht das
 * nicht: Der Kaeufer, der reklamiert, ist derselbe, der davon profitiert.
 *
 * Beide Entscheidungen laufen ueber den LeadComplaintService -- Zustandswechsel
 * und Gutschrift gehoeren in eine Transaktion, und die steht dort.
 */
class LeadComplaintResource extends Resource
{
    protected static ?string $model = LeadComplaint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?int $navigationSort = 13;

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public static function getModelLabel(): string
    {
        return __('marketplace.complaint.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('marketplace.complaint.resource.plural_label');
    }

    /**
     * Offene Antraege in der Navigation -- eine Reklamation, die niemand
     * bemerkt, ist ein wartender Kaeufer mit gebundenem Guthaben.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = LeadComplaint::query()->pending()->count();

        return $pending === 0 ? null : (string) $pending;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->description(__('marketplace.complaint.resource.read_only'))
                ->schema([
                    TextEntry::make('created_at')
                        ->label(__('marketplace.complaint.fields.created_at'))
                        ->dateTime(),
                    TextEntry::make('status')
                        ->label(__('marketplace.complaint.fields.status'))
                        ->badge()
                        ->formatStateUsing(fn (ComplaintStatus $state): string => $state->label()),
                    TextEntry::make('buyer.name')
                        ->label(__('marketplace.complaint.fields.buyer')),
                    TextEntry::make('requested_state')
                        ->label(__('marketplace.complaint.fields.requested_state'))
                        ->formatStateUsing(fn (mixed $state): string => $state->label()),
                    TextEntry::make('reason')
                        ->label(__('marketplace.complaint.fields.reason'))
                        ->columnSpanFull(),
                    TextEntry::make('reviewer.name')
                        ->label(__('marketplace.complaint.fields.reviewed_by'))
                        ->placeholder('-'),
                    TextEntry::make('reviewed_at')
                        ->label(__('marketplace.complaint.fields.reviewed_at'))
                        ->dateTime()
                        ->placeholder('-'),
                    TextEntry::make('decision_note')
                        ->label(__('marketplace.complaint.fields.decision_note'))
                        ->placeholder('-')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'asc')
            ->emptyStateHeading(__('marketplace.complaint.resource.empty_heading'))
            ->emptyStateDescription(__('marketplace.complaint.resource.empty_description'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('marketplace.complaint.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('buyer.name')
                    ->label(__('marketplace.complaint.fields.buyer'))
                    ->searchable(),
                TextColumn::make('requested_state')
                    ->label(__('marketplace.complaint.fields.requested_state'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state->label()),
                TextColumn::make('status')
                    ->label(__('marketplace.complaint.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (ComplaintStatus $state): string => $state->label())
                    ->color(fn (ComplaintStatus $state): string => match ($state) {
                        ComplaintStatus::PENDING => 'warning',
                        ComplaintStatus::APPROVED => 'success',
                        ComplaintStatus::REJECTED => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('reason')
                    ->label(__('marketplace.complaint.fields.reason'))
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('marketplace.complaint.fields.status'))
                    ->options(fn (): array => ComplaintStatus::options()),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('approve')
                    ->label(__('marketplace.complaint.actions.approve'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->modalDescription(__('marketplace.complaint.actions.approve_confirm'))
                    ->schema([
                        Textarea::make('note')
                            ->label(__('marketplace.complaint.fields.decision_note'))
                            ->maxLength(1000),
                    ])
                    ->visible(fn (LeadComplaint $record): bool => $record->status->isPending())
                    ->action(fn (array $data, LeadComplaint $record) => app(LeadComplaintService::class)
                        ->approve($record, self::actingAdmin(), $data['note'] ?? null))
                    ->successNotificationTitle(__('marketplace.complaint.actions.approved')),

                Action::make('reject')
                    ->label(__('marketplace.complaint.actions.reject'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->schema([
                        Textarea::make('note')
                            ->label(__('marketplace.complaint.fields.decision_note'))
                            ->helperText(__('marketplace.complaint.hints.decision_note'))
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000),
                    ])
                    ->visible(fn (LeadComplaint $record): bool => $record->status->isPending())
                    ->action(fn (array $data, LeadComplaint $record) => app(LeadComplaintService::class)
                        ->reject($record, self::actingAdmin(), $data['note']))
                    ->successNotificationTitle(__('marketplace.complaint.actions.rejected')),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeadComplaints::route('/'),
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

    private static function actingAdmin(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
