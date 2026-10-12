<?php

declare(strict_types=1);

namespace App\Marketplace;

use App\Constants\BuyerRegion;
use App\Constants\FunnelFieldKey;
use App\Constants\LeadState;
use App\Constants\TenantType;
use App\Models\BuyerProfile;
use App\Models\Lead;
use App\Models\LeadWatchlistEntry;
use App\Models\Scopes\TenantScopes;
use App\Models\Tenant;
use App\Support\Geo\PlzDirectory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Welche Leads ein Kaeufer im Marktplatz sieht (FB-053).
 *
 * Die zweite und letzte Stelle, an der der Mandanten-Scope bewusst abgeschaltet
 * wird -- nach dem MarketplaceCatalog aus FB-051. Das ist der Kern des
 * Marktplatzes: Ein Kaeufer kauft Leads, die einem Betreiber gehoeren, also
 * muss er sie sehen koennen, bevor sie ihm gehoeren. Deshalb steht die Abfrage
 * hier und nicht in einer Livewire-Komponente: Was freigegeben ist, soll an
 * einer benannten Stelle nachlesbar und pruefbar sein.
 *
 * Vier Einschraenkungen, alle notwendig:
 *
 *  - nur Leads im Zustand `verfuegbar` oder `reserviert`. Alles davor ist noch
 *    nicht freigegeben, alles danach nicht mehr zu haben;
 *  - nur Leads von Betreiber-Mandanten. Legt ein Kaeufer-Mandant selbst einen
 *    Funnel an, landen dessen Leads nie im Marktplatz;
 *  - nur Leads, deren Personenbezug noch besteht -- anonymisierte Leads (FB-037)
 *    sind nichts mehr wert;
 *  - nur Leads, die die Kaufkriterien des Kaeufers passieren (FB-051).
 *
 * **Die Maskierung passiert nicht hier.** Diese Stelle liefert Lead-Modelle;
 * wer welche Kontaktdaten sieht, entscheidet ausschliesslich der
 * LeadContactResolver ueber `Lead::contactFor()` (FB-032). Gefiltert wird
 * dagegen auf den echten Werten -- ein Postleitzahl-Kriterium auf gekuerzten
 * Werten waere entweder zu grob oder schlicht falsch.
 */
class MarketplaceListing
{
    public const SORT_NEWEST = 'newest';

    public const SORT_SCORE = 'score';

    /**
     * Maskierzeichen fuer LIKE-Vergleiche. Ein Postleitzahl-Praefix besteht
     * normalerweise aus Ziffern; das Zeichen kommt darin nicht vor.
     */
    private const LIKE_ESCAPE = '!';

    /**
     * Hoechstzahl der Postleitzahl-Praefixe, mit denen der Umkreis vorgewaehlt
     * wird. Jedes Praefix ist eine LIKE-Bedingung; irgendwann ist die Abfrage
     * teurer als die Vorauswahl wert ist -- dann faellt der Umkreis aus der
     * Vorauswahl heraus und nur noch der Matcher entscheidet ihn.
     */
    private const MAX_RADIUS_PREFIXES = 60;

    /**
     * Praefixlaengen fuer die Umkreis-Vorauswahl, von genau nach grob. Ein
     * kleiner Umkreis kommt mit dreistelligen Praefixen aus; ein grosser
     * faellt auf zweistellige zurueck, statt die Abfrage zu sprengen.
     */
    private const RADIUS_PREFIX_LENGTHS = [3, 2];

