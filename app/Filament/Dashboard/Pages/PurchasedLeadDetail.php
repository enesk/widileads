<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Constants\BuyerLeadFeedback;
use App\Constants\LeadState;
use App\Exceptions\ComplaintNotAllowedException;
use App\Funnel\Snapshots\SnapshotLabels;
use App\Models\LeadAnswer;
use App\Models\LeadPurchase;
use App\Models\Scopes\TenantScopes;
use App\Models\Tenant;
use App\Models\User;
use App\Presenters\LeadPresenter;
use App\Services\LeadComplaintService;
use App\Services\TenantTypeService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Ein gekaufter Lead in voller Breite -- die Seite, auf der ein Kaeufer nach
 * dem Kauf landet.
 *
 * Wie "Meine Leads" haengt sie am KAUFBELEG und nicht am Lead: Der Beleg
 * gehoert dem Kaeufer, der Lead dem Betreiber. Deshalb wird ausdruecklich auf
 * `buyer_tenant_id` eingeschraenkt (Scope `ofBuyer`) statt auf einen
 * Mandanten-Scope zu vertrauen, der auf `tenant_id` zielt -- genau daran
 * scheiterte der Aufruf eines gekauften Leads bisher mit 404.
 *
 * Kontaktdaten kommen ausschliesslich ueber den LeadPresenter. Dass sie hier im
 * Klartext stehen, entscheidet der LeadContactResolver anhand des Kaufbelegs
 * (FB-032, FB-054), nicht diese Ansicht.
 */
class PurchasedLeadDetail extends Page
{
    protected string $view = 'filament.dashboard.pages.purchased-lead-detail';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'purchased-leads/{purchase}';

    public string $purchaseId = '';

    /**
     * Der Beleg wird je Anfrage frisch geladen und nur im Speicher gehalten.
     *
     * Ein Eloquent-Modell als oeffentliche Livewire-Eigenschaft muesste
     * zwischen den Anfragen wiederhergestellt werden -- und zwar ohne den
     * Kaeufer-Filter, der hier die einzige Zugangspruefung ist. Der Schluessel
     * allein reist deshalb, geprueft wird bei jedem Zugriff neu.
     */
    private ?LeadPurchase $loaded = null;

    public function mount(string $purchase): void
    {
        $this->purchaseId = $purchase;

        // Laedt und prueft -- ein fremder Beleg endet hier mit 404.
        $this->purchase();
    }

    public function purchase(): LeadPurchase
    {
        return $this->loaded ??= $this->findPurchase($this->purchaseId);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->presenter()->name();
    }

