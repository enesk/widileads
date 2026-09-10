<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Constants\AuditAction;
use App\Constants\BuyerLeadFeedback;
use App\Constants\CallAttemptOutcome;
use App\Constants\FunnelFieldKey;
use App\Constants\LeadContactStatus;
use App\Constants\LeadState;
use App\Exceptions\ComplaintNotAllowedException;
use App\Funnel\Snapshots\SnapshotLabels;
use App\Models\LeadPurchase;
use App\Models\Scopes\TenantScopes;
use App\Models\Tenant;
use App\Models\User;
use App\Presenters\LeadPresenter;
use App\Services\AuditLogger;
use App\Services\LeadComplaintService;
use App\Services\TenantTypeService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Meine Leads" -- die gekauften Leads eines Kaeufers (FB-057, seit FB-090 als
 * Filament-Tabelle).
 *
 * Kontaktdaten kommen wie ueberall ausschliesslich ueber den LeadPresenter;
 * dass sie hier im Klartext stehen, entscheidet nicht diese Seite, sondern der
 * LeadContactResolver anhand des Kaufbelegs (FB-032, FB-054).
 *
 * Gezeigt werden ausschliesslich die Kaeufe des aktiven Mandanten -- der
 * Kaufbeleg gehoert dem Kaeufer, waehrend der Lead dem Betreiber gehoert.
 * Deshalb wird hier ausdruecklich auf `buyer_tenant_id` eingeschraenkt (Scope
 * `ofBuyer`) und nicht auf einen Mandanten-Scope vertraut, der auf die falsche
 * Spalte zielte. Aus demselben Grund bleibt das eine Page mit Tabelle und wird
 * keine Resource: Deren automatische Mandantenbindung liefe ueber `tenant_id`.
 */
