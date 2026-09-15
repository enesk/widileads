<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Constants\FunnelFieldKey;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\LeadNotPurchasableException;
use App\Funnel\Snapshots\SnapshotLabels;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Marketplace\MarketplaceListing;
use App\Marketplace\MatchableLead;
use App\Models\BuyerProfile;
use App\Models\Funnel;
use App\Models\FunnelOrigin;
use App\Models\Lead;
use App\Models\Scopes\TenantScopes;
use App\Models\User;
use App\Models\Wallet;
use App\Presenters\LeadPresenter;
use App\Services\LeadPurchaseAction;
use App\Support\Money;
use App\Support\PostpaidTerms;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Der Lead-Marktplatz im eigenen Portal (Portal Phase 1).
 *
 * Zweitfassung der Filament-Seite App\Filament\Dashboard\Pages\Marketplace --
 * beide Wege laufen parallel, bis das Portal abgenommen ist. Portiert wurde
 * ausschliesslich die Huelle: Welche Leads sichtbar sind, entscheidet
 * weiterhin das MarketplaceListing, der Kauf die austauschbare Zusage
 * LeadPurchaseAction, das Geld der PurchaseService dahinter.
 *
 * **Diese Komponente maskiert nichts.** Kontaktdaten kommen ausschliesslich
 * ueber den LeadPresenter, der `Lead::contactFor()` aufruft; wer welche Fassung
 * sieht, entscheidet allein der LeadContactResolver (FB-032). Eine zweite
 * Maskierlogik im Portal waere ein Verstoss gegen Architekturleitsatz 5.
 *
 * **Gefiltert wird auf den echten Werten.** Die Region vergleicht die
 * Postleitzahl aus MatchableLead, nicht die gekuerzte Anzeigefassung -- genau
 * wie das MarketplaceListing es fuer die Kaufkriterien tut. Angeboten werden
 * dem Kaeufer dabei nur die Zweiergruppen ("76…"), die in seinen Treffern
 * ueberhaupt vorkommen: Eine freie Eingabe waere eine Suchmaske, mit der sich
 * eine verdeckte Postleitzahl Ziffer fuer Ziffer erraten liesse.
 *
 * Die Ansicht bekommt ausschliesslich fertige Werte. Aus dem Blade wird kein
 * Dienst aufgerufen, damit das nachgelieferte Markup aus `marktplatz.html` eins
 * zu eins eingesetzt werden kann.
 */
#[Layout('components.layouts.portal-app')]
class Marketplace extends Component
{
    use InteractsWithPortalTenant;

    public const SORT_NEWEST = 'newest';

    public const SORT_OLDEST = 'oldest';

    public const SORT_SCORE = 'score';

    /**
     * So viele Zeilen stehen zuerst da. Die Liste blaettert nicht, sie waechst:
     * "6 weitere laden" statt Seite zwei. Auf dem Telefon ist Blaettern der
     * schlechtere Weg -- man verliert die Stelle, an der man war.
     */
    public const PER_PAGE = 10;

    /** So viele kommen je Klick dazu. */
    public const LOAD_MORE = 10;

    /** Mehr Merkmale passen nicht in eine Zeile. */
    private const MAX_CHIPS = 4;

    /** Ab dieser Laenge gilt eine Antwort als Freitext und steht als Zitat. */
    private const FREE_TEXT_MIN = 60;

    /** Darstellung: eine Liste oder nach Funnel gruppiert. */
    public const VIEW_LIST = 'liste';

    public const VIEW_FUNNEL = 'funnel';

    /**
     * Das Antwortfeld, in dem der Funnel festhaelt, fuer welches Firmenprofil
     * die Anfrage kam (elektrikerportal.com setzt es selbst, der Endkunde sieht
     * es nicht). Es gehoert in die Unterzeile ("fuer Bollinger Elektrotechnik"),
     * nicht zu den Merkmalen.
     */
    private const COMPANY_FIELD = 'firmenprofil';

    #[Url(as: 'ansicht', except: self::VIEW_LIST)]
    public string $view = self::VIEW_LIST;

    #[Url(as: 'sortierung', except: self::SORT_NEWEST)]
    public string $sort = self::SORT_NEWEST;

    /**
     * Branche = Fragebogen. Einen eigenen Branchenschluessel gibt es im Modell
     * nicht; der Funnel ist die Einheit, in der ein Betreiber ein Gewerk
     * abfragt, und genau daran haengen auch die Kaufkriterien (funnel_ids).
     */
    #[Url(as: 'branche', except: '')]
    public string $industry = '';

