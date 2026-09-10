<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Resources\Funnels;

use App\Actions\ArchiveFunnel;
use App\Actions\CreateFunnelPreviewLink;
use App\Actions\DuplicateFunnel;
use App\Actions\PublishFunnel;
use App\Constants\FunnelStatus;
use App\Exceptions\FunnelNotPublishableException;
use App\Filament\Dashboard\Pages\FunnelBuilder;
use App\Filament\Dashboard\Pages\FunnelNotificationSettings;
use App\Filament\Dashboard\Pages\FunnelRules;
use App\Filament\Dashboard\Pages\FunnelSaleSettings;
use App\Filament\Dashboard\Pages\ThemeEditor;
use App\Filament\Dashboard\Resources\Funnels\Pages\ListFunnels;
use App\Models\Funnel;
use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Funnels des Betreibers (seit FB-090 als Filament-Resource).
 *
 * Die Aktionen bleiben, was sie waren: Veroeffentlichen, Duplizieren,
 * Archivieren und der befristete Vorschau-Link rufen dieselben Actions aus
 * FB-014 und FB-018 auf. Neu ist nur, wer sie anbietet.
 *
 * Der Builder selbst bleibt eigenstaendig -- Drag-and-drop, Autosave und
 * Dreispaltenlayout waeren in einem Filament-Formular ein Rueckschritt.
 */
class FunnelResource extends Resource
{
    protected static ?string $model = Funnel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('builder.funnels.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('builder.funnels.nav_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.funnels');
    }

    public static function getNavigationLabel(): string
    {
        return __('builder.funnels.nav_label');
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
        return $schema->components([
            TextInput::make('name')
                ->label(__('builder.funnels.new_name'))
                ->placeholder(__('builder.funnels.new_placeholder'))
                ->required()
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('builder.funnels.empty'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('builder.funnels.name'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (Funnel $record): string => (string) $record->slug),
                TextColumn::make('status')
                    ->label(__('builder.funnels.status'))
                    ->badge()
                    ->formatStateUsing(fn (FunnelStatus $state): string => $state->label())
                    ->color(fn (FunnelStatus $state): string => match ($state) {
                        FunnelStatus::PUBLISHED => 'success',
                        FunnelStatus::ARCHIVED => 'gray',
                        default => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('steps_count')
                    ->label(__('builder.funnels.size'))
                    ->counts(['steps', 'questions'])
                    ->state(fn (Funnel $record): string => __('builder.funnels.counts', [
                        'steps' => $record->steps_count ?? 0,
                        'questions' => $record->questions_count ?? 0,
                    ])),
                TextColumn::make('created_at')
                    ->label(__('builder.funnels.created_at'))
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('builder.funnels.status'))
                    ->options(fn (): array => FunnelStatus::labels()),
            ])
            ->recordActions([
                Action::make('builder')
                    ->label(__('builder.funnels.open_builder'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Funnel $record): string => FunnelBuilder::getUrl(['funnel' => $record])),
                Action::make('rules')
                    ->label(__('builder.funnels.open_rules'))
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->url(fn (Funnel $record): string => FunnelRules::getUrl(['funnel' => $record])),
                Action::make('theme')
                    ->label(__('builder.funnels.open_theme'))
                    ->icon(Heroicon::OutlinedSwatch)
                    ->url(fn (Funnel $record): string => ThemeEditor::getUrl(['funnel' => $record])),
                Action::make('sale')
                    ->label(__('builder.funnels.open_sale'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->url(fn (Funnel $record): string => FunnelSaleSettings::getUrl(['funnel' => $record])),
                Action::make('notifications')
                    ->label(__('builder.funnels.open_notifications'))
                    ->icon(Heroicon::OutlinedBell)
                    ->url(fn (Funnel $record): string => FunnelNotificationSettings::getUrl(['funnel' => $record])),
                Action::make('publish')
                    ->label(__('builder.funnels.publish'))
                    ->icon(Heroicon::OutlinedRocketLaunch)
                    ->color('success')
                    ->visible(fn (Funnel $record): bool => $record->status !== FunnelStatus::ARCHIVED)
                    ->action(function (Funnel $record): void {
                        try {
                            app(PublishFunnel::class)->handle($record, auth()->user());

                            Notification::make()->success()
                                ->title(__('builder.funnels.published', ['name' => $record->name]))
                                ->send();
                        } catch (FunnelNotPublishableException $exception) {
                            // Die Gruende kommen fertig formuliert aus FB-014 und
                            // sagen genau, was noch fehlt.
                            Notification::make()->danger()
                                ->title(__('builder.funnels.not_publishable'))
                                ->body(implode("\n", $exception->reasons))
                                ->persistent()
                                ->send();
                        }
                    }),
                Action::make('preview')
                    ->label(__('builder.funnels.preview'))
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading(__('builder.funnels.preview_heading'))
                    ->modalDescription(__('builder.funnels.preview_hint'))
                    ->modalSubmitAction(false)
                    ->modalContent(fn (Funnel $record) => view('filament.dashboard.partials.preview-link', [
                        'link' => app(CreateFunnelPreviewLink::class)->handle($record),
                    ])),
                Action::make('duplicate')
                    ->label(__('builder.funnels.duplicate'))
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->requiresConfirmation()
                    ->action(function (Funnel $record): void {
                        $copy = app(DuplicateFunnel::class)->handle($record);

                        Notification::make()->success()
                            ->title(__('builder.funnels.duplicated', ['name' => $copy->name]))
                            ->send();
                    }),
                Action::make('archive')
                    ->label(__('builder.funnels.archive'))
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('builder.funnels.confirm_archive'))
                    ->visible(fn (Funnel $record): bool => $record->status !== FunnelStatus::ARCHIVED)
                    ->action(fn (Funnel $record) => app(ArchiveFunnel::class)->handle($record)),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFunnels::route('/'),
        ];
    }

    public static function canEdit(Model $record): bool
    {
        // Bearbeitet wird im Builder, nicht in einem Formular.
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        // Funnels werden archiviert, nicht geloescht -- ihre Leads bleiben.
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
            ->where('tenant_id', Filament::getTenant()?->getKey());
    }

    /**
     * Ein je Mandant eindeutiger Slug. Der Unique-Index laeuft ueber
     * (tenant_id, slug); ein Duplikat waere ein Fehler beim Anlegen.
     */
    public static function freeSlug(Tenant $tenant, string $name): string
    {
        $base = Str::slug($name) ?: 'funnel';
        $taken = $tenant->funnels()->pluck('slug')->all();

        $candidate = $base;
        $suffix = 1;

        while (in_array($candidate, $taken, true)) {
            $suffix++;
            $candidate = $base.'-'.$suffix;
        }

        return $candidate;
    }
}
