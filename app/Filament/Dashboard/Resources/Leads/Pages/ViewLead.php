<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Resources\Leads\Pages;

use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Filament\Dashboard\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\LeadStateLog;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * FB-090: Lead-Detail als Filament-Infolist.
 *
 * Die Kontaktdaten kommen ueber `LeadResource::contactOf()` und damit ueber
 * `Lead::contactFor()` -- die Entscheidung, ob Klartext oder verdeckt, faellt
 * im LeadContactResolver und nicht hier.
 */
class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    /**
     * @return array<int, never>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('leads.contact.heading'))
                ->description(fn (Lead $record): ?string => LeadResource::contactOf($record)->masked
                    ? __('leads.contact.masked_hint')
                    : null)
                ->schema([
                    TextEntry::make('contact_name')
                        ->label(__('leads.contact.name'))
                        ->state(fn (Lead $record): string => LeadResource::contactOf($record)->fullName() ?? __('leads.contact.unknown')),
                    TextEntry::make('contact_email')
                        ->label(__('leads.contact.email'))
                        ->state(fn (Lead $record): string => LeadResource::contactOf($record)->email ?? __('leads.contact.missing')),
                    TextEntry::make('contact_phone')
                        ->label(__('leads.contact.phone'))
                        ->state(fn (Lead $record): string => LeadResource::contactOf($record)->phone ?? __('leads.contact.missing')),
                    TextEntry::make('contact_postal_code')
                        ->label(__('leads.contact.postal_code'))
                        ->state(fn (Lead $record): string => LeadResource::contactOf($record)->postalCode ?? __('leads.contact.missing')),
                ])
                ->columns(2),

            Section::make(__('leads.detail.answers'))
                ->schema([
                    RepeatableEntry::make('qualification_answers')
                        ->hiddenLabel()
                        // Ohne die reservierten Kontaktfelder: Sie stehen oben
                        // und wuerden hier an der Maskierung vorbeilaufen.
                        ->state(fn (Lead $record): array => $record->answers
                            ->reject(static fn (LeadAnswer $answer): bool => $answer->isPersonal())
                            ->map(static fn (LeadAnswer $answer): array => [
                                'field_key' => $answer->field_key,
                                'value' => is_array($answer->value)
                                    ? implode(', ', array_map(static fn (mixed $part): string => (string) $part, $answer->value))
                                    : (string) ($answer->value ?? '-'),
                            ])
                            ->values()
                            ->all())
                        ->schema([
                            TextEntry::make('field_key')->hiddenLabel()->weight('medium'),
                            TextEntry::make('value')->hiddenLabel(),
                        ])
                        ->columns(2),

                    TextEntry::make('score')->label(__('leads.list.score')),
                    TextEntry::make('result_key')->label(__('leads.detail.result'))->placeholder('-'),
                    TextEntry::make('price_at_creation')->label(__('leads.detail.price_at_creation'))->placeholder('-'),
                    TextEntry::make('duplicate_of_lead_id')
                        ->label(__('leads.detail.duplicate_of', ['id' => '']))
                        ->placeholder('-')
                        ->visible(fn (Lead $record): bool => $record->duplicate_of_lead_id !== null),
                ])
                ->columns(3),

            Section::make(__('leads.detail.origin'))
                ->schema([
                    TextEntry::make('utm_source')->label(__('leads.detail.utm_source'))->placeholder('-'),
                    TextEntry::make('utm_campaign')->label(__('leads.detail.utm_campaign'))->placeholder('-'),
                    TextEntry::make('embed_origin')->label(__('leads.detail.embed_origin'))->placeholder('-'),
                ])
                ->columns(3),

            Section::make(__('leads.detail.state_log'))
                ->schema([
                    RepeatableEntry::make('state_history')
                        ->hiddenLabel()
                        ->state(fn (Lead $record): array => $record->stateLog
                            ->map(static fn (LeadStateLog $entry): array => [
                                'when' => $entry->created_at?->format('d.m.Y H:i') ?? '',
                                'change' => ($entry->from_state instanceof LeadState ? $entry->from_state->label() : '-')
                                    .' → '.$entry->to_state->label(),
                                'reason' => $entry->reason instanceof LeadTransitionReason
                                    ? $entry->reason->label()
                                    : '',
                            ])
                            ->all())
                        ->schema([
                            TextEntry::make('when')->hiddenLabel(),
                            TextEntry::make('change')->hiddenLabel()->weight('medium'),
                            TextEntry::make('reason')->hiddenLabel()->color('gray'),
                        ])
                        ->columns(3),
                ]),
        ]);
    }
}