    /** Zweistellige Postleitzahlengruppe, z. B. "76". */
    #[Url(as: 'region', except: '')]
    public string $region = '';

    /** Hoechstpreis in Cent, als Text in der Adresse. */
    #[Url(as: 'preis', except: '')]
    public string $maxPrice = '';

    /**
     * Innerhalb einer Anfrage einmal aufgeloest, nicht oeffentlich -- wandert
     * also nicht in den Zustand der Komponente.
     */
    private ?Wallet $wallet = null;

    /**
     * Wie viele Zeilen gerade sichtbar sind. Steht in der Adresse, damit ein
     * Neuladen nicht auf zehn zurueckfaellt.
     */
    #[Url(as: 'anzahl', except: self::PER_PAGE)]
    public int $visible = self::PER_PAGE;

    /**
     * Der Lead, dessen Blatt offen ist -- oder null. Nur der Schluessel reist
     * durch den Zustand, nie das Modell: Ein Lead im Livewire-Zustand muesste
     * zwischen den Anfragen wiederhergestellt werden, ohne den Filter, der hier
     * die einzige Zugangspruefung ist.
     */
    public ?int $openLeadId = null;

    /**
     * Die Rueckmeldung des letzten Kaufversuchs. Im Portal gibt es keine
     * Filament-Notification, die Meldung steht als Band ueber der Liste.
     */
    public ?string $message = null;

    /** success | warning */
    public string $messageLevel = 'success';

    public ?string $messageActionLabel = null;

    public ?string $messageActionUrl = null;

    /**
     * Die Treffer dieser Anfrage. Nicht oeffentlich, wandert also nicht in den
     * Zustand der Komponente -- die Liste wird fuer die Filterlisten, die Seite
     * selbst und den Kauf gebraucht und soll nicht dreimal entstehen.
     *
     * @var EloquentCollection<int, Lead>|null
     */
    private ?EloquentCollection $visibleLeads = null;

    private ?string $visibleLeadsSort = null;

    /** @var array<int, string>|null */
    private ?array $funnelOrigins = null;

    /**
     * Laeuft nach allen boot-Haken, der Mandant steht also bereits.
     */
    public function booted(): void
    {
        $user = $this->portalUser();

        abort_unless(
            $user instanceof User && Gate::forUser($user)->allows('marketplace.access', $this->portalTenant()),
            403,
        );
    }

    /**
     * Ein geaenderter Filter beginnt wieder auf Seite eins -- sonst landet der
     * Kaeufer auf einer Seite 3, die es nach dem Filtern nicht mehr gibt.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['sort', 'industry', 'region', 'maxPrice'], true)) {
            $this->visible = self::PER_PAGE;
        }
    }

    public function resetFilters(): void
    {
        $this->industry = '';
        $this->region = '';
        $this->maxPrice = '';
        $this->visible = self::PER_PAGE;
    }

    /**
     * Einen einzelnen Filter loeschen -- das ist, was das X an einem Chip tut.
     */
    public function clearFilter(string $filter): void
    {
        match ($filter) {
            'industry' => $this->industry = '',
            'region' => $this->region = '',
            'maxPrice' => $this->maxPrice = '',
            default => null,
        };

        $this->visible = self::PER_PAGE;
    }

    /**
     * Die Funnel-Reiter: erste Ebene der Seite. Ein leerer Wert heisst "Alle
     * Funnels". Intern ist das derselbe Filter wie vorher die Branche -- ein
     * Funnel ist die Einheit, in der ein Betreiber ein Gewerk abfragt.
     */
    public function setFunnel(string $funnelId): void
    {
        $this->industry = $funnelId === '' ? '' : (string) (int) $funnelId;
        $this->visible = self::PER_PAGE;
    }

    public function setView(string $view): void
    {
        $this->view = $view === self::VIEW_FUNNEL ? self::VIEW_FUNNEL : self::VIEW_LIST;
    }

    public function loadMore(): void
    {
        $this->visible += self::LOAD_MORE;
    }

    /**
     * Oeffnet das Blatt zu einem Lead.
     *
     * Geprueft wird ueber die sichtbare Liste: Ein Lead, den dieser Kaeufer
     * nicht sehen darf, ist hier schlicht nicht vorhanden.
     */
    public function openLead(int $leadId): void
    {
        $this->openLeadId = $this->visibleLeads()
            ->contains(static fn (Lead $lead): bool => (int) $lead->getKey() === $leadId)
            ? $leadId
            : null;
    }

