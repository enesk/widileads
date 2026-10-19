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
 */
class LeadVolumeReport
{
    /** Die Zeitraeume, nach denen gruppiert werden darf. */
    public const PERIODS = ['day', 'week', 'month', 'year'];

    /**
     * Eingegangene Leads je Zeitraum, von alt nach neu.
     *
     * Leere Zeitraeume stehen mit 0 darin und fallen nicht heraus: Ein Monat
     * ohne Leads ist die wichtigste Zahl der Reihe, und eine Luecke im
     * Diagramm sieht aus wie ein Monat, den es nicht gab.
     *
     * @param  string  $period  Einer aus PERIODS; alles andere wird zu 'month'.
     * @return array<string, int> Beschriftung => Anzahl
     */
    public function perPeriod(string $period = 'month', ?Carbon $from = null, ?Carbon $until = null): array
    {
        $period = in_array($period, self::PERIODS, strict: true) ? $period : 'month';
        $from = ($from ?? now()->subYear())->copy()->startOfDay();
        $until = ($until ?? now())->copy()->endOfDay();

        if ($from->greaterThan($until)) {
            return [];
        }

        $buckets = $this->emptyBuckets($period, $from, $until);

        foreach ($this->countsPerDay($from, $until) as $day => $count) {
            $label = $this->labelFor($period, Carbon::parse($day));

            // Der Topf steht schon: Ein Tag ausserhalb der leeren Reihe kann
            // es nicht geben, weil beide aus demselben Zeitraum kommen.
            $buckets[$label] = ($buckets[$label] ?? 0) + $count;
        }

        return $buckets;
    }

    /**
     * Eingegangene Leads im Zeitraum insgesamt -- die Summe unter dem
     * Diagramm.
     */
    public function total(?Carbon $from = null, ?Carbon $until = null): int
    {
        return $this->baseQuery(
            ($from ?? now()->subYear())->copy()->startOfDay(),
            ($until ?? now())->copy()->endOfDay(),
        )->count();
    }

    /**
     * Anzahl je Kalendertag, gerechnet in der Datenbank.
     *
     * `date()` kennen MariaDB und SQLite gleich; alles darueber hinaus
     * (Wochen, Monatsnamen) macht PHP, weil die Datenbanken sich dort
     * unterscheiden und die Wochenzaehlung ohnehin von der Sprache abhaengt.
     *
     * @return array<string, int> Y-m-d => Anzahl
     */
    private function countsPerDay(Carbon $from, Carbon $until): array
    {
        /** @var array<string, int> $rows */
        $rows = $this->baseQuery($from, $until)
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        return $rows;
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
