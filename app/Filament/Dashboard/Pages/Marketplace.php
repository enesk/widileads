<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Constants\FunnelFieldKey;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\LeadNotPurchasableException;
use App\Funnel\Snapshots\SnapshotLabels;
use App\Marketplace\MarketplaceListing;
use App\Models\BuyerProfile;
use App\Models\Lead;
use App\Models\Scopes\TenantScopes;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Presenters\LeadPresenter;
use App\Services\LeadPurchaseAction;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;

/**
 * Der Lead-Marktplatz eines Kaeufers (FB-053).
 *
 * **Diese Seite maskiert nichts.** Kontaktdaten kommen ausschliesslich ueber
 * den LeadPresenter, der `Lead::contactFor()` aufruft; wer welche Fassung
 * sieht, entscheidet allein der LeadContactResolver aus FB-032. Eine zweite
 * Maskierlogik waere ein Verstoss gegen Architekturleitsatz 5 -- und der
 * Architektur-Test aus FB-042 schlaegt darauf an.
 *
 * Ebenso wenig kauft sie: Der Knopf fragt die austauschbare Zusage
 * LeadPurchaseAction.
 *
 * Gerendert wird eine eigene Ansicht statt einer Filament-Tabelle. Der
 * Marktplatz ist ein Schaufenster: Der Kaeufer vergleicht nicht Zeilen, er
 * sieht sich Angebote an und kauft eines. Welche Leads sichtbar sind,
 * entscheidet weiterhin das MarketplaceListing -- der LeadMatcher ist eine
 * reine PHP-Funktion und laesst sich nicht in eine Tabellenabfrage uebersetzen.
 */
class Marketplace extends Page
{
    public const SORT_NEWEST = 'newest';

    public const SORT_OLDEST = 'oldest';

    public const SORT_SCORE = 'score';

    protected string $view = 'filament.dashboard.pages.marketplace';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?int $navigationSort = -10;

    #[Url(as: 'sortierung', except: self::SORT_NEWEST)]
    public string $sort = self::SORT_NEWEST;

    /**
     * Die Ueberschrift steht in der Ansicht selbst, damit Guthaben und
     * Einleitung in einer Zeile stehen koennen.
     */
    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getTitle(): string|Htmlable
    {
        return __('marketplace.listing.heading');
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.marketplace');
    }

    public static function getNavigationLabel(): string
    {
        return __('marketplace.listing.nav_label');
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        return Gate::allows('marketplace.access', $tenant);
    }

    /**
     * Sortierungen als vollstaendige Saetze -- ein Kaeufer liest "Neueste
     * zuerst", nicht "created_at absteigend".
     *
     * @return array<string, string>
     */
    public function sortOptions(): array
    {
        return [
            self::SORT_NEWEST => __('marketplace.listing.sort.newest'),
            self::SORT_OLDEST => __('marketplace.listing.sort.oldest'),
            self::SORT_SCORE => __('marketplace.listing.sort.score'),
        ];
    }

    /**
     * Das frei verfuegbare Guthaben in Cent.
     *
     * Massgeblich ist `available_cents`, nicht der Saldo: Was fuer andere Leads
     * schon reserviert ist, steht fuer den naechsten Kauf nicht mehr zur
     * Verfuegung (LP-WALLET-005).
     */
    public function balance(): int
    {
        return Wallet::forBuyer($this->tenant())->available_cents;
    }

    public function hasBuyerProfile(): bool
    {
        return $this->profile() !== null;
    }