    public function closeLead(): void
    {
        $this->openLeadId = null;
    }

    /**
     * Kauft einen Lead.
     *
     * Die Komponente entscheidet nichts: Sie reicht an die Kauf-Action weiter,
     * die Reservierung, Guthaben, Preis und Zustand unter Sperre prueft. Was
     * hier abgefangen wird, sind die alltaeglichen Ausgaenge -- ein anderer war
     * schneller, der Preis hat sich geaendert, oder das Guthaben reicht nicht.
     * Alles drei ist ein Hinweis an den Kaeufer, kein Fehler.
     *
     * Nicht `call` nennen und auch sonst keine Livewire-Methode: Der Name ist
     * belegt, `wire:click="call"` schickt dem Server ein leeres Kommando.
     *
     * @param  int|null  $priceShownCents  Preis, den diese Seite dem Kaeufer genannt hat
     */
    public function purchase(int $leadId, ?int $priceShownCents = null): void
    {
        $this->clearMessage();

        // Das Blatt geht in jedem Ausgang zu, nicht nur beim Erfolg: Die
        // Rueckmeldung steht als Band ueber der Liste, und ein offenes Blatt
        // liegt davor. Der Kaeufer haette geklickt und saehe nichts.
        $this->openLeadId = null;

        $actor = $this->portalUser();

        if (! $actor instanceof User) {
            return;
        }

        $lead = $this->visibleLeads()->first(static fn (Lead $candidate): bool => (int) $candidate->getKey() === $leadId);

        if (! $lead instanceof Lead) {
            $this->warn(__('marketplace.listing.gone'));

            return;
        }

        try {
            app(LeadPurchaseAction::class)->purchase($this->portalTenant(), $lead, $actor, $priceShownCents);
        } catch (InsufficientFundsException $exception) {
            // Eine Meldung, die dem Kaeufer sagt, dass Geld fehlt, ohne ihm zu
            // sagen, wo er es nachlegt, laesst ihn suchen.
            $this->warn($exception->getMessage());
            $this->messageActionLabel = __('marketplace.wallet.top_up.title');
            $this->messageActionUrl = $this->topUpUrl();

            return;
        } catch (LeadNotPurchasableException $exception) {
            $this->warn($exception->getMessage());

            return;
        }

        $this->message = __('marketplace.purchase.done');
        $this->messageLevel = 'success';

        // Das gekaufte Lead faellt aus der Liste; der Guthabenstand im Kopf
        // wird beim Neuzeichnen ohnehin neu gelesen. Andere Bausteine, die
        // Guthaben anzeigen, hoeren auf dieses Ereignis.
        $this->dispatch('wallet-updated');
    }

    public function render(): View
    {
        $matched = $this->filteredLeads();
        $total = $matched->count();
        $visible = max(self::PER_PAGE, $this->visible);

        $pageLeads = $matched->slice(0, $visible)->values();

        $this->loadPresentationRelations($pageLeads);

        $availableCents = $this->availableCents();

        $cards = $this->cards($pageLeads, $availableCents);
        $funnelTabs = $this->funnelTabs();

        return view('livewire.portal.marketplace', [
            'leads' => $cards,
            'funnelTabs' => $funnelTabs,
            // Gruppiert wird nur unter "Alle Funnels": Innerhalb eines Funnels
            // gaebe es genau eine Gruppe, und die Ueberschrift wiederholte nur
            // den gewaehlten Reiter.
            'groups' => $this->view === self::VIEW_FUNNEL && $this->industry === ''
                ? $this->groupByFunnel($cards, $funnelTabs)
                : null,
            'resultCount' => $total,
            'balance' => Money::format($availableCents),
            'hasFunds' => $availableCents > 0,
            // Bei Pay as you go traegt jeder Preis den Aufschlag; der Hinweis
            // sagt, warum er hoeher ist als der Preis des Verkaeufers.
            'surchargeHint' => PostpaidTerms::isPostpaid($this->wallet()) ? PostpaidTerms::surchargeHint() : null,
            'postpaid' => PostpaidTerms::isPostpaid($this->wallet()),
            // Eine Kaufsperre schliesst jeden Kauf aus, unabhaengig vom
            // Guthaben (App\Exceptions\PurchaseBlockedException). Der Knopf
            // bleibt deshalb tot, statt in eine Fehlermeldung zu laufen.
            'blocked' => (bool) $this->wallet()->purchase_blocked,
            'hasBuyerProfile' => $this->profile() !== null,
            'topUpUrl' => $this->topUpUrl(),
            'sortOptions' => $this->sortOptions(),
            'sortLabel' => $this->sortOptions()[$this->sort] ?? '',
            'industryOptions' => $this->industryOptions(),
            'regionOptions' => $this->regionOptions(),
            'priceOptions' => $this->priceOptions(),
            'activeFilterCount' => $this->activeFilterCount(),
            'activeFilters' => $this->activeFilters(),
            // Wie viele beim naechsten Klick dazukommen -- der Knopf nennt die
            // echte Zahl, nicht immer zehn.
            'remaining' => max(0, $total - $pageLeads->count()),
            'nextBatch' => min(self::LOAD_MORE, max(0, $total - $pageLeads->count())),
            'sheet' => $this->sheet($pageLeads, $availableCents),
        ])->title(__('marketplace.listing.heading'));
    }

