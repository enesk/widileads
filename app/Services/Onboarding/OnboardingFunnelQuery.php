<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Constants\TenantType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * LP-ONBOARD-015: Die Zahlen hinter dem Kaeufer-Onboarding.
 *
 * Der Trichter beantwortet eine einzige Frage: Wo bleiben neue Kaeufer
 * haengen. Alles dafuer steht schon in der Datenbank -- `tenants.created_at`,
 * `users.email_verified_at`, `users.phone_number_verified_at` und die Haken in
 * `tenant_onboarding_steps`. Es gibt deshalb kein externes Analytics-Werkzeug
 * und keine Ereignisse im Browser; eine zweite Quelle wuerde nur eine zweite
 * Wahrheit erzeugen.
 *
 * Eine Abfrage je Aufruf: Die Schritte werden in der Datenbank zu Spalten
 * gedreht (max(case when step = ...)), nicht in PHP gruppiert. Das Ergebnis
 * liegt zehn Minuten im Cache -- der Trichter ist eine Beobachtung und keine
 * Abrechnung, und er wird mehrmals je Seitenaufbau gelesen (Balken, Kennzahlen,
 * CSV).
 *
 * Die Stufen werden bewusst **nicht** kumulativ gezaehlt: Gezaehlt wird, wer
 * eine Stufe erreicht hat, unabhaengig davon, ob die Stufen davor stehen. Ein
 * Bestandskunde mit nachgetragenen Haken (onboarding:backfill) wuerde sonst
 * aus dem ganzen Trichter verschwinden. Eine Quote kann dadurch ueber 100 %
 * liegen; das ist dann genau die Auffaelligkeit, die man sehen will.
 */
class OnboardingFunnelQuery
{
    /** Zehn Minuten, wie im Ticket gefordert. */
    private const CACHE_TTL_SECONDS = 600;

    /** Erlaubte Zeitraeume in Tagen. Alles andere faellt auf den ersten zurueck. */
    public const PERIODS = [7, 30, 90];

    /** Ab wann ein Workspace als haengend gilt. */
    public const STUCK_HOURS = 48;

    /** Das Fenster, in dem eine Registrierung als aktiviert gilt. */
    public const ACTIVATION_WINDOW_DAYS = 7;

    /** Hoechstens so viele Zeilen in "Haengt gerade". */
    private const STUCK_LIMIT = 100;

    /**
     * Die Stufen des Trichters in ihrer Reihenfolge.
     *
     * Die Reihenfolge ist die Fachaussage dieser Klasse: Sie entscheidet, auf
     * welcher Stufe ein Workspace "steht" und gegen welche Vorstufe eine Quote
     * gerechnet wird. Die ersten drei Stufen kommen aus `tenants` und `users`,
     * der Rest aus `tenant_onboarding_steps`.
     *
     * @var list<string>
     */
    public const STAGES = [
        'registered',
        'email_verified',
        'phone_verified',
        'industries_selected',
        'region_selected',
        'payment_setup',
        'first_topup',
        'first_purchase',
        'first_call',
    ];

    /**
     * Die Stufen, deren Zeitpunkt in `tenant_onboarding_steps` steht.
     *
     * `phone_verified` ist doppelt gefuehrt: Die Wahrheit liegt am Benutzer
     * (LP-ONBOARD-003), der Haken ist nur die Spiegelung. Gelesen wird der
     * Benutzer, und nur wenn dort nichts steht, der Haken -- so taucht auch
     * ein Bestandskunde auf, dessen Nummer nie ueber Twilio Verify lief.
     *
     * @var list<string>
     */
    private const STEP_STAGES = [
        'phone_verified',
        'industries_selected',
        'region_selected',
        'payment_setup',
        'first_topup',
        'first_purchase',
        'first_call',
    ];

    /**
     * Der Trichter fuer die letzten `$days` Tage, nach Registrierungsdatum.
     *
     * @return array{
     *     days: int,
     *     since: Carbon,
     *     registered: int,
     *     stages: list<array{key: string, count: int, percent_of_previous: float, percent_of_total: float}>,
     *     activated_in_window: int,
     *     activation_rate: float,
     *     activation_window_open: int,
     *     median_hours_to_first_purchase: float|null,
     *     purchased: int
     * }
     */
    public function funnel(int $days): array
    {
        $days = $this->normalizePeriod($days);

        return Cache::remember(
            'onboarding.funnel.'.$days,
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->buildFunnel($days),
        );
    }

