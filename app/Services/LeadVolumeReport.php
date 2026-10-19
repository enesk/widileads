<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lead;
use App\Models\Scopes\TenantScopes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Wie viele Leads sind in welchem Zeitraum eingegangen?
 *
 * Die Frage des Betreibers an seinen Bestand: Kommt mehr oder weniger als im
 * Vormonat? Gezaehlt wird der **Eingang** (`leads.created_at`) -- der Moment,
 * in dem der Endkunde die Strecke abgeschickt hat. Nicht `delivered_at`: Ein
 * Lead, der heute eingeht und morgen ausgeliefert wird, gehoert in den Monat
 * seines Eingangs, sonst verschiebt eine Auslieferungsverzoegerung die Zahlen
 * zwischen zwei Monaten.
 *
 * Gezaehlt wird jeder Lead, auch der als Dublette erkannte und der
 * ausgeschiedene: Die Frage lautet "wie viel kam herein", nicht "wie viel war
 * verkaeuflich". Wer letzteres wissen will, braucht eine eigene Auswertung
 * nach `lead_state` -- hier waere sie eine stille Annahme.
 *
 * Gruppiert wird in zwei Schritten: Die Datenbank zaehlt je Kalendertag, PHP
 * legt die Tage in die Toepfe. Das ist absichtlich so geteilt -- die Zaehlung
 * je Tag bleibt eine Zeile pro Tag statt einer pro Lead, und die
 * Topf-Beschriftung braucht keine datenbankabhaengigen Datumsfunktionen.
 *
 * **Das Portal ist `leads.embed_origin`**, auf den Hostnamen eingekuerzt: die
 * Seite, auf der der Endkunde den Funnel ausgefuellt hat
 * (elektrikerportal.com, pfotencheck.tierarztportal.com). Nicht der Funnel --
 * der ist unser Fragebogen und kann auf mehreren Portalen stehen -- und nicht
 * `utm_source`, die den Weg zum Portal beschreibt und meistens leer ist.
 * Unterdomains bleiben getrennt: `pfotencheck.tierarztportal.com` ist ein
 * eigener Auftritt und keine Variante von `tierarztportal.com`.
 */
class LeadVolumeReport
{
    /** Die Zeitraeume, nach denen gruppiert werden darf. */
    public const PERIODS = ['day', 'week', 'month', 'year'];

    /**
     * So viele Portale bekommen im gestapelten Diagramm einen eigenen Balken,
     * der Rest wird zu "Andere" zusammengefasst.
     *
     * Eine Lesbarkeitsgrenze, keine fachliche: Ab einem Dutzend Farben
     * erkennt niemand mehr, welcher Streifen zu welchem Portal gehoert. Wer
     * ein kleines Portal genau ansehen will, waehlt es im Filter aus -- dort
     * steht jedes, auch das kleinste.
     */
    public const MAX_STACKED_PORTALS = 8;

    /**
     * Eingegangene Leads je Zeitraum, von alt nach neu.
     *
     * Leere Zeitraeume stehen mit 0 darin und fallen nicht heraus: Ein Monat
     * ohne Leads ist die wichtigste Zahl der Reihe, und eine Luecke im
     * Diagramm sieht aus wie ein Monat, den es nicht gab.
     *
     * @param  string  $period  Einer aus PERIODS; alles andere wird zu 'month'.
     * @param  string|null  $portal  Hostname eines Portals; null heisst alle.
     * @return array<string, int> Beschriftung => Anzahl
     */
    public function perPeriod(string $period = 'month', ?Carbon $from = null, ?Carbon $until = null, ?string $portal = null): array
    {
        $period = $this->normalizePeriod($period);
        $from = $this->start($from);
        $until = $this->end($until);

        if ($from->greaterThan($until)) {
            return [];
        }

        $buckets = $this->emptyBuckets($period, $from, $until);

        foreach ($this->rowsPerDay($from, $until) as $row) {
            if ($portal !== null && $row['portal'] !== $portal) {
                continue;
            }

            $label = $this->labelFor($period, Carbon::parse($row['day']));

            // Der Topf steht schon: Ein Tag ausserhalb der leeren Reihe kann
            // es nicht geben, weil beide aus demselben Zeitraum kommen.
            $buckets[$label] = ($buckets[$label] ?? 0) + $row['total'];
        }

        return $buckets;
    }