    /**
     * Die gesetzten Filter als entfernbare Chips.
     *
     * @return list<array{key: string, label: string}>
     */
    private function activeFilters(): array
    {
        $chips = [];

        if ($this->industry !== '') {
            $chips[] = [
                'key' => 'industry',
                'label' => (string) ($this->industryOptions()[$this->industry] ?? $this->industry),
            ];
        }

        if ($this->region !== '') {
            $chips[] = ['key' => 'region', 'label' => (string) __('marketplace.listing.filters.region_option', ['group' => $this->region])];
        }

        if ($this->maxPrice !== '') {
            $chips[] = [
                'key' => 'maxPrice',
                'label' => (string) __('marketplace.listing.filters.price_option', ['amount' => Money::format((int) $this->maxPrice)]),
            ];
        }

        return $chips;
    }

    /**
     * Das offene Blatt: alle Merkmale, der Freitext und der Kaufknopf.
     *
     * Es ersetzt auf dem Telefon Detailseite und Kaufdialog in einem. Gelesen
     * wird nur aus der sichtbaren Liste -- ein fremder Lead ist hier nicht
     * vorhanden, und der Kauf laeuft ueber denselben Weg wie aus der Zeile.
     *
     * @param  EloquentCollection<int, Lead>  $leads
     * @return array<string, mixed>|null
     */
    private function sheet(EloquentCollection $leads, int $availableCents): ?array
    {
        if ($this->openLeadId === null) {
            return null;
        }

        $lead = $leads->first(fn (Lead $candidate): bool => (int) $candidate->getKey() === $this->openLeadId);

        if (! $lead instanceof Lead) {
            return null;
        }

        $purchase = app(LeadPurchaseAction::class);
        $priceCents = $purchase->priceCentsOf($lead, $this->portalTenant());
        $presenter = $this->presenter($lead);
        $answers = $this->qualificationAnswers($lead);

        return [
            'id' => (int) $lead->getKey(),
            'name' => $presenter->name(),
            'meta' => trim(implode(' · ', array_filter([
                $this->relativeTime($lead),
                $presenter->postalCode(),
            ]))),
            'is_new' => $lead->created_at?->greaterThan(now()->subDay()) ?? false,
            'attributes' => array_values(array_filter(
                $answers,
                fn (array $answer): bool => mb_strlen($answer['value']) < self::FREE_TEXT_MIN,
            )),
            'free_text' => $this->freeText($answers),
            'price' => Money::format($priceCents),
            'price_cents' => $priceCents,
            'purchasable' => ! $this->wallet()->purchase_blocked
                && $purchase->isAvailable()
                && $purchase->canPurchase($this->portalTenant(), $lead),
            'affordable' => $availableCents >= $priceCents,
        ];
    }

    /**
     * Die eine lange Antwort, die ein Kunde selbst geschrieben hat -- oder null.
     *
     * Eine eigene Spalte dafuer gibt es im Funnel nicht: Freitext ist eine
     * Antwort wie jede andere, nur laenger. Deshalb die Laenge als Merkmal.
     * Sie steht im Blatt als Zitat und nicht in der Liste der Merkmale, wo sie
     * die Zeilen sprengen wuerde.
     *
     * @param  list<array{label: string, value: string}>  $answers
     */
    private function freeText(array $answers): ?string
    {
        $longest = null;

        foreach ($answers as $answer) {
            if (mb_strlen($answer['value']) < self::FREE_TEXT_MIN) {
                continue;
            }

            if ($longest === null || mb_strlen($answer['value']) > mb_strlen($longest)) {
                $longest = $answer['value'];
            }
        }

        return $longest;
    }

