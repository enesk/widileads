<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Resources\Leads\Pages;

use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Filament\Dashboard\Resources\Leads\LeadResource;
use App\Funnel\Snapshots\SnapshotLabels;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\LeadStateLog;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

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

    public function getTitle(): string
    {
        /** @var Lead $lead */
        $lead = $this->getRecord();

        return __('leads.detail.heading', ['id' => $lead->getKey()]);
    }

    public function getSubheading(): ?string
    {
        /** @var Lead $lead */
        $lead = $this->getRecord();

        return __('leads.detail.subheading', [
            'funnel' => $lead->funnel?->name ?? __('leads.detail.unknown_funnel'),
            'date' => $lead->created_at?->format('d.m.Y H:i') ?? '-',
        ]);
    }

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
            // Zuerst der Kontakt: Deshalb oeffnet jemand einen Lead.
            Section::make(__('leads.contact.heading'))
                ->icon(Heroicon::OutlinedUserCircle)
                ->description(fn (Lead $record): ?string => LeadResource::contactOf($record)->masked
                    ? __('leads.contact.masked_hint')
                    : null)
                ->schema([
                    TextEntry::make('contact_name')
                        ->label(__('leads.contact.name'))
                        ->size(TextSize::Large)
                        ->weight('bold')
                        ->state(fn (Lead $record): string => LeadResource::contactOf($record)->fullName() ?? __('leads.contact.unknown')),
                    TextEntry::make('contact_email')
                        ->label(__('leads.contact.email'))
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->copyable()
                        ->state(fn (Lead $record): string => LeadResource::contactOf($record)->email ?? __('leads.contact.missing')),
                    TextEntry::make('contact_phone')
                        ->label(__('leads.contact.phone'))
                        ->icon(Heroicon::OutlinedPhone)
                        ->copyable()
                        ->state(fn (Lead $record): string => LeadResource::contactOf($record)->phone ?? __('leads.contact.missing')),
                    TextEntry::make('contact_postal_code')
                        ->label(__('leads.contact.postal_code'))
                        ->icon(Heroicon::OutlinedMapPin)
                        ->state(fn (Lead $record): string => LeadResource::contactOf($record)->postalCode ?? __('leads.contact.missing')),
                ])
                ->columns(2),

            Section::make(__('leads.detail.overview'))
                ->icon(Heroicon::OutlinedChartBar)
                ->schema([
                    TextEntry::make('lead_state')
                        ->label(__('leads.list.state'))
                        ->badge()
                        ->formatStateUsing(fn (LeadState $state): string => $state->label()),
                    TextEntry::make('score')
                        ->label(__('leads.list.score'))
                        ->badge()
                        ->color('info'),
                    TextEntry::make('result_key')
                        ->label(__('leads.detail.result'))
                        ->placeholder('-'),
                    TextEntry::make('funnel.name')
                        ->label(__('leads.list.funnel'))
                        ->placeholder('-'),
                    TextEntry::make('created_at')
                        ->label(__('leads.list.received_at'))
                        ->dateTime('d.m.Y H:i'),
                    TextEntry::make('price_at_creation')
                        ->label(__('leads.detail.price_at_creation'))
                        ->placeholder('-'),
                    TextEntry::make('duplicate_of_lead_id')
                        ->label(__('leads.detail.duplicate_label'))
                        ->badge()
                        ->color('warning')
                        ->formatStateUsing(fn (mixed $state): string => __('leads.detail.duplicate_of', ['id' => $state]))
                        ->visible(fn (Lead $record): bool => $record->duplicate_of_lead_id !== null)
                        ->columnSpanFull(),
                ])
                ->columns(3),

            Section::make(__('leads.detail.answers'))
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->description(__('leads.detail.answers_hint'))
                ->schema([
                    KeyValueEntry::make('qualification_answers')
                        ->hiddenLabel()
                        ->keyLabel(__('leads.detail.question'))
                        ->valueLabel(__('leads.detail.answer'))
                        // Ohne die reservierten Kontaktfelder: Sie stehen oben
                        // und wuerden hier an der Maskierung vorbeilaufen.
                        ->state(function (Lead $record): array {
                            $snapshot = $record->funnelVersion?->snapshot;
                            $labels = SnapshotLabels::questions(is_array($snapshot) ? $snapshot : null);
                            $options = SnapshotLabels::options(is_array($snapshot) ? $snapshot : null);

                            return $record->answers
                                ->reject(static fn (LeadAnswer $answer): bool => $answer->isPersonal())
                                ->mapWithKeys(static function (LeadAnswer $answer) use ($labels, $options): array {
                                    // Der Kunde hat eine Frage gelesen und eine
                                    // Option angeklickt -- beides soll hier
                                    // stehen, nicht der technische Schluessel.
                                    $readable = static fn (mixed $single): string => $options[$answer->field_key][(string) $single]
                                        ?? (string) $single;

                                    $value = $answer->value;

                                    return [
                                        $labels[$answer->field_key] ?? $answer->field_key => match (true) {
                                            is_array($value) => implode(', ', array_map($readable, $value)),
                                            is_bool($value) => $value ? __('leads.detail.yes') : __('leads.detail.no'),
                                            is_scalar($value) => $readable($value),
                                            default => '-',
                                        },
                                    ];
                                })
                                ->all();
                        }),
                ]),

            Section::make(__('leads.detail.origin'))
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->collapsed()
                ->schema([
                    TextEntry::make('utm_source')->label(__('leads.detail.utm_source'))->placeholder('-'),
                    TextEntry::make('utm_campaign')->label(__('leads.detail.utm_campaign'))->placeholder('-'),
                    TextEntry::make('embed_origin')->label(__('leads.detail.embed_origin'))->placeholder('-'),
                ])
                ->columns(3),

            Section::make(__('leads.detail.state_log'))
                ->icon(Heroicon::OutlinedClock)
                ->collapsed()
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
                            TextEntry::make('when')->hiddenLabel()->color('gray'),
                            TextEntry::make('change')->hiddenLabel()->weight('medium'),
                            TextEntry::make('reason')->hiddenLabel()->color('gray'),
                        ])
                        ->columns(3),
                ]),
        ]);
    }
}