class PurchasedLeads extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.dashboard.pages.purchased-leads';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = -9;

    public function getHeading(): string|Htmlable
    {
        return __('marketplace.purchased.heading');
    }

    public function getTitle(): string|Htmlable
    {
        return __('marketplace.purchased.heading');
    }

    public static function getNavigationLabel(): string
    {
        return __('marketplace.purchased.nav_label');
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        return app(TenantTypeService::class)->canAccessMarketplace($tenant);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->purchasesQuery())
            ->defaultSort('purchased_at', 'desc')
            ->paginated([(int) config('funnel.marketplace.listing.per_page')])
            ->description(__('marketplace.purchased.description'))
            ->emptyStateHeading(__('marketplace.purchased.empty'))
            // Karten statt einer elfspaltigen Tabelle: Auf dem Telefon
            // schiebt eine solche Tabelle den halben Inhalt aus dem Bild.
            // Mobil eine Karte je Kauf, ab mittleren Bildschirmen zwei, auf
            // grossen drei.
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->columns([
                Panel::make([
                    Stack::make([
                        // Kopf der Karte: Wer, und seit wann gehoert er dir.
                        TextColumn::make('contact_name')
                            ->label(__('leads.contact.name'))
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large)
                            // Kontaktdaten ausschliesslich ueber den Presenter.
                            ->state(fn (LeadPurchase $record): string => $this->presenter($record)->name()),
                        TextColumn::make('purchased_at')
                            ->label(__('marketplace.purchased.csv.purchased_at'))
                            ->icon(Heroicon::OutlinedClock)
                            ->color('gray')
                            ->size(TextSize::Small)
                            ->dateTime('d.m.Y H:i')
                            ->sortable(),

                        // Zustaende als Abzeichen nebeneinander.
                        Split::make([
                            TextColumn::make('contact_status')
                                ->label(__('call.panel.list.contact_status'))
                                ->badge()
                                // Der Stand der Erreichbarkeit, nicht der
                                // Verkaufsstand: beide Achsen bewegen sich
                                // unabhaengig voneinander.
                                ->state(fn (LeadPurchase $record): string => $this->contactStatusLabel($record))
                                ->color(fn (LeadPurchase $record): string => $this->contactStatusColor($record)),
                            TextColumn::make('buyer_feedback')
                                ->label(__('marketplace.purchased.csv.feedback'))
                                ->badge()
                                ->formatStateUsing(fn (BuyerLeadFeedback $state): string => $state->label())
                                ->placeholder(''),
                        ])->from('sm'),

                        TextColumn::make('failed_attempts_count')
                            ->label(__('call.panel.list.attempts'))
                            ->color('gray')
                            ->size(TextSize::Small)
                            ->state(fn (LeadPurchase $record): string => __('call.panel.counter', [
                                'count' => (int) ($record->failed_attempts_count ?? 0),
                                'required' => (int) config('lead_calls.unreachable_attempts'),
                            ])),

                        // Kontaktdaten: der Grund, warum man diese Seite oeffnet.
                        TextColumn::make('contact_phone')
                            ->label(__('marketplace.listing.phone'))
                            ->icon(Heroicon::OutlinedPhone)
                            ->state(fn (LeadPurchase $record): string => $this->presenter($record)->phone()),
                        TextColumn::make('contact_email')
                            ->label(__('marketplace.listing.email'))
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->state(fn (LeadPurchase $record): string => $this->presenter($record)->email()),
                        TextColumn::make('contact_postal_code')
                            ->label(__('marketplace.listing.region'))
                            ->icon(Heroicon::OutlinedMapPin)
                            ->color('gray')
                            ->size(TextSize::Small)
                            ->state(fn (LeadPurchase $record): string => $this->presenter($record)->postalCode()),

                        TextColumn::make('lead.funnel.name')
                            ->label(__('leads.list.funnel'))
                            ->color('gray')
                            ->size(TextSize::Small)
                            ->placeholder(__('marketplace.listing.unknown_funnel')),

                        TextColumn::make('complaint.status')
                            ->label(__('marketplace.complaint.fields.status'))
                            ->badge()
                            ->color('warning')
                            ->state(fn (LeadPurchase $record): ?string => $record->complaint === null
                                ? null
                                : __('marketplace.complaint.filed', [
                                    'state' => $record->complaint->requested_state->label(),
                                    'status' => $record->complaint->status->label(),
                                ]))
                            ->placeholder(''),

                        TextColumn::make('qualification')
                            ->label(__('leads.detail.answers'))
                            ->badge()
                            ->color('gray')
                            ->state(fn (LeadPurchase $record): array => $this->qualificationAnswers($record))
                            ->listWithLineBreaks()
                            ->limitList(3)
                            ->expandableLimitedList(),
                    ])->space(2),
                ]),
            ])
            ->filters([
                Filter::make('without_feedback')
                    ->label(__('marketplace.purchased.only_without_feedback'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNull('buyer_feedback')),
            ])
            ->headerActions([
                Action::make('exportCsv')
                    ->label(__('marketplace.purchased.export'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (): StreamedResponse => $this->exportCsv()),
            ])
            ->recordUrl(fn (LeadPurchase $record): string => PurchasedLeadDetail::getUrl(['purchase' => $record->getKey()]))
            ->recordActions([
                Action::make('open')
                    ->label(__('marketplace.purchased.detail.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->link()
                    ->url(fn (LeadPurchase $record): string => PurchasedLeadDetail::getUrl(['purchase' => $record->getKey()])),
                Action::make('feedback')
                    ->label(__('marketplace.purchased.csv.feedback'))
                    ->icon(Heroicon::OutlinedHandThumbUp)
                    ->link()
                    ->schema([
                        Select::make('buyer_feedback')
                            ->label(__('marketplace.purchased.csv.feedback'))
                            ->options(BuyerLeadFeedback::options())
                            ->required(),
                    ])
                    ->fillForm(fn (LeadPurchase $record): array => [
                        'buyer_feedback' => $record->buyer_feedback?->value,
                    ])
                    ->action(fn (LeadPurchase $record, array $data) => $this->setFeedback($record, (string) $data['buyer_feedback'])),
                Action::make('complaint')
                    ->label(__('marketplace.complaint.open'))
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->link()
                    ->color('warning')
                    ->modalDescription(__('marketplace.complaint.help'))
                    ->visible(fn (LeadPurchase $record): bool => $this->canComplain($record))
                    ->schema([
                        Select::make('requested_state')
                            ->label(__('marketplace.complaint.fields.requested_state'))
                            ->options([
                                LeadState::UNERREICHBAR->value => LeadState::UNERREICHBAR->label(),
                                LeadState::UNGUELTIG->value => LeadState::UNGUELTIG->label(),
                            ])
                            ->default(LeadState::UNERREICHBAR->value)
                            ->required(),
                        Textarea::make('reason')
                            ->label(__('marketplace.complaint.fields.reason'))
                            ->placeholder(__('marketplace.complaint.reason_placeholder'))
                            ->required(),
                    ])
                    ->modalSubmitActionLabel(__('marketplace.complaint.submit'))
                    ->action(fn (LeadPurchase $record, array $data) => $this->fileComplaint(
                        $record,
                        (string) $data['requested_state'],
                        (string) $data['reason'],
                    )),
            ]);
    }

    /**
     * Gibt die eigenen Kaeufe als CSV aus.
     *
     * Der Export enthaelt Kontaktdaten im Klartext und wird deshalb im
     * Audit-Log festgehalten (FB-005, AuditAction::DATA_EXPORTED). Wer Daten
     * aus dem System traegt, hinterlaesst eine Spur -- das ist der Sinn des
     * Protokolls und keine Zusatzfunktion.
     */
    public function exportCsv(): StreamedResponse
    {
        $tenant = $this->tenant();
        $viewer = $this->viewer();
        $purchases = $this->purchasesQuery()->get();

        app(AuditLogger::class)->log(
            AuditAction::DATA_EXPORTED,
            null,
            ['subject' => 'purchased_leads', 'count' => $purchases->count()],
            $tenant,
        );

        $rows = $purchases->map(function (LeadPurchase $purchase) use ($viewer): array {
            $presenter = new LeadPresenter($purchase->lead, $viewer);

            return [
                $purchase->purchased_at?->toDateTimeString(),
                $purchase->lead->funnel?->name,
                $presenter->name(),
                $presenter->email(),
                $presenter->phone(),
                $presenter->postalCode(),
                (string) $purchase->lead->score,
                $purchase->buyer_feedback?->value,
            ];
        })->all();

        $headers = [
            __('marketplace.purchased.csv.purchased_at'),
            __('marketplace.purchased.csv.funnel'),
            __('marketplace.purchased.csv.name'),
            __('marketplace.purchased.csv.email'),
            __('marketplace.purchased.csv.phone'),
            __('marketplace.purchased.csv.postal_code'),
            __('marketplace.purchased.csv.score'),
            __('marketplace.purchased.csv.feedback'),
        ];

        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            // Byte Order Mark, damit Excel die Umlaute richtig liest.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, 'meine-leads.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function setFeedback(LeadPurchase $purchase, string $feedback): void
    {
        $value = BuyerLeadFeedback::tryFrom($feedback);

        if ($value === null) {
            return;
        }

        $purchase->update([
            'buyer_feedback' => $value,
            'buyer_feedback_at' => now(),
        ]);
    }

    /**
     * Reklamiert einen Kauf (FB-058).
     *
     * Die Seite entscheidet nichts: Der Antrag geht in die Pruefliste, ein
     * Mensch sieht ihn an. Was hier abgefangen wird, sind die alltaeglichen
     * Ausgaenge -- Frist abgelaufen, schon reklamiert, Lead nicht mehr
     * verkauft. Alles Hinweise, keine Fehler.
     */
    private function fileComplaint(LeadPurchase $purchase, string $requestedState, string $reason): void
    {
        $state = LeadState::tryFrom($requestedState);

        try {
            $complaint = app(LeadComplaintService::class)->file(
                $purchase,
                $this->tenant(),
                $state ?? LeadState::UNERREICHBAR,
                $reason,
            );
        } catch (ComplaintNotAllowedException $exception) {
            Notification::make()
                ->warning()
                ->title($exception->getMessage())
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title(__('marketplace.complaint.filed', [
                'state' => $complaint->requested_state->label(),
                'status' => $complaint->status->label(),
            ]))
            ->send();
    }

    /**
     * Kann dieser Kauf noch reklamiert werden?
     *
     * Nur eine Vorschau fuer die Oberflaeche -- verbindlich prueft der
     * LeadComplaintService in dem Moment, in dem der Antrag kommt.
     */
    private function canComplain(LeadPurchase $purchase): bool
    {
        if ($purchase->complaint !== null) {
            return false;
        }

        if ($purchase->lead?->lead_state !== LeadState::VERKAUFT) {
            return false;
        }

        $deadline = $purchase->purchased_at?->copy()->addDays((int) config('funnel.call.deadline_days'));

        return $deadline === null || $deadline->isFuture();
    }

    /**
     * @return Builder<LeadPurchase>
     */
    private function purchasesQuery(): Builder
    {
        return LeadPurchase::query()
            ->with([
                'complaint',
                'lead.answers',
                // Ohne Mandanten-Scope: Der Fragebogen gehoert dem Betreiber,
                // nicht dem Kaeufer. Mit Scope kaeme hier immer null heraus,
                // und der Kaeufer saehe seine eigenen Kaeufe ohne Herkunft.
                'lead.funnel' => static fn (Relation $funnel) => $funnel->withoutGlobalScopes(TenantScopes::names()),
                // Die Fassung liefert die Beschriftungen der Fragen und
                // Antwortoptionen; auch sie gehoert dem Betreiber.
                'lead.funnelVersion' => static fn (Relation $version) => $version->withoutGlobalScopes(TenantScopes::names()),
            ])
            ->withCount([
                // Nur die gueltigen Fehlversuche -- genau die Menge, die das
                // Regelwerk zaehlt (FB-083).
                'callAttempts as failed_attempts_count' => static fn (Builder $attempts): Builder => $attempts
                    ->where('outcome', CallAttemptOutcome::FAILED_VALID),
            ])
            ->ofBuyer($this->tenant());
    }

    /**
     * Beschriftung und Farbe des Erreichbarkeits-Abzeichens -- dieselben wie im
     * LeadCallPanel auf der Detailseite.
     */
    private function contactStatusLabel(LeadPurchase $purchase): string
    {
        return match ($purchase->lead?->contact_status) {
            LeadContactStatus::BILLABLE => __('call.panel.badge.billable'),
            LeadContactStatus::UNREACHABLE => __('call.panel.badge.unreachable'),
            default => __('call.panel.badge.open'),
        };
    }

    private function contactStatusColor(LeadPurchase $purchase): string
    {
        return match ($purchase->lead?->contact_status) {
            LeadContactStatus::BILLABLE => 'success',
            LeadContactStatus::UNREACHABLE => 'danger',
            default => 'gray',
        };
    }

    /**
     * Die Qualifizierungsantworten -- ohne die reservierten Kontaktfelder.
     *
     * Der Kaeufer duerfte sie hier zwar sehen, aber die Kontaktdaten stehen
     * ohnehin schon aus dem Presenter daneben. Sie ein zweites Mal aus den
     * Rohantworten zu holen, waere ein Weg an der einen Stelle vorbei, an der
     * ueber ihre Sichtbarkeit entschieden wird.
     *
     * @return array<int, string>
     */
    private function qualificationAnswers(LeadPurchase $purchase): array
    {
        // Beschriftungen aus der Fassung, unter der der Lead entstanden ist --
        // wie im Marktplatz und auf der Detailseite. Ein Kaeufer soll lesen,
        // was der Kunde angeklickt hat, nicht "rasse_groesse: gross".
        $snapshot = $purchase->lead->funnelVersion?->snapshot;
        $labels = SnapshotLabels::questions(is_array($snapshot) ? $snapshot : null);
        $options = SnapshotLabels::options(is_array($snapshot) ? $snapshot : null);

        $answers = [];

        foreach ($purchase->lead->answers as $answer) {
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

    private function presenter(LeadPurchase $purchase): LeadPresenter
    {
        return new LeadPresenter($purchase->lead, $this->viewer());
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