    /**
     * Die Merkmale als kurze Chips: nur die Antworten, ohne die Fragen.
     *
     * In einer Zeile ist Platz fuer vier Woerter, nicht fuer vier Saetze. Wer
     * die Frage dahinter sehen will, tippt die Zeile an -- im Blatt stehen
     * Frage und Antwort vollstaendig.
     *
     * @param  list<array{label: string, value: string}>  $answers
     * @return list<string>
     */
    private function chips(array $answers): array
    {
        $chips = [];

        foreach ($answers as $answer) {
            $value = trim($answer['value']);

            if ($value === '' || mb_strlen($value) >= self::FREE_TEXT_MIN) {
                continue;
            }

            $chips[] = $value;

            if (count($chips) === self::MAX_CHIPS) {
                break;
            }
        }

        return $chips;
    }

    /**
     * Sortierungen als vollstaendige Saetze -- ein Kaeufer liest "Neueste
     * zuerst", nicht "created_at absteigend".
     *
     * @return array<string, string>
     */
    private function sortOptions(): array
    {
        return [
            self::SORT_NEWEST => __('marketplace.listing.sort.newest'),
            self::SORT_OLDEST => __('marketplace.listing.sort.oldest'),
            self::SORT_SCORE => __('marketplace.listing.sort.score'),
        ];
    }

    /**
     * Die Angebote dieser Seite, fertig fuer die Ansicht aufbereitet.
     *
     * @param  EloquentCollection<int, Lead>  $leads
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
    private function cards(EloquentCollection $leads, int $availableCents): array
    {
        $purchase = app(LeadPurchaseAction::class);
        $tenant = $this->portalTenant();
        $blocked = (bool) $this->wallet()->purchase_blocked;

        return $leads
            ->map(function (Lead $lead) use ($purchase, $tenant, $availableCents, $blocked): array {
                // Mit Kaeufer gerechnet: Bei Pay as you go steht hier der
                // Gesamtpreis inklusive Aufschlag -- also das, was dieser
                // Kaeufer tatsaechlich traegt (LP-POSTPAID-007).
                $priceCents = $purchase->priceCentsOf($lead, $tenant);
                $presenter = $this->presenter($lead);

                return [
                    'id' => (int) $lead->getKey(),
                    // Kontaktdaten ausschliesslich ueber den Presenter.
                    'name' => $presenter->name(),
                    'created_at' => $this->relativeTime($lead),
                    'created_at_exact' => $lead->created_at?->format('d.m.Y H:i') ?? '',
                    'is_new' => $lead->created_at?->greaterThan(now()->subDay()) ?? false,
                    'postal_code' => $presenter->postalCode(),
                    'attributes' => $this->qualificationAnswers($lead),
                    'chips' => $this->chips(array_values(array_filter(
                        $this->qualificationAnswers($lead),
                        static fn (array $answer): bool => ($answer['key'] ?? '') !== self::COMPANY_FIELD,
                    ))),
                    'funnel_id' => (string) ($lead->funnel_id ?? ''),
                    'funnel_name' => $lead->funnel instanceof Funnel
                        ? $lead->funnel->name
                        : (string) __('marketplace.listing.unknown_funnel'),
                    'funnel_icon' => $this->funnelIcon($lead->funnel),
                    'portal' => $this->portalHost($lead),
                    'company' => $this->companyOf($lead),
                    'price' => Money::format($priceCents),
                    // Der angezeigte Preis geht beim Kauf zurueck an den Server:
                    // Hat der Verkaeufer ihn inzwischen geaendert, wird der Kauf
                    // abgelehnt statt teurer abgerechnet (LP-WALLET-007).
                    'price_cents' => $priceCents,
                    'purchasable' => ! $blocked && $purchase->isAvailable() && $purchase->canPurchase($tenant, $lead),
                    // Nur eine Anzeige-Entscheidung: Ob das Guthaben wirklich
                    // reicht, prueft die Kauf-Action unter Sperre.
                    'affordable' => $availableCents >= $priceCents,
                ];
            })
            ->all();
    }

    /**
     * Die sichtbaren Leads nach den Filtern dieser Seite.
     *
     * @return EloquentCollection<int, Lead>
     */
    private function filteredLeads(): EloquentCollection
    {
        $leads = $this->visibleLeads();

        if ($this->industry !== '') {
            $funnelId = (int) $this->industry;
            $leads = $leads->filter(static fn (Lead $lead): bool => (int) $lead->funnel_id === $funnelId);
        }

        if ($this->region !== '') {
            $region = $this->region;
            $leads = $leads->filter(fn (Lead $lead): bool => $this->postalGroupOf($lead) === $region);
        }

        if ($this->maxPrice !== '') {
            $maxCents = (int) $this->maxPrice;
            $purchase = app(LeadPurchaseAction::class);
            $tenant = $this->portalTenant();
            $leads = $leads->filter(static fn (Lead $lead): bool => $purchase->priceCentsOf($lead, $tenant) <= $maxCents);
        }

        return $leads->values();
    }