    /**
     * Die Angebote dieser Seite, fertig fuer die Ansicht aufbereitet.
     *
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     created_at: string,
     *     created_at_exact: string,
     *     is_new: bool,
     *     postal_code: string,
     *     attributes: array<int, array{label: string, value: string}>,
     *     price: string,
     *     price_cents: int,
     *     purchasable: bool,
     *     affordable: bool,
     * }>
     */
    public function leads(): array
    {
        $purchase = app(LeadPurchaseAction::class);
        $tenant = $this->tenant();
        // Einmal gelesen und fuer alle Karten verwendet: Der Stand aendert sich
        // waehrend des Aufbaus einer Seite nicht, und je Karte eine Abfrage
        // waere die teuerste Art, dieselbe Zahl zu erfahren.
        $available = $this->balance();

        return $this->visibleLeads()
            ->map(function (Lead $lead) use ($purchase, $tenant, $available): array {
                $priceCents = $purchase->priceCentsOf($lead);

                return [
                    'id' => (int) $lead->getKey(),
                    // Kontaktdaten ausschliesslich ueber den Presenter.
                    'name' => $this->presenter($lead)->name(),
                    'created_at' => $this->relativeTime($lead),
                    'created_at_exact' => $lead->created_at?->format('d.m.Y H:i') ?? '',
                    'is_new' => $lead->created_at?->greaterThan(now()->subDay()) ?? false,
                    'postal_code' => $this->presenter($lead)->postalCode(),
                    'attributes' => $this->qualificationAnswers($lead),
                    'price' => __('marketplace.listing.price', ['amount' => self::formatCents($priceCents)]),
                    // Der angezeigte Preis geht beim Kauf zurueck an den Server:
                    // Hat der Verkaeufer ihn inzwischen geaendert, wird der Kauf
                    // abgelehnt statt teurer abgerechnet (LP-WALLET-007).
                    'price_cents' => $priceCents,
                    'purchasable' => $purchase->isAvailable() && $purchase->canPurchase($tenant, $lead),
                    // Nur eine Anzeige-Entscheidung: Ob das Guthaben wirklich
                    // reicht, prueft die Kauf-Action unter Sperre. Ein Knopf,
                    // der sicher in eine Fehlermeldung fuehrt, gehoert aber
                    // nicht anklickbar auf die Seite.
                    'affordable' => $available >= $priceCents,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Kauft einen Lead.
     *
     * Die Seite entscheidet nichts: Sie reicht an die PurchaseLead-Action
     * weiter, die Reservierung, Guthaben, Preis und Zustand unter Sperre
     * prueft. Was hier abgefangen wird, sind die alltaeglichen Ausgaenge -- ein
     * anderer war schneller, der Preis hat sich geaendert, oder das Guthaben
     * reicht nicht. Alles drei ist ein Hinweis an den Kaeufer, kein Fehler.
     *
     * Beim fehlenden Guthaben bekommt der Hinweis den Weg zur Aufladung mit:
     * Eine Meldung, die dem Kaeufer sagt, dass Geld fehlt, ohne ihm zu sagen,
     * wo er es nachlegt, laesst ihn suchen.
     *
     * @param  int|null  $priceShownCents  Preis, den diese Seite dem Kaeufer genannt hat
     */
    public function purchase(int $leadId, ?int $priceShownCents = null): void
    {
        $actor = $this->viewer();

        if (! $actor instanceof User) {
            return;
        }

        $lead = $this->visibleLeads()->first(static fn (Lead $candidate): bool => (int) $candidate->getKey() === $leadId);

        if (! $lead instanceof Lead) {
            Notification::make()
                ->warning()
                ->title(__('marketplace.listing.gone'))
                ->send();

            return;
        }

        try {
            $purchase = app(LeadPurchaseAction::class)->purchase($this->tenant(), $lead, $actor, $priceShownCents);
        } catch (InsufficientFundsException $exception) {
            Notification::make()
                ->warning()
                ->title($exception->getMessage())
                ->actions([
                    Action::make('topUp')
                        ->label(__('marketplace.wallet.top_up.title'))
                        ->url(WalletTopUp::getUrl())
                        ->button(),
                ])
                ->persistent()
                ->send();

            return;
        } catch (LeadNotPurchasableException $exception) {
            Notification::make()
                ->warning()
                ->title($exception->getMessage())
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title(__('marketplace.purchase.done'))
            ->send();

        // Direkt zum gekauften Lead: Der Kaeufer hat gerade bezahlt und will
        // die Kontaktdaten sehen, nicht erst eine Liste durchsuchen.
        $this->redirect(PurchasedLeadDetail::getUrl(['purchase' => $purchase->getKey()]));
    }

    /**
     * Die Leads, die dieser Kaeufer sehen darf.
     *
     * @return Collection<int, Lead>
     */
    private function visibleLeads(): Collection
    {
        $matched = app(MarketplaceListing::class)->for(
            $this->tenant(),
            $this->profile(),
            $this->sort === self::SORT_SCORE
                ? MarketplaceListing::SORT_SCORE
                : MarketplaceListing::SORT_NEWEST,
        );

        $ids = $matched->map(static fn (Lead $lead): int => (int) $lead->getKey())->all();

        if ($ids === []) {
            return collect();
        }

        $leads = Lead::query()
            // Der Kaeufer besitzt keinen dieser Leads -- ohne das Abschalten
            // des Mandanten-Scopes waere der Marktplatz immer leer. Welche
            // Leads das sind, hat das MarketplaceListing entschieden.
            ->withoutGlobalScopes(TenantScopes::names())
            ->with([
                'answers',
                // Der Verkaeufer haengt am Preis (LP-WALLET-003); ohne ihn
                // fragte die Liste ihn je Lead einzeln nach.
                'tenant',
                // Fragebogen und Fassung gehoeren dem Betreiber, gelesen wird
                // im Kontext des Kaeufers (FB-055a).
                'funnel' => static fn (Relation $funnel) => $funnel->withoutGlobalScopes(TenantScopes::names()),
                'funnelVersion' => static fn (Relation $version) => $version->withoutGlobalScopes(TenantScopes::names()),
            ])
            ->whereIn('id', $ids)
            ->get()
            // Die Reihenfolge hat das MarketplaceListing bestimmt; die Abfrage
            // gibt sie nicht zurueck.
            ->sortBy(static fn (Lead $lead): int => (int) array_search((int) $lead->getKey(), $ids, true))
            ->values();

        return $this->sort === self::SORT_OLDEST ? $leads->reverse()->values() : $leads;
    }

    /**
     * "vor 2 Std.", "gestern, 09:22", ab zwei Tagen das Datum.
     */
    private function relativeTime(Lead $lead): string
    {
        $created = $lead->created_at;

        if ($created === null) {
            return '-';
        }

        if ($created->greaterThan(now()->subDay())) {
            return $created->diffForHumans();
        }

        if ($created->greaterThan(now()->subDays(2))) {
            return __('marketplace.listing.yesterday', ['time' => $created->format('H:i')]);
        }

        return $created->format('d.m.Y');
    }

    /**
     * Cent als Betrag in der Schreibweise, die der Kaeufer im Portal sieht.
     */
    private static function formatCents(int $cents): string
    {
        return Money::format($cents);
    }

    private function presenter(Lead $lead): LeadPresenter
    {
        return new LeadPresenter($lead, $this->viewer());
    }

    /**
     * Die Qualifizierungsantworten eines Leads -- **ohne** die reservierten
     * Kontaktfelder.
     *
     * Das ist der Punkt, an dem eine Marktplatzliste am ehesten Klartext
     * ausplaudert: `lead_answers` enthaelt auch die Antworten auf `email`,
     * `telefon` und `plz`. Wer sie ungefiltert ausgibt, umgeht die Maskierung,
     * ohne je eine Kontaktspalte anzufassen.
     *
     * Beschriftungen kommen aus der Fassung, unter der der Lead entstanden ist:
     * Ein Kaeufer soll lesen, was der Kunde angeklickt hat, nicht
     * "rasse_groesse: gross".
     *
     * @return array<int, array{label: string, value: string}>
     */
    private function qualificationAnswers(Lead $lead): array
    {
        $snapshot = $lead->funnelVersion?->snapshot;
        $labels = SnapshotLabels::questions(is_array($snapshot) ? $snapshot : null);
        $options = SnapshotLabels::options(is_array($snapshot) ? $snapshot : null);

        $answers = [];

        foreach ($lead->answers as $answer) {
            if (FunnelFieldKey::isReserved($answer->field_key)) {
                continue;
            }

            $readable = static fn (mixed $single): string => $options[$answer->field_key][(string) $single]
                ?? (string) $single;

            $value = $answer->value;

            $answers[] = [
                'label' => $labels[$answer->field_key] ?? $answer->field_key,
                'value' => is_array($value)
                    ? implode(', ', array_map($readable, $value))
                    : $readable($value),
            ];
        }

        return $answers;
    }

    private function profile(): ?BuyerProfile
    {
        return BuyerProfile::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->where('tenant_id', $this->tenant()->getKey())
            ->first();
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