    /**
     * Dieselbe Reihe, aber je Portal eine eigene -- die Grundlage des
     * gestapelten Diagramms.
     *
     * Die Reihen sind nach Menge sortiert, die groesste zuerst: So steht im
     * Diagramm oben, was den Ausschlag gibt, und in der Legende zuerst, was
     * man wissen will. Ueber MAX_STACKED_PORTALS hinaus werden die kleinen zu
     * einer Reihe "Andere" addiert, damit keine Zahl verschwindet.
     *
     * @return array{labels: list<string>, series: array<string, list<int>>}
     */
    public function perPortalAndPeriod(string $period = 'month', ?Carbon $from = null, ?Carbon $until = null): array
    {
        $period = $this->normalizePeriod($period);
        $from = $this->start($from);
        $until = $this->end($until);

        if ($from->greaterThan($until)) {
            return ['labels' => [], 'series' => []];
        }

        $empty = $this->emptyBuckets($period, $from, $until);
        $ranking = $this->perPortal($from, $until);
        $named = array_slice(array_keys($ranking), 0, self::MAX_STACKED_PORTALS);

        $series = [];

        foreach ($this->rowsPerDay($from, $until) as $row) {
            $portal = in_array($row['portal'], $named, strict: true)
                ? $row['portal']
                : __('reports.leads.other_portals');

            $label = $this->labelFor($period, Carbon::parse($row['day']));
            $series[$portal] ??= $empty;
            $series[$portal][$label] = ($series[$portal][$label] ?? 0) + $row['total'];
        }

        // Die Reihenfolge der Reihen ist die des Rankings; "Andere" haengt
        // hinten, weil es keine Menge ist, sondern ein Rest.
        $ordered = [];

        foreach ($named as $portal) {
            if (isset($series[$portal])) {
                $ordered[$portal] = array_values($series[$portal]);
            }
        }

        $other = __('reports.leads.other_portals');

        if (isset($series[$other])) {
            $ordered[$other] = array_values($series[$other]);
        }

        return ['labels' => array_keys($empty), 'series' => $ordered];
    }

    /**
     * Welches Portal hat im Zeitraum wie viele Leads gebracht? Absteigend,
     * das staerkste zuerst.
     *
     * @return array<string, int> Portal => Anzahl
     */
    public function perPortal(?Carbon $from = null, ?Carbon $until = null): array
    {
        $counts = [];

        foreach ($this->rowsPerDay($this->start($from), $this->end($until)) as $row) {
            $counts[$row['portal']] = ($counts[$row['portal']] ?? 0) + $row['total'];
        }

        arsort($counts);

        return $counts;
    }