    public function getHeading(): string|Htmlable
    {
        return $this->presenter()->name();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('marketplace.purchased.detail.subheading', [
            'funnel' => $this->purchase()->lead->funnel?->name ?? __('marketplace.listing.unknown_funnel'),
            'date' => $this->purchase()->purchased_at?->format('d.m.Y H:i') ?? '-',
        ]);
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        return app(TenantTypeService::class)->canAccessMarketplace($tenant);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('marketplace.purchased.detail.back'))
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => PurchasedLeads::getUrl()),
            Action::make('feedback')
                ->label(__('marketplace.purchased.csv.feedback'))
                ->icon(Heroicon::OutlinedHandThumbUp)
                ->schema([
                    Select::make('buyer_feedback')
                        ->label(__('marketplace.purchased.csv.feedback'))
                        ->options(BuyerLeadFeedback::options())
                        ->required(),
                ])
                ->fillForm(fn (): array => ['buyer_feedback' => $this->purchase()->buyer_feedback?->value])
                ->action(fn (array $data) => $this->setFeedback((string) $data['buyer_feedback'])),
            Action::make('complaint')
                ->label(__('marketplace.complaint.open'))
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning')
                ->modalDescription(__('marketplace.complaint.help'))
                ->visible(fn (): bool => $this->canComplain())
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
                ->action(fn (array $data) => $this->fileComplaint(
                    (string) $data['requested_state'],
                    (string) $data['reason'],
                )),
        ];
    }

    /**
     * Die Ansicht selbst -- dieselben Bausteine wie die Lead-Detailseite des
     * Betreibers (ViewLead), damit ein Lead auf beiden Seiten gleich aussieht.
     */
    public function leadInfolist(Schema $schema): Schema
    {
        $presenter = $this->presenter();
        $purchase = $this->purchase();
        $lead = $purchase->lead;

        return $schema
            ->record($lead)
            ->components([
                // Zuerst der Kontakt: Deshalb oeffnet jemand einen gekauften Lead.
                Section::make(__('leads.contact.heading'))
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->schema([
                        TextEntry::make('contact_name')
                            ->label(__('leads.contact.name'))
                            ->size(TextSize::Large)
                            ->weight('bold')
                            ->state(fn (): string => $presenter->name()),
                        TextEntry::make('contact_email')
                            ->label(__('leads.contact.email'))
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->copyable()
                            ->state(fn (): string => $presenter->email()),
                        TextEntry::make('contact_phone')
                            ->label(__('leads.contact.phone'))
                            ->icon(Heroicon::OutlinedPhone)
                            ->copyable(fn (): bool => ! $presenter->isPhoneMasked())
                            ->helperText(fn (): ?string => $presenter->phoneHint())
                            ->state(fn (): string => $presenter->phone()),
                        TextEntry::make('contact_postal_code')
                            ->label(__('leads.contact.postal_code'))
                            ->icon(Heroicon::OutlinedMapPin)
                            ->state(fn (): string => $presenter->postalCode()),
                    ])
                    ->columns(2),

                Section::make(__('marketplace.purchased.detail.facts'))
                    ->icon(Heroicon::OutlinedShoppingBag)
                    ->schema([
                        TextEntry::make('purchased_at')
                            ->label(__('marketplace.purchased.csv.purchased_at'))
                            ->state(fn (): string => $purchase->purchased_at?->format('d.m.Y H:i') ?? '-'),
                        // Was der Lead gekostet hat, und was mit dem Geld
                        // gerade ist: reserviert, abgebucht, freigegeben oder
                        // erstattet. Der Preis steht am Beleg und aendert sich
                        // nicht, auch wenn der Verkaeufer ihn spaeter anhebt.
                        TextEntry::make('price')
                            ->label(__('marketplace.purchased.detail.price'))
                            ->state(fn (): string => Money::format($purchase->price_cents, $purchase->currency))
                            ->helperText(fn (): string => __('marketplace.purchased.detail.price_status.'.$purchase->status->value)),
                        TextEntry::make('received_at')
                            ->label(__('leads.list.received_at'))
                            ->state(fn (): string => $lead->created_at?->format('d.m.Y H:i') ?? '-'),
                        TextEntry::make('funnel_name')
                            ->label(__('leads.list.funnel'))
                            ->state(fn (): string => $lead->funnel?->name ?? __('marketplace.listing.unknown_funnel')),
                        TextEntry::make('buyer_feedback')
                            ->label(__('marketplace.purchased.csv.feedback'))
                            ->badge()
                            ->placeholder('-')
                            ->state(fn (): ?string => $purchase->buyer_feedback?->label()),
                        TextEntry::make('complaint_status')
                            ->label(__('marketplace.complaint.fields.status'))
                            ->badge()
                            ->color('warning')
                            ->visible(fn (): bool => $purchase->complaint !== null)
                            ->state(fn (): ?string => $purchase->complaint === null ? null : __('marketplace.complaint.filed', [
                                'state' => $purchase->complaint->requested_state->label(),
                                'status' => $purchase->complaint->status->label(),
                            ]))
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
                            ->state(fn (): array => $this->answers()),
                    ]),
            ]);
    }

    /**
     * Die Antworten aus dem Fragebogen, mit den Beschriftungen der Fassung,
     * unter der der Lead entstanden ist -- nicht mit den technischen
     * Feldschluesseln.
     *
     * Ohne die reservierten Kontaktfelder: Sie stehen schon oben, und zwar aus
     * dem Presenter. Sie hier ein zweites Mal aus den Rohantworten zu holen,
     * waere ein Weg an der einen Stelle vorbei, an der ueber ihre Sichtbarkeit
     * entschieden wird.
     *
     * @return array<string, string>
     */
    public function answers(): array
    {
        $lead = $this->purchase()->lead;
        $snapshot = $lead->funnelVersion?->snapshot;
        $labels = SnapshotLabels::questions(is_array($snapshot) ? $snapshot : null);
        $options = SnapshotLabels::options(is_array($snapshot) ? $snapshot : null);

        return $lead->answers
            ->reject(static fn (LeadAnswer $answer): bool => $answer->isPersonal())
            ->mapWithKeys(static function (LeadAnswer $answer) use ($labels, $options): array {
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
    }

    public function presenter(): LeadPresenter
    {
        return new LeadPresenter($this->purchase()->lead, $this->viewer());
    }

    /**
     * Nur die eigenen Kaeufe. Ein fremder Beleg ist hier nicht "verboten",
     * sondern schlicht nicht vorhanden -- sonst liesse sich an der Antwort
     * ablesen, welche Kaufbelege es gibt.
     */
    private function findPurchase(string $key): LeadPurchase
    {
        $purchase = LeadPurchase::query()
            ->with([
                'complaint',
                'lead.answers',
                // Ohne Mandanten-Scope: Fragebogen und Fassung gehoeren dem
                // Betreiber, nicht dem Kaeufer. Mit Scope kaeme null heraus,
                // und der Kaeufer saehe seinen Lead ohne Herkunft.
                'lead.funnel' => static fn (Relation $funnel) => $funnel->withoutGlobalScopes(TenantScopes::names()),
                'lead.funnelVersion' => static fn (Relation $version) => $version->withoutGlobalScopes(TenantScopes::names()),
            ])
            ->ofBuyer($this->tenant())
            ->whereKey($key)
            ->first();

        abort_unless($purchase instanceof LeadPurchase, 404);

        return $purchase;
    }

    private function setFeedback(string $feedback): void
    {
        $value = BuyerLeadFeedback::tryFrom($feedback);

        if ($value === null) {
            return;
        }

        $this->purchase()->update([
            'buyer_feedback' => $value,
            'buyer_feedback_at' => now(),
        ]);

        Notification::make()
            ->success()
            ->title(__('marketplace.purchased.detail.feedback_saved'))
            ->send();
    }

    /**
     * Reklamiert den Kauf (FB-058). Die Seite entscheidet nichts, der Antrag
     * geht in die Pruefliste.
     */
    private function fileComplaint(string $requestedState, string $reason): void
    {
        $state = LeadState::tryFrom($requestedState);

        try {
            $complaint = app(LeadComplaintService::class)->file(
                $this->purchase(),
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

        $this->purchase()->refresh();

        Notification::make()
            ->success()
            ->title(__('marketplace.complaint.filed', [
                'state' => $complaint->requested_state->label(),
                'status' => $complaint->status->label(),
            ]))
            ->send();
    }

    /**
     * Nur eine Vorschau fuer die Oberflaeche -- verbindlich prueft der
     * LeadComplaintService in dem Moment, in dem der Antrag kommt.
     */
    private function canComplain(): bool
    {
        if ($this->purchase()->complaint !== null) {
            return false;
        }

        if ($this->purchase()->lead?->lead_state !== LeadState::VERKAUFT) {
            return false;
        }

        $deadline = $this->purchase()->purchased_at?->copy()->addDays((int) config('funnel.call.deadline_days'));

        return $deadline === null || $deadline->isFuture();
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
