<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Constants\FunnelFieldKey;
use App\Funnel\Snapshots\SnapshotLabels;
use App\Marketplace\MarketplaceListing;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\Tenant;
use App\Presenters\LeadPresenter;
use App\Services\LeadPurchaseAction;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Die echten Zahlen, mit denen die Onboarding-Mails reden (LP-ONBOARD-014).
 *
 * Die Mails sollen keine Allgemeinplaetze verschicken, sondern den Stand
 * dieses einen Kaeufers: der naechste offene Schritt mit seinem Ziel, die
 * Anfragen, die gerade zu seinen Kaufkriterien passen, sein Fortschritt als
 * Zahl. Das steht hier und nicht in den vier Mailable-Klassen, weil zwei
 * Stellen dieselben Werte brauchen -- die Mail beim Rendern und der Job
 * davor, der anhand derselben Zahlen entscheidet, ob er ueberhaupt sendet.
 * Liefen sie auseinander, verschickte die Strecke eine Mail "3 Anfragen
 * warten" mit null Karten darin.
 *
 * **Hier wird nicht maskiert.** Die Vorschaukarten kommen ueber
 * LeadPresenter::forTeamView() -- die Fassung, in der Name, E-Mail und
 * Rufnummer fuer jeden Betrachter verdeckt sind (FB-032). Eine Mail laesst
 * sich weiterleiten; sie ist der letzte Ort, an dem ein Klartext stehen darf,
 * den der Kaeufer nicht gekauft hat.
 */
class OnboardingMailContent
{
    /**
     * So viele Vorschaukarten passen in die Mail "leads_waiting".
     */
    public const PREVIEW_LIMIT = 3;

    /**
     * So viele passende Anfragen muessen da sein, damit die Mail
     * "leads_waiting" ueberhaupt rausgeht.
     */
    public const LEADS_WAITING_MINIMUM = 3;

    /** Mehr Merkmale passen nicht in eine Karte -- wie im Marktplatz. */
    private const MAX_CHIPS = 3;

    /** Ab dieser Laenge gilt eine Antwort als Freitext und faellt raus. */
    private const FREE_TEXT_MIN = 60;

    /**
     * Die passenden Anfragen je Workspace, einmal geholt.
     *
     * Der Job fragt die Zahl, die Mail danach die Karten und noch einmal die
     * Zahl -- ohne das hier waeren das drei Durchlaeufe durch dieselbe
     * Liste. Der Dienst lebt nur fuer die Dauer einer Mail, das Merken ist
     * also nicht laenger gueltig, als es sein darf.
     *
     * @var array<int, Collection<int, Lead>>
     */
    private array $matching = [];

    public function __construct(
        private readonly OnboardingChecklist $checklist,
        private readonly MarketplaceListing $listing,
        private readonly LeadPurchaseAction $purchases,
    ) {}

    /**
     * Der eine Schritt, zu dem die Mail auffordert -- Beschriftung und Ziel.
     *
     * Kommt aus OnboardingChecklist: dieselbe Liste, die die Aktivierungskarte
     * im Dashboard zeigt (LP-ONBOARD-012). Die Mail nimmt daraus die erste
     * offene Zeile. Null heisst: nichts mehr offen.
     *
     * @return array{label: string, url: string}|null
     */
    public function nextAction(Tenant $workspace): ?array
    {
        return $this->checklist->nextAction($workspace);
    }

    /**
     * Der Fortschritt als zwei Zahlen fuer "3 von 5 Schritten erledigt".
     *
     * Gezaehlt werden die Zeilen der Aktivierungskarte und nicht die neun
     * Faelle des Enums: Die Mail soll dieselbe Zahl nennen, die der Kaeufer im
     * Dashboard sieht.
     *
     * @return array{done: int, total: int}
     */
    public function checklistProgress(Tenant $workspace): array
    {
        return $this->checklist->counts($workspace);
    }

    /**
     * Wie viele Anfragen gerade zu den Kaufkriterien dieses Kaeufers passen.
     *
     * Dieselbe Quelle wie der Marktplatz (MarketplaceListing) -- die Mail
     * darf keine Zahl nennen, die der Kaeufer nach dem Klick nicht vorfindet.
     */
    public function matchingLeadCount(Tenant $workspace): int
    {
        return $this->matchingLeads($workspace)->count();
    }

    /**
     * Bis zu drei Vorschaukarten, anonymisiert.
     *
     * @return list<array{funnel: string, region: string, age: string, price: string, chips: list<string>}>
     */
    public function previewCards(Tenant $workspace, int $limit = self::PREVIEW_LIMIT): array
    {
        return $this->matchingLeads($workspace)
            ->take($limit)
            ->map(fn (Lead $lead): array => $this->card($workspace, $lead))
            ->values()
            ->all();
    }

    /**
     * Die passenden Anfragen -- unmaskierte Modelle, nur fuer das Zaehlen und
     * fuer den Weg in card().
     *
     * @return Collection<int, Lead>
     */
    private function matchingLeads(Tenant $workspace): Collection
    {
        $id = (int) $workspace->getKey();

        return $this->matching[$id] ??= $this->listing->for($workspace, $workspace->buyerProfile);
    }

    /**
     * Eine Karte. Kontaktdaten kommen ausschliesslich aus der Teamsicht des
     * Presenters -- in der Mail steht kein Name und keine Nummer.
     *
     * @return array{funnel: string, region: string, age: string, price: string, chips: list<string>}
     */
    private function card(Tenant $workspace, Lead $lead): array
    {
        $presenter = LeadPresenter::forTeamView($lead);

        return [
            'funnel' => $lead->funnel instanceof Funnel
                ? $lead->funnel->name
                : (string) __('marketplace.listing.unknown_funnel'),
            'region' => $presenter->postalCode(),
            'age' => $lead->created_at?->diffForHumans() ?? '',
            'price' => Money::format($this->purchases->priceCentsOf($lead, $workspace)),
            'chips' => $this->chips($lead),
        ];
    }

    /**
     * Die kurzen Antworten eines Leads als Merkmale -- wie im Marktplatz,
     * nur knapper. Reservierte Felder (Name, E-Mail, Telefon) sind dabei
     * ausgeschlossen, bevor irgendetwas gelesen wird.
     *
     * @return list<string>
     */
    private function chips(Lead $lead): array
    {
        $snapshot = $lead->funnelVersion?->snapshot;
        $labels = SnapshotLabels::options(is_array($snapshot) ? $snapshot : null);

        $chips = [];

        foreach ($lead->answers as $answer) {
            if (FunnelFieldKey::isReserved($answer->field_key)) {
                continue;
            }

            $readable = static fn (mixed $single): string => $labels[$answer->field_key][(string) $single]
                ?? (string) $single;

            $value = $answer->value;
            $text = trim(is_array($value)
                ? implode(', ', array_map($readable, $value))
                : $readable($value));

            if ($text === '' || mb_strlen($text) >= self::FREE_TEXT_MIN) {
                continue;
            }

            $chips[] = $text;

            if (count($chips) === self::MAX_CHIPS) {
                break;
            }
        }

        return $chips;
    }
}