    /**
     * Die Leads, die dieser Kaeufer sehen darf -- bereits gegen sein Profil
     * geprueft.
     *
     * Der LeadMatcher ist eine reine Funktion und laeuft in PHP. Die Datenbank
     * schraenkt deshalb nur vor, was sie sicher entscheiden kann (Zustand,
     * Betreiber, Funnelauswahl, Mindestpunktzahl, Postleitzahl-Praefixe und
     * -- grob -- den Umkreis); die Entfernung selbst und die Antwortfilter
     * beurteilt danach der Matcher.
     *
     * Die Entfernung rechnet die Datenbank nicht nach: Sie steht in einer CSV
     * und nicht in einer Spalte, und ein Nachbau in SQL waere eine zweite
     * Wahrheit. Vorgewaehlt werden deshalb nur die Postleitzahl-Praefixe der
     * Zustellbereiche im Umkreis (siehe radiusPrefixes) -- eine Obermenge
     * dessen, was der Matcher durchlaesst.
     *
     * Die Vorauswahl darf nie mehr ausschliessen als der Matcher -- sonst
     * faehrt die Anzeige ein anderes Ergebnis als der Autokauf in FB-056,
     * und beim Kandidatendeckel unten fielen passende Leads stumm weg.
     *
     * @return Collection<int, Lead>
     */
    public function for(Tenant $buyer, ?BuyerProfile $profile, string $sort = self::SORT_NEWEST, bool $onlyWatchlisted = false): Collection
    {
        $profile ??= new BuyerProfile;

        $candidates = $this->candidates($buyer, $profile, $sort, $onlyWatchlisted)->get();

        return $candidates
            ->filter(static fn (Lead $lead): bool => LeadMatcher::matches(MatchableLead::fromLead($lead), $profile))
            ->values();
    }

    /**
     * Die Vorauswahl aus der Datenbank.
     *
     * @return Builder<Lead>
     */
    private function candidates(Tenant $buyer, BuyerProfile $profile, string $sort, bool $onlyWatchlisted): Builder
    {
        $query = Lead::query()
            // Der Kaeufer besitzt keinen dieser Leads -- ohne das Abschalten
            // des Mandanten-Scopes waere der Marktplatz immer leer.
            ->withoutGlobalScopes(TenantScopes::names())
            // Der Funnel gehoert dem Betreiber, gelesen wird im Kontext des
            // Kaeufers -- ohne das Abschalten des Scopes kaeme hier null heraus,
            // und der Marktplatz zeigte statt des Fragebogennamens einen
            // Platzhalter (FB-055a).
            ->with([
                'answers',
                'funnel' => static fn (Relation $funnel) => $funnel->withoutGlobalScopes(TenantScopes::names()),
            ])
            ->whereIn('lead_state', [LeadState::VERFUEGBAR->value, LeadState::RESERVIERT->value])
            ->whereNull('anonymized_at')
            ->whereHas('tenant', static fn (Builder $tenant) => $tenant->where('type', TenantType::OPERATOR->value));

        $funnelIds = $profile->funnelIds();

        if ($funnelIds !== []) {
            $query->whereIn('funnel_id', $funnelIds);
        }

        if ($profile->min_score !== null) {
            $query->where('score', '>=', $profile->min_score);
        }

        $this->restrictToPostalPrefixes($query, $profile->postalPrefixes());

        // Praefixe und Umkreis gelten zusammen (UND) -- genauso wertet sie der
        // Matcher aus, also zwei getrennte Bedingungen und keine verschmolzene.
        $this->restrictToPostalPrefixes($query, $this->radiusPrefixes($profile));

        if ($onlyWatchlisted) {
            $query->whereIn('id', $this->watchlistedLeadIds($buyer));
        }

        $this->applySort($query, $sort);

        return $query->limit((int) config('funnel.marketplace.listing.candidate_limit'));
    }

    /**
     * Verengt die Vorauswahl auf die Regionen des Profils.
     *
     * Die Postleitzahl steht nicht als Spalte am Lead, sondern als Antwort auf
     * den reservierten Feldschluessel `plz` -- eine JSON-Spalte. Verglichen wird
     * deshalb ueber JSON_UNQUOTE, und zwar auf dem getrimmten Wert, damit die
     * Bedingung dasselbe bedeutet wie `trim()` im MatchableLead.
     *
     * **Konservativ:** Diese Bedingung darf nur ausschliessen, was der Matcher
     * ohnehin ablehnen wuerde. Deshalb steht hier bewusst kein Nachbau der
     * Regel, sondern nur ihre Vorauswahl -- der Matcher bleibt die einzige
     * Instanz, die entscheidet. Ein Lead ohne `plz`-Antwort faellt in beiden
     * Faellen heraus, eine Mehrfachantwort ebenfalls (JSON_UNQUOTE liefert dann
     * den Listentext, der mit keiner Ziffernfolge beginnt -- und `is_scalar()`
     * im MatchableLead ergibt null).
     *
     * Ein Praefix mit LIKE-Platzhaltern wird maskiert. Das macht die Bedingung
     * genauer, nicht strenger: Der Matcher vergleicht mit `str_starts_with()`
     * ebenfalls buchstaeblich.
     *
     * @param  Builder<Lead>  $query
     * @param  list<string>  $prefixes
     */
    private function restrictToPostalPrefixes(Builder $query, array $prefixes): void
    {
        if ($prefixes === []) {
            return;
        }

        $query->whereHas('answers', function (Builder $answer) use ($prefixes): void {
            $answer->where('field_key', FunnelFieldKey::PLZ->value)
                ->where(function (Builder $matching) use ($prefixes): void {
                    foreach ($prefixes as $prefix) {
                        $matching->orWhereRaw(
                            'trim(json_unquote(`lead_answers`.`value`)) like ? escape ?',
                            [self::escapeLikeWildcards($prefix).'%', self::LIKE_ESCAPE],
                        );
                    }
                });
        });
    }