    /**
     * Workspaces, die seit mehr als `$hours` Stunden auf derselben Stufe
     * stehen -- ohne Zeitraum-Filter, denn genau die alten Faelle sind
     * gemeint.
     *
     * @return list<array{
     *     tenant_id: int,
     *     tenant_name: string,
     *     user_id: int|null,
     *     contact_email: string|null,
     *     stage: string,
     *     next_stage: string,
     *     since: Carbon,
     *     hours: int
     * }>
     */
    public function stuck(int $hours = self::STUCK_HOURS): array
    {
        return Cache::remember(
            'onboarding.funnel.stuck.'.$hours,
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->buildStuck($hours),
        );
    }

    /**
     * Wirft die gemerkten Zahlen weg. Fuer den Fall, dass jemand nach einer
     * Datenkorrektur nicht zehn Minuten warten will.
     */
    public function forget(): void
    {
        foreach (self::PERIODS as $days) {
            Cache::forget('onboarding.funnel.'.$days);
        }

        Cache::forget('onboarding.funnel.stuck.'.self::STUCK_HOURS);
    }

    /**
     * Der naechste erlaubte Zeitraum -- ein unbekannter Wert aus der Adresse
     * soll keine Abfrage ueber alle Jahre ausloesen.
     */
    public function normalizePeriod(int $days): int
    {
        return in_array($days, self::PERIODS, strict: true) ? $days : self::PERIODS[0];
    }

    /**
     * @return array{
     *     days: int,
     *     since: Carbon,
     *     registered: int,
     *     stages: list<array{key: string, count: int, percent_of_previous: float, percent_of_total: float}>,
     *     activated_in_window: int,
     *     activation_rate: float,
     *     activation_window_open: int,
     *     median_hours_to_first_purchase: float|null,
     *     purchased: int
     * }
     */
    private function buildFunnel(int $days): array
    {
        $since = Carbon::now()->subDays($days);
        $rows = $this->rows($since);

        $counts = array_fill_keys(self::STAGES, 0);
        $windowHours = self::ACTIVATION_WINDOW_DAYS * 24;
        $activated = 0;
        $windowOpen = 0;

        /** @var list<float> $durations */
        $durations = [];

        foreach ($rows as $row) {
            $times = $this->stageTimes($row);

            foreach (self::STAGES as $stage) {
                if ($times[$stage] instanceof Carbon) {
                    $counts[$stage]++;
                }
            }

            $registeredAt = $times['registered'];
            $purchasedAt = $times['first_purchase'];

            if (! $registeredAt instanceof Carbon) {
                continue;
            }

            if ($purchasedAt instanceof Carbon) {
                // Negative Abstaende sind Datenmuell (nachgetragener Haken vor
                // der Registrierung) und wuerden den Median verziehen.
                $hours = max(0.0, $registeredAt->diffInHours($purchasedAt, absolute: false));
                $durations[] = $hours;

                if ($hours <= $windowHours) {
                    $activated++;
                }

                continue;
            }

            // Wer noch keine sieben Tage alt ist, hat sein Fenster noch offen.
            // Ohne diese Zahl liest man die Quote zu pessimistisch.
            if ($registeredAt->copy()->addDays(self::ACTIVATION_WINDOW_DAYS)->isFuture()) {
                $windowOpen++;
            }
        }

        $registered = $counts['registered'];
        $stages = [];
        $previous = null;

        foreach (self::STAGES as $stage) {
            $count = $counts[$stage];

            $stages[] = [
                'key' => $stage,
                'count' => $count,
                'percent_of_previous' => $previous === null || $previous === 0
                    ? ($count > 0 ? 100.0 : 0.0)
                    : round($count / $previous * 100, 1),
                'percent_of_total' => $registered === 0 ? 0.0 : round($count / $registered * 100, 1),
            ];

            $previous = $count;
        }

        return [
            'days' => $days,
            'since' => $since,
            'registered' => $registered,
            'stages' => $stages,
            'activated_in_window' => $activated,
            'activation_rate' => $registered === 0 ? 0.0 : round($activated / $registered * 100, 1),
            'activation_window_open' => $windowOpen,
            'median_hours_to_first_purchase' => $this->median($durations),
            'purchased' => count($durations),
        ];
    }