    /**
     * Die Leads, die dieser Kaeufer sehen darf -- ungefiltert und in der
     * Reihenfolge der Sortierung.
     *
     * Einmal je Anfrage: Die Liste wird zum Aufbau der Filterlisten, fuer die
     * Seite selbst und beim Kauf gebraucht.
     *
     * @return EloquentCollection<int, Lead>
     */
    private function visibleLeads(): EloquentCollection
    {
        if ($this->visibleLeads instanceof EloquentCollection && $this->visibleLeadsSort === $this->sort) {
            return $this->visibleLeads;
        }

        $matched = app(MarketplaceListing::class)->for(
            $this->portalTenant(),
            $this->profile(),
            $this->sort === self::SORT_SCORE
                ? MarketplaceListing::SORT_SCORE
                : MarketplaceListing::SORT_NEWEST,
        );

        if ($this->sort === self::SORT_OLDEST) {
            $matched = $matched->reverse();
        }

        // Das MarketplaceListing gibt eine gewoehnliche Collection heraus; zum
        // Nachladen der Beziehungen wird eine Eloquent-Collection gebraucht.
        $leads = new EloquentCollection($matched->values()->all());

        // Der Verkaeufer haengt am Preis (LP-WALLET-003); ohne ihn fragte die
        // Liste ihn je Lead einzeln nach.
        $leads->loadMissing('tenant');

        $this->visibleLeadsSort = $this->sort;

        return $this->visibleLeads = $leads;
    }

    /**
     * Fragebogen und Fassung gehoeren dem Betreiber, gelesen wird im Kontext
     * des Kaeufers -- ohne das Abschalten des Scopes kaeme hier null heraus
     * (FB-055a). Geladen wird nur fuer die angezeigte Seite: Die Momentaufnahme
     * einer Fassung ist gross, und gebraucht wird sie nur fuer die Beschriftung
     * sichtbarer Karten.
     *
     * @param  EloquentCollection<int, Lead>  $leads
     */
    private function loadPresentationRelations(EloquentCollection $leads): void
    {
        $leads->loadMissing([
            'answers',
            'funnelVersion' => static fn (Relation $version) => $version->withoutGlobalScopes(TenantScopes::names()),
        ]);
    }

    /**
     * Die Fragebogen der Treffer als Filterliste.
     *
     * @return array<array-key, string>
     */
    private function industryOptions(): array
    {
        $options = [];

        foreach ($this->visibleLeads() as $lead) {
            $funnelId = $lead->funnel_id;

            if ($funnelId === null) {
                continue;
            }

            $funnel = $lead->funnel;

            $options[(string) $funnelId] = $funnel instanceof Funnel
                ? $funnel->name
                : (string) __('marketplace.listing.unknown_funnel');
        }

        asort($options);

        return $options;
    }

    /**
     * Die Postleitzahlengruppen der Treffer, z. B. "76…".
     *
     * @return array<array-key, string>
     */
    private function regionOptions(): array
    {
        $options = [];

        foreach ($this->visibleLeads() as $lead) {
            $group = $this->postalGroupOf($lead);

            if ($group === null) {
                continue;
            }

            $options[$group] = (string) __('marketplace.listing.filters.region_option', ['group' => $group]);
        }

        ksort($options);

        return $options;
    }