    /**
     * Das Portal eines Leads, wie es in der Auswertung erscheint.
     *
     * Aus `https://pfotencheck.tierarztportal.com/anfrage?x=1` wird
     * `pfotencheck.tierarztportal.com`. Ein Lead ohne Herkunft -- aus dem
     * direkten Aufruf einer Funnel-Seite oder aus einer Einreichung ohne
     * Einbettung -- bekommt einen eigenen, benannten Topf statt stillschweigend
     * zu verschwinden.
     */
    public function portalOf(?string $embedOrigin): string
    {
        $origin = trim((string) $embedOrigin);

        if ($origin === '') {
            return __('reports.leads.unknown_portal');
        }

        // parse_url() braucht ein Schema, um den Host zu erkennen.
        $host = parse_url(str_contains($origin, '//') ? $origin : 'https://'.$origin, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : $origin;
    }

    /**
     * Eingegangene Leads im Zeitraum insgesamt -- die Summe unter dem
     * Diagramm.
     */
    public function total(?Carbon $from = null, ?Carbon $until = null, ?string $portal = null): int
    {
        if ($portal === null) {
            return $this->baseQuery($this->start($from), $this->end($until))->count();
        }

        return $this->perPortal($from, $until)[$portal] ?? 0;
    }

    /**
     * Anzahl je Kalendertag und Herkunft, gerechnet in der Datenbank.
     *
     * `date()` kennen MariaDB und SQLite gleich; alles darueber hinaus
     * (Wochen, Monatsnamen) macht PHP, weil die Datenbanken sich dort
     * unterscheiden und die Wochenzaehlung ohnehin von der Sprache abhaengt.
     *
     * Der Hostname entsteht ebenfalls in PHP: `embed_origin` ist eine volle
     * Adresse, und die in SQL zu zerlegen hiesse, dieselbe Regel in jeder
     * Datenbank nachzubauen. Die Zeilenzahl bleibt dabei klein -- ein Tag mal
     * eine Handvoll Portale.
     *
     * @return list<array{day: string, portal: string, total: int}>
     */
    private function rowsPerDay(Carbon $from, Carbon $until): array
    {
        return $this->baseQuery($from, $until)
            ->selectRaw('date(created_at) as day, embed_origin, count(*) as total')
            ->groupBy('day', 'embed_origin')
            ->orderBy('day')
            ->get()
            ->map(fn (Lead $row): array => [
                'day' => (string) $row->getAttribute('day'),
                'portal' => $this->portalOf($row->embed_origin),
                'total' => (int) $row->getAttribute('total'),
            ])
            ->all();
    }

    private function normalizePeriod(string $period): string
    {
        return in_array($period, self::PERIODS, strict: true) ? $period : 'month';
    }

    private function start(?Carbon $from): Carbon
    {
        return ($from ?? now()->subYear())->copy()->startOfDay();
    }

    private function end(?Carbon $until): Carbon
    {
        return ($until ?? now())->copy()->endOfDay();
    }

    /**
     * Die Leads im Zeitraum, ueber alle Mandanten.
     *
     * Ohne Mandanten-Scopes: Die Auswertung steht im Admin-Panel des
     * Betreibers, und dort ist kein Mandant gesetzt. Mit Scope zaehlte sie
     * entweder nichts oder -- schlimmer -- den zuletzt gesetzten Mandanten.
     *
     * @return Builder<Lead>
     */
    private function baseQuery(Carbon $from, Carbon $until): Builder
    {
        return Lead::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->whereBetween('created_at', [$from, $until]);
    }

    /**
     * Die leere Reihe: jeder Zeitraum zwischen Anfang und Ende, mit 0.
     *
     * @return array<string, int>
     */
    private function emptyBuckets(string $period, Carbon $from, Carbon $until): array
    {
        $buckets = [];
        $cursor = $this->startOfPeriod($period, $from);

        while ($cursor->lessThanOrEqualTo($until)) {
            $buckets[$this->labelFor($period, $cursor)] = 0;

            $cursor = match ($period) {
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                'year' => $cursor->addYear(),
                default => $cursor->addMonth(),
            };
        }

        return $buckets;
    }

    private function startOfPeriod(string $period, Carbon $date): Carbon
    {
        $start = $date->copy();

        return match ($period) {
            'day' => $start->startOfDay(),
            'week' => $start->startOfWeek(),
            'year' => $start->startOfYear(),
            default => $start->startOfMonth(),
        };
    }

    /**
     * Beschriftung eines Topfes. Sie ist gleichzeitig sein Schluessel, muss
     * also innerhalb der Reihe eindeutig sein -- deshalb traegt auch die
     * Woche ihr Jahr.
     */
    private function labelFor(string $period, Carbon $date): string
    {
        return match ($period) {
            'day' => $date->translatedFormat('d.m.Y'),
            'week' => __('reports.leads.week', ['week' => $date->isoFormat('WW'), 'year' => $date->isoWeekYear()]),
            'year' => $date->format('Y'),
            default => $date->translatedFormat('F Y'),
        };
    }
}