    /**
     * @return list<array{
     *     tenant_id: int,
     *     tenant_name: string,
     *     user_id: int|null,
     *     contact_email: string|null,
     *     stage: string,
     *     next_stage: string,
     *     since: Carbon,
     *     hours: int
     * }>
     */
    private function buildStuck(int $hours): array
    {
        $now = Carbon::now();
        $stuck = [];

        foreach ($this->rows(null) as $row) {
            $times = $this->stageTimes($row);

            $reachedAt = null;
            $stage = null;
            $nextStage = null;

            // Die Stufe, auf der der Workspace steht, ist das Ende der
            // zusammenhaengenden Kette vom Anfang -- nicht die hoechste
            // erreichte. Sonst stuende ein Kaeufer ohne bestaetigte E-Mail
            // weiter vorn, als er wartet.
            foreach (self::STAGES as $candidate) {
                $time = $times[$candidate];

                if (! $time instanceof Carbon) {
                    $nextStage = $candidate;
                    break;
                }

                $stage = $candidate;
                $reachedAt = $reachedAt === null || $time->greaterThan($reachedAt) ? $time : $reachedAt;
            }

            if ($nextStage === null || $stage === null || ! $reachedAt instanceof Carbon) {
                // Durch (oder ohne Registrierungsdatum) -- nichts zu melden.
                continue;
            }

            $waiting = (int) $reachedAt->diffInHours($now, absolute: true);

            if ($waiting <= $hours) {
                continue;
            }

            $stuck[] = [
                'tenant_id' => (int) $row->id,
                'tenant_name' => (string) $row->name,
                'user_id' => $row->user_id === null ? null : (int) $row->user_id,
                'contact_email' => $row->contact_email === null ? null : (string) $row->contact_email,
                'stage' => $stage,
                'next_stage' => $nextStage,
                'since' => $reachedAt,
                'hours' => $waiting,
            ];
        }

        usort($stuck, static fn (array $a, array $b): int => $b['hours'] <=> $a['hours']);

        return array_slice($stuck, 0, self::STUCK_LIMIT);
    }

    /**
     * Eine Zeile je Kaeufer-Workspace, mit allen Stufen-Zeitpunkten als
     * Spalten.
     *
     * Der Kontakt ist der Nutzer aus `tenants.created_by` -- der, der den
     * Workspace angelegt hat. Ein zweiter Kollege im selben Workspace hat eine
     * eigene Rufnummer, aber nicht die Zustaendigkeit fuer das Onboarding.
     *
     * @return Collection<int, \stdClass>
     */
    private function rows(?Carbon $since): Collection
    {
        $query = DB::table('tenants as t')
            ->leftJoin('users as u', 'u.id', '=', 't.created_by')
            ->leftJoin('tenant_onboarding_steps as s', 's.tenant_id', '=', 't.id')
            ->where('t.type', TenantType::BUYER->value)
            ->groupBy('t.id', 't.name', 't.created_at', 'u.id', 'u.email', 'u.email_verified_at', 'u.phone_number_verified_at')
            ->select([
                't.id',
                't.name',
                't.created_at',
                'u.id as user_id',
                'u.email as contact_email',
                'u.email_verified_at',
                'u.phone_number_verified_at',
            ]);

        foreach (self::STEP_STAGES as $stage) {
            // Der Spaltenname kommt aus der Konstante und nicht von aussen;
            // der Vergleichswert ist trotzdem gebunden.
            $query->selectRaw(
                'max(case when s.step = ? then s.completed_at end) as step_'.$stage.'_at',
                [$stage],
            );
        }

        if ($since instanceof Carbon) {
            $query->where('t.created_at', '>=', $since);
        }

        return $query->get();
    }

    /**
     * Die Zeitpunkte einer Zeile je Stufe; null heisst: nicht erreicht.
     *
     * @return array<string, Carbon|null>
     */
    private function stageTimes(\stdClass $row): array
    {
        $times = [
            'registered' => $this->toCarbon($row->created_at),
            'email_verified' => $this->toCarbon($row->email_verified_at),
            'phone_verified' => $this->toCarbon($row->phone_number_verified_at)
                ?? $this->toCarbon($row->step_phone_verified_at),
        ];

        foreach (self::STEP_STAGES as $stage) {
            if ($stage === 'phone_verified') {
                continue;
            }

            $times[$stage] = $this->toCarbon($row->{'step_'.$stage.'_at'});
        }

        return $times;
    }

    private function toCarbon(mixed $value): ?Carbon
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        return Carbon::parse((string) $value);
    }

    /**
     * @param  list<float>  $values
     */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? round($values[$middle], 1)
            : round(($values[$middle - 1] + $values[$middle]) / 2, 1);
    }
}