    /**
     * Die vorkommenden Preise als Hoechstpreis-Liste.
     *
     * @return array<array-key, string>
     */
    private function priceOptions(): array
    {
        $purchase = app(LeadPurchaseAction::class);
        $options = [];

        foreach ($this->visibleLeads() as $lead) {
            $cents = $purchase->priceCentsOf($lead, $this->portalTenant());
            $options[(string) $cents] = (string) __('marketplace.listing.filters.price_option', [
                'amount' => Money::format($cents),
            ]);
        }

        ksort($options, SORT_NUMERIC);

        return $options;
    }

    private function activeFilterCount(): int
    {
        // Der Funnel zaehlt nicht mit: Er steht oben als Reiter und ist damit
        // schon sichtbar. Mitgezaehlt klappte das Filterfeld bei jedem Wechsel
        // des Reiters auf.
        return count(array_filter([$this->region, $this->maxPrice], static fn (string $value): bool => $value !== ''));
    }

    /**
     * Die Zweiergruppe der echten Postleitzahl -- nicht der Anzeigefassung.
     */
    private function postalGroupOf(Lead $lead): ?string
    {
        $postalCode = MatchableLead::fromLead($lead)->postalCode;

        if ($postalCode === null || strlen($postalCode) < 2) {
            return null;
        }

        return substr($postalCode, 0, 2);
    }

    /**
     * Das frei verfuegbare Guthaben in Cent.
     *
     * Massgeblich ist `available_cents`, nicht der Saldo: Was fuer andere Leads
     * schon reserviert ist, steht fuer den naechsten Kauf nicht mehr zur
     * Verfuegung (LP-WALLET-005).
     */
    private function availableCents(): int
    {
        return $this->wallet()->available_cents;
    }

    /**
     * Das Kauf-Wallet dieser Seite. Einmal je Anfrage gelesen: Preis, Sperre
     * und Guthabenstand fragen alle danach.
     */
    private function wallet(): Wallet
    {
        return $this->wallet ??= Wallet::forBuyer($this->portalTenant());
    }

    private function topUpUrl(): string
    {
        return route('portal.wallet', ['tenant' => $this->portalTenant()]);
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

    private function presenter(Lead $lead): LeadPresenter
    {
        return new LeadPresenter($lead, $this->portalUser());
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
                'key' => (string) $answer->field_key,
                'label' => $labels[$answer->field_key] ?? $answer->field_key,
                'value' => is_array($value)
                    ? implode(', ', array_map($readable, $value))
                    : $readable($value),
            ];
        }

