<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Wallets\RelationManagers;

use App\Constants\WalletTransactionType;
use App\Filament\Admin\Resources\Wallets\WalletResource;
use App\Models\User;
use App\Models\WalletTransaction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * LP-WALLET-013: Das vollstaendige Journal eines Wallets.
 *
 * Rein lesend. Buchungen sind unveraenderlich (WalletTransaction), eine
 * Fehlbuchung wird mit einer Korrektur gegengebucht -- deshalb gibt es hier
 * weder Anlegen noch Bearbeiten noch Loeschen.
 *
 * Die Zeile ist als Split gebaut und traegt darunter ein aufklappbares Panel
 * mit `meta` und `idempotency_key`. Beide gehoeren nicht in die Zeile -- sie
 * werden nur gebraucht, wenn eine einzelne Buchung geprueft wird -- duerfen
 * aber auch nicht fehlen: `meta` traegt den Grund einer Korrektur und den
 * Provisionssatz, `idempotency_key` beantwortet die Frage, ob zwei aehnliche
 * Zeilen eine Doppelbuchung sind.
 */
class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('marketplace.wallet.admin.ledger.heading'))
            ->description(__('marketplace.wallet.admin.ledger.description'))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('marketplace.wallet.admin.ledger.empty_heading'))
            ->columns([
                Split::make([
                    TextColumn::make('created_at')
                        ->label(__('marketplace.wallet.admin.fields.created_at'))
                        ->dateTime(config('app.datetime_format'))
                        ->sortable()
                        ->grow(false),
                    TextColumn::make('type')
                        ->label(__('marketplace.wallet.admin.fields.type'))
                        ->badge()
                        ->formatStateUsing(fn (WalletTransactionType $state): string => __('marketplace.wallet.admin.type.'.$state->value))
                        ->color(fn (WalletTransactionType $state): string => match ($state) {
                            WalletTransactionType::TOPUP, WalletTransactionType::EARNING, WalletTransactionType::REFUND => 'success',
                            WalletTransactionType::RESERVE, WalletTransactionType::RELEASE => 'info',
                            WalletTransactionType::ADJUSTMENT => 'warning',
                            WalletTransactionType::PAYOUT, WalletTransactionType::CAPTURE => 'danger',
                            default => 'gray',
                        })
                        ->grow(false),
                    TextColumn::make('amount_cents')
                        ->label(__('marketplace.wallet.admin.fields.amount'))
                        // Das Vorzeichen ist die Aussage der Zeile und soll
                        // auch dann sichtbar sein, wenn es positiv ist.
                        ->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').WalletResource::money($state))
                        ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                        ->weight('bold')
                        ->grow(false),
                    TextColumn::make('description')
                        ->label(__('marketplace.wallet.admin.fields.description'))
                        ->wrap(),
                    // Die beiden Salden tragen ihren Namen in der Zelle: Eine
                    // Split-Zeile hat keine Kopfzeile, und zwei Geldbetraege
                    // nebeneinander waeren ohne Beschriftung nicht zu
                    // unterscheiden.
                    TextColumn::make('balance_after_cents')
                        ->label(__('marketplace.wallet.admin.fields.balance_after'))
                        ->formatStateUsing(fn (int $state): string => __('marketplace.wallet.admin.fields.balance_after').': '.WalletResource::money($state))
                        ->color(fn (int $state): string => $state < 0 ? 'danger' : 'gray')
                        ->grow(false),
                    TextColumn::make('reserved_after_cents')
                        ->label(__('marketplace.wallet.admin.fields.reserved_after'))
                        ->formatStateUsing(fn (int $state): string => __('marketplace.wallet.admin.fields.reserved_after').': '.WalletResource::money($state))
                        ->grow(false),
                ]),
                Panel::make([
                    Stack::make([
                        TextColumn::make('id')
                            ->label(__('marketplace.wallet.admin.fields.id'))
                            ->formatStateUsing(fn (int $state): string => __('marketplace.wallet.admin.fields.id').': #'.$state),
                        TextColumn::make('idempotency_key')
                            ->label(__('marketplace.wallet.admin.fields.idempotency_key'))
                            ->placeholder('—')
                            ->copyable()
                            ->formatStateUsing(fn (?string $state): string => __('marketplace.wallet.admin.fields.idempotency_key').': '.($state ?? '—')),
                        TextColumn::make('reference_type')
                            ->label(__('marketplace.wallet.admin.fields.reference'))
                            ->formatStateUsing(fn (WalletTransaction $record): string => __('marketplace.wallet.admin.fields.reference').': '.(
                                $record->reference_type === null
                                    ? '—'
                                    : class_basename($record->reference_type).' #'.$record->reference_id
                            )),
                        TextColumn::make('createdBy.name')
                            ->label(__('marketplace.wallet.admin.fields.created_by'))
                            ->formatStateUsing(fn (WalletTransaction $record): string => __('marketplace.wallet.admin.fields.created_by').': '.self::creatorName($record)),
                        TextColumn::make('meta')
                            ->label(__('marketplace.wallet.admin.fields.meta'))
                            ->formatStateUsing(fn (WalletTransaction $record): string => __('marketplace.wallet.admin.fields.meta').': '.self::formatMeta($record))
                            ->wrap(),
                    ]),
                ])->collapsible(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('marketplace.wallet.admin.fields.type'))
                    ->options(fn (): array => self::typeOptions()),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canEdit(Model $record): bool
    {
        return false;
    }

    public function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * Wer die Buchung ausgeloest hat. Nur manuelle Handlungen tragen einen
     * Benutzer; alles, was aus dem Marktplatz selbst kommt, hat keinen.
     */
    private static function creatorName(WalletTransaction $transaction): string
    {
        $creator = $transaction->createdBy;

        return $creator instanceof User ? $creator->name : '—';
    }

    /**
     * Zusatzangaben einer Buchung in einer Zeile. Ohne Umbruch in JSON, damit
     * auch ein Grund mit Anfuehrungszeichen lesbar bleibt.
     */
    private static function formatMeta(WalletTransaction $transaction): string
    {
        $meta = $transaction->meta;

        if ($meta === null || $meta === []) {
            return '—';
        }

        $parts = [];

        foreach ($meta as $key => $value) {
            $parts[] = $key.': '.(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return implode(' · ', $parts);
    }

    /**
     * @return array<string, string>
     */
    private static function typeOptions(): array
    {
        $options = [];

        foreach (WalletTransactionType::cases() as $case) {
            $options[$case->value] = __('marketplace.wallet.admin.type.'.$case->value);
        }

        return $options;
    }
}