    /**
     * Maskiert die LIKE-Platzhalter eines Praefixes, damit er buchstaeblich
     * verglichen wird. Als Maskierzeichen dient bewusst nicht der Backslash:
     * er muesste durch PHP, den Query-Builder und MySQL geschleust werden und
     * verliert dabei je nach Ebene eine Verdopplung.
     */
    private static function escapeLikeWildcards(string $prefix): string
    {
        return str_replace(
            [self::LIKE_ESCAPE, '%', '_'],
            [self::LIKE_ESCAPE.self::LIKE_ESCAPE, self::LIKE_ESCAPE.'%', self::LIKE_ESCAPE.'_'],
            $prefix,
        );
    }

    /**
     * Die Postleitzahl-Praefixe, mit denen sich der Umkreis des Profils
     * vorwaehlen laesst -- leer, wenn er nicht einschraenkt.
     *
     * Ohne diese Vorauswahl bekaeme ein Kaeufer mit "25 km um 76133" und ohne
     * eigene Praefixe eine bundesweite Kandidatenliste, von der der Matcher
     * fast alles verwirft. Sobald mehr Leads vorliegen als der
     * Kandidatendeckel zulaesst, fielen passende Leads stumm weg, weil der
     * Deckel schon vor der Entfernungspruefung greift.
     *
     * **Obermenge, nicht Nachbau.** Aus dem Verzeichnis kommen die
     * Zustellbereiche im Umkreis; davon bleibt nur der Anfang der
     * Postleitzahl stehen. Jeder Lead, den der Matcher durchlaesst, liegt in
     * einem dieser Bereiche -- also auch unter einem dieser Praefixe. Die
     * Umkehrung gilt nicht, und genau das ist gewollt: Entschieden wird
     * weiterhin ausschliesslich im Matcher.
     *
     * Dieselben Vorbedingungen wie dort: `nationwide`, ein halb gefuelltes
     * Kriterium und ein unbekannter Mittelpunkt schraenken nicht ein.
     *
     * @return list<string>
     */
    private function radiusPrefixes(BuyerProfile $profile): array
    {
        if ($profile->region !== BuyerRegion::RADIUS) {
            return [];
        }

        $centre = trim((string) $profile->postal_code);
        $radiusKm = $profile->radius_km;

        if ($centre === '' || $radiusKm === null || $radiusKm <= 0) {
            return [];
        }

        $postalCodes = (new PlzDirectory)->postalCodesWithin($centre, (float) $radiusKm);

        if ($postalCodes === []) {
            return [];
        }

        foreach (self::RADIUS_PREFIX_LENGTHS as $length) {
            $prefixes = array_values(array_unique(array_map(
                static fn (string $postalCode): string => substr($postalCode, 0, $length),
                $postalCodes,
            )));

            if (count($prefixes) <= self::MAX_RADIUS_PREFIXES) {
                return $prefixes;
            }
        }

        return [];
    }

    /**
     * @param  Builder<Lead>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        if ($sort === self::SORT_SCORE) {
            $query->orderByDesc('score')->orderByDesc('created_at');

            return;
        }

        $query->orderByDesc('created_at');
    }

    /**
     * @return array<int, int>
     */
    private function watchlistedLeadIds(Tenant $buyer): array
    {
        return LeadWatchlistEntry::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->where('tenant_id', $buyer->getKey())
            ->pluck('lead_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}