        return $answers;
    }

    /**
     * Die Funnel-Reiter mit Zaehlern.
     *
     * Gezaehlt wird ueber Region und Hoechstpreis, aber nicht ueber den Funnel
     * selbst -- sonst stuende neben jedem anderen Reiter eine Null, sobald
     * einer gewaehlt ist. Sortiert nach Anzahl: Wo die meisten Leads warten,
     * steht vorn.
     *
     * @return list<array{id: string, name: string, icon: string, count: int, active: bool}>
     */
    private function funnelTabs(): array
    {
        $leads = $this->visibleLeads();

        if ($this->region !== '') {
            $region = $this->region;
            $leads = $leads->filter(fn (Lead $lead): bool => $this->postalGroupOf($lead) === $region);
        }

        if ($this->maxPrice !== '') {
            $maxCents = (int) $this->maxPrice;
            $purchase = app(LeadPurchaseAction::class);
            $tenant = $this->portalTenant();
            $leads = $leads->filter(static fn (Lead $lead): bool => $purchase->priceCentsOf($lead, $tenant) <= $maxCents);
        }

        $perFunnel = [];

        foreach ($leads as $lead) {
            if ($lead->funnel_id === null) {
                continue;
            }

            $id = (string) $lead->funnel_id;
            $perFunnel[$id] ??= [
                'id' => $id,
                'name' => $lead->funnel instanceof Funnel ? $lead->funnel->name : (string) __('marketplace.listing.unknown_funnel'),
                'icon' => $this->funnelIcon($lead->funnel),
                'count' => 0,
                'active' => $this->industry === $id,
            ];
            $perFunnel[$id]['count']++;
        }

        uasort($perFunnel, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return [
            [
                'id' => '',
                'name' => (string) __('marketplace.listing.funnels.all'),
                'icon' => 'grid',
                'count' => $leads->count(),
                'active' => $this->industry === '',
            ],
            ...array_values($perFunnel),
        ];
    }

    /**
     * Die Karten nach Funnel, in der Reihenfolge der Reiter.
     *
     * @param  list<array<string, mixed>>  $cards
     * @param  list<array{id: string, name: string, icon: string, count: int, active: bool}>  $tabs
     * @return list<array{id: string, name: string, icon: string, count: int, cards: list<array<string, mixed>>}>
     */
    private function groupByFunnel(array $cards, array $tabs): array
    {
        $groups = [];

        foreach ($tabs as $tab) {
            if ($tab['id'] === '') {
                continue;
            }

            $inGroup = array_values(array_filter($cards, static fn (array $card): bool => $card['funnel_id'] === $tab['id']));

            if ($inGroup === []) {
                continue;
            }

            $groups[] = [
                'id' => $tab['id'],
                'name' => $tab['name'],
                'icon' => $tab['icon'],
                // Die Zahl aller Leads dieses Funnels, nicht der geladenen
                // Karten -- dieselbe Zahl wie im Reiter.
                'count' => $tab['count'],
                'cards' => $inGroup,
            ];
        }

        return $groups;
    }

    /**
     * Ein Symbol je Funnel.
     *
     * Ein eigenes Feld dafuer hat der Funnel nicht. Abgeleitet wird es aus Name
     * und Kurzname -- Elektriker ein Blitz, Tiere eine Pfote, Sanitaer ein
     * Schraubenschluessel. Alles andere bekommt ein neutrales Raster, statt ein
     * falsches Gewerk zu behaupten.
     */
    private function funnelIcon(?Funnel $funnel): string
    {
        if (! $funnel instanceof Funnel) {
            return 'grid';
        }

        $text = mb_strtolower($funnel->name.' '.$funnel->slug);

        return match (true) {
            str_contains($text, 'elektr') || str_contains($text, 'strom') => 'zap',
            str_contains($text, 'pfote') || str_contains($text, 'tier') || str_contains($text, 'hund') || str_contains($text, 'katze') => 'paw',
            str_contains($text, 'sanit') || str_contains($text, 'heiz') || str_contains($text, 'bad') => 'wrench',
            default => 'grid',
        };
    }

    /**
     * Die Seite, auf der die Anfrage entstand, ohne Protokoll: elektrikerportal.com.
     *
     * Zuerst die Herkunft des Leads selbst, danach die erste freigegebene
     * Herkunft des Funnels. Ohne beides steht nichts -- ein erfundener Name
     * waere schlimmer als keiner.
     */
    private function portalHost(Lead $lead): ?string
    {
        $origin = $lead->embed_origin;

        if (! is_string($origin) || $origin === '') {
            $origin = $this->funnelOrigins()[(int) $lead->funnel_id] ?? null;
        }

        if (! is_string($origin) || $origin === '') {
            return null;
        }

        $host = parse_url($origin, PHP_URL_HOST) ?: $origin;

        return preg_replace('/^www\./', '', (string) $host);
    }

    /**
     * Die erste freigegebene Herkunft je Funnel, einmal je Anfrage.
     *
     * @return array<int, string>
     */
    private function funnelOrigins(): array
    {
        if ($this->funnelOrigins !== null) {
            return $this->funnelOrigins;
        }

        $ids = $this->visibleLeads()->pluck('funnel_id')->filter()->unique()->values()->all();

        return $this->funnelOrigins = FunnelOrigin::query()
            ->whereIn('funnel_id', $ids)
            ->orderBy('id')
            ->get()
            ->groupBy('funnel_id')
            ->map(static fn ($origins): string => (string) $origins->first()->origin)
            ->all();
    }

    /**
     * Fuer welches Firmenprofil die Anfrage kam -- oder null.
     */
    private function companyOf(Lead $lead): ?string
    {
        foreach ($lead->answers as $answer) {
            if ($answer->field_key !== self::COMPANY_FIELD) {
                continue;
            }

            $value = is_array($answer->value) ? implode(', ', $answer->value) : (string) $answer->value;

            return trim($value) === '' ? null : trim($value);
        }

        return null;
    }

    private function profile(): ?BuyerProfile
    {
        return BuyerProfile::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->where('tenant_id', $this->portalTenant()->getKey())
            ->first();
    }

    private function warn(string $message): void
    {
        $this->message = $message;
        $this->messageLevel = 'warning';
    }

    private function clearMessage(): void
    {
        $this->message = null;
        $this->messageLevel = 'success';
        $this->messageActionLabel = null;
        $this->messageActionUrl = null;
    }
}
