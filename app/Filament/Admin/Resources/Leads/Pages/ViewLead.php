<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Leads\Pages;

use App\Constants\CallAttemptOutcome;
use App\Constants\CallAttemptStatus;
use App\Constants\LeadContactStatus;
use App\Constants\LeadResolutionReason;
use App\Constants\LeadState;
use App\Filament\Admin\Resources\Leads\LeadResource;
use App\Models\CallAttempt;
use App\Models\Lead;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * FB-085: Der Versuchsverlauf eines Leads im Admin.
 *
 * Die Beweisfuehrung im Streitfall: Wer hat wann angerufen, was hat Twilio
 * gemeldet, wie wurde der Versuch bewertet -- und warum zaehlt er
 * gegebenenfalls nicht. Die Rohmeldung steht aufklappbar daneben; sie enthaelt
 * Rufnummern und ist deshalb nur hier zu sehen, im Admin-Panel, zu dem nur
 * `is_admin` Zutritt hat.
 *
 * Reine Ansicht. Weder Versuche noch `contact_status` lassen sich von hier
 * aendern -- entschieden wird ausschliesslich im LeadResolver (FB-083).
 */
class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    public function getTitle(): string
    {
        /** @var Lead $lead */
        $lead = $this->getRecord();

        return __('call.reachability.detail.heading', ['id' => $lead->getKey()]);
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
            Section::make(__('call.reachability.detail.state'))
                ->icon(Heroicon::OutlinedPhoneArrowUpRight)
                ->schema([
                    TextEntry::make('lead_state')
                        ->label(__('funnel.lead.fields.lead_state'))
                        ->badge()
                        ->formatStateUsing(fn (LeadState $state): string => $state->label()),
                    TextEntry::make('contact_status')
                        ->label(__('call.reachability.detail.contact_status'))
                        ->badge()
                        ->color(fn (LeadContactStatus $state): string => match ($state) {
                            LeadContactStatus::BILLABLE => 'success',
                            LeadContactStatus::UNREACHABLE => 'danger',
                            LeadContactStatus::OPEN => 'gray',
                        })
                        ->formatStateUsing(fn (LeadContactStatus $state): string => $state->label()),
                    TextEntry::make('resolved_by')
                        ->label(__('call.reachability.detail.resolved_by'))
                        ->placeholder('-')
                        ->formatStateUsing(fn (LeadResolutionReason $state): string => $state->label()),
                    TextEntry::make('delivered_at')
                        ->label(__('call.reachability.detail.delivered_at'))
                        ->dateTime('d.m.Y H:i')
                        ->placeholder('-'),
                    TextEntry::make('deadline_at')
                        ->label(__('call.reachability.detail.deadline_at'))
                        ->dateTime('d.m.Y H:i')
                        ->placeholder('-'),
                    TextEntry::make('resolved_at')
                        ->label(__('call.reachability.detail.resolved_at'))
                        ->dateTime('d.m.Y H:i')
                        ->placeholder('-'),
                ])
                ->columns(3),

            Section::make(__('call.reachability.detail.attempts'))
                ->icon(Heroicon::OutlinedClock)
                ->description(__('call.reachability.detail.attempts_hint'))
                ->schema([
                    RepeatableEntry::make('attempt_log')
                        ->hiddenLabel()
                        ->placeholder(__('call.reachability.detail.no_attempts'))
                        ->state(fn (Lead $record): array => $record->callAttempts
                            ->map(static fn (CallAttempt $attempt): array => [
                                'when' => $attempt->started_at?->format('d.m.Y H:i:s')
                                    ?? $attempt->created_at?->format('d.m.Y H:i:s')
                                    ?? '-',
                                'buyer' => $attempt->purchase?->buyer->name ?? '-',
                                'status' => $attempt->status instanceof CallAttemptStatus
                                    ? $attempt->status->label()
                                    : '-',
                                'dial_status' => $attempt->dial_status ?? '-',
                                'answered_by' => $attempt->answered_by ?? '-',
                                'duration' => $attempt->duration_seconds === null
                                    ? '-'
                                    : __('call.reachability.detail.seconds', ['seconds' => $attempt->duration_seconds]),
                                'outcome' => $attempt->outcome instanceof CallAttemptOutcome
                                    ? $attempt->outcome->label()
                                    : '-',
                                'ignore_reason' => $attempt->ignore_reason ?? '-',
                                'call_sid' => $attempt->provider_call_sid ?? '-',
                                'dial_sid' => $attempt->provider_dial_sid ?? '-',
                                'raw_payload' => self::formatPayload($attempt),
                            ])
                            ->all())
                        ->schema([
                            TextEntry::make('when')
                                ->label(__('call.reachability.detail.when'))
                                ->weight('medium'),
                            TextEntry::make('buyer')
                                ->label(__('call.reachability.detail.buyer')),
                            TextEntry::make('status')
                                ->label(__('call.reachability.detail.status')),
                            TextEntry::make('dial_status')
                                ->label(__('call.reachability.detail.dial_status')),
                            TextEntry::make('answered_by')
                                ->label(__('call.reachability.detail.answered_by')),
                            TextEntry::make('duration')
                                ->label(__('call.reachability.detail.duration')),
                            TextEntry::make('outcome')
                                ->label(__('call.reachability.detail.outcome')),
                            TextEntry::make('ignore_reason')
                                ->label(__('call.reachability.detail.ignore_reason')),
                            TextEntry::make('call_sid')
                                ->label(__('call.reachability.detail.call_sid'))
                                ->fontFamily(FontFamily::Mono)
                                ->copyable(),
                            TextEntry::make('dial_sid')
                                ->label(__('call.reachability.detail.dial_sid'))
                                ->fontFamily(FontFamily::Mono)
                                ->copyable(),

                            // Die Rohmeldung ist lang und wird selten
                            // gebraucht -- deshalb zugeklappt.
                            Section::make(__('call.reachability.detail.raw_payload'))
                                ->collapsed()
                                ->columnSpanFull()
                                ->schema([
                                    TextEntry::make('raw_payload')
                                        ->hiddenLabel()
                                        ->html()
                                        ->formatStateUsing(fn (string $state): HtmlString => new HtmlString(
                                            '<pre class="overflow-x-auto whitespace-pre-wrap text-xs">'
                                            .e($state)
                                            .'</pre>',
                                        )),
                                ]),
                        ])
                        ->columns(4),
                ]),
        ]);
    }

    /**
     * Die Rohmeldung von Twilio als lesbares JSON.
     *
     * Unveraendert bis auf die Formatierung -- was hier steht, ist der Beleg.
     */
    private static function formatPayload(CallAttempt $attempt): string
    {
        $payload = $attempt->provider_payload;

        if (! is_array($payload) || $payload === []) {
            return __('call.reachability.detail.no_payload');
        }

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ?: __('call.reachability.detail.no_payload');
    }
}
