<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Constants\CallAttemptOutcome;
use App\Constants\PurchaseStatus;
use App\Constants\WalletTransactionType;
use App\Funnel\Snapshots\SnapshotLabels;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Marketplace\MarketplaceListing;
use App\Models\BuyerProfile;
use App\Models\CallAttempt;
use App\Models\CallerId;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Presenters\LeadPresenter;
use App\Services\CallerIdService;
use App\Services\CallNotPossible;
use App\Services\CallService;
use App\Services\LeadPurchaseAction;
use App\Services\Twilio\OutboundCallFailed;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Das Dashboard des Kaeufers (Portal Phase 1).
 *
 * Gebaut nach einer Frage: Was ist jetzt zu tun? Deshalb steht der
 * Handlungsbedarf oben (ablaufende Fristen, neue passende Leads) und die
 * Statistik unten.
 *
 * **Diese Komponente entscheidet nichts.** Welche Leads passen, beantwortet
 * dieselbe Abfrage wie der Marktplatz; ob ein Anruf zulaessig ist, prueft der
 * CallService. Die Zahlen sind Auskunft, keine zweite Buchfuehrung: Geld kommt
 * aus dem Wallet und den Kaufbelegen, nicht aus einer eigenen Rechnung.
 */
#[Layout('components.layouts.portal-app')]
class Dashboard extends Component
{
    use InteractsWithPortalTenant;

    /** So viele Leads zeigt die Vorschau aus dem Marktplatz. */
    private const SUGGESTIONS = 3;

    /** Zeitraum der Statistik in Tagen. */
    private const WINDOW_DAYS = 30;

    public ?string $notice = null;

    public function booted(): void
    {
        abort_unless(Gate::allows('marketplace.access', $this->portalTenant()), 403);
    }

    public function render(): View
    {
        $tenant = $this->portalTenant();
        $wallet = $tenant->wallet;
        $open = $this->openPurchases();
        $matching = $this->matchingLeads();
        $stats = $this->stats();

        $dueToday = $open->filter(fn (LeadPurchase $purchase): bool => $this->isDueToday($purchase))->count();
        $freshLeads = $matching->filter(
            static fn (Lead $lead): bool => $lead->created_at !== null && $lead->created_at->gt(now()->subDay()),
        )->count();

        return view('livewire.portal.dashboard', [
            'today' => now()->translatedFormat('l, j. F Y'),
            'greeting' => $this->greeting(),
            'summary' => trans_choice('portal.dashboard.summary', $open->count(), [
                'calls' => $open->count(),
                'leads' => $freshLeads,
            ]),
            'balance' => Money::format($wallet?->available_cents ?? 0),
            'reserved' => Money::format($wallet?->reserved_cents ?? 0),
            'matchCount' => $matching->count(),
            'freshCount' => $freshLeads,
            'openCalls' => $open->count(),
            'dueToday' => $dueToday,
            'stats' => $stats,
            'deadlines' => $this->deadlineRows($open),
            'suggestions' => $this->suggestionCards($matching),
            'criteria' => $this->criteriaLines(),
            'activity' => $this->activity(),
            'callerId' => $this->callerId(),
            'urls' => [
                'wallet' => route('portal.wallet', ['tenant' => $tenant->uuid]),
                'marketplace' => route('portal.marketplace', ['tenant' => $tenant->uuid]),
                'leads' => route('portal.leads', ['tenant' => $tenant->uuid]),
                'criteria' => route('portal.buying-criteria', ['tenant' => $tenant->uuid]),
                'callerId' => route('portal.caller-id', ['tenant' => $tenant->uuid]),
                'transactions' => route('portal.transactions', ['tenant' => $tenant->uuid]),
            ],
        ]);
    }

    /**
     * Startet den Anruf zu einem Lead mit ablaufender Frist.
     *
     * Der Name darf NICHT `call` lauten: `$wire.call(methode)` ist die
     * eingebaute Livewire-Schnittstelle im Browser.
     */
    public function startCall(int $purchaseId, CallService $calls): void
    {
        $this->notice = null;

        $user = $this->portalUser();
        $purchase = LeadPurchase::query()
            ->ofBuyer($this->portalTenant())
            ->whereKey($purchaseId)
            ->first();

        if (! $user instanceof User || ! $purchase instanceof LeadPurchase) {
            return;
        }

        try {
            $calls->start($purchase, $user, $this->portalTenant());
        } catch (CallNotPossible $exception) {
            $this->notice = $exception->translated();
        } catch (OutboundCallFailed $exception) {
            // Die Twilio-Meldung nennt Kontodetails und gehoert ins Log.
            logger()->error('Twilio konnte den Anruf nicht starten.', [
                'message' => $exception->getMessage(),
            ]);

            $this->notice = __('call.attempt.errors.provider_failed');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Handlungsbedarf
    |--------------------------------------------------------------------------
    */

    /**
     * Kaeufe, bei denen das Geld noch reserviert ist und der Lead offen --
     * also genau die, bei denen ein Anruf noch etwas aendert.
     *
     * @return Collection<int, LeadPurchase>
     */
    private function openPurchases(): Collection
    {
        return LeadPurchase::query()
            ->with(['lead', 'callAttempts'])
            ->ofBuyer($this->portalTenant())
            ->where('status', PurchaseStatus::RESERVED)
            ->get()
            ->filter(static fn (LeadPurchase $purchase): bool => $purchase->lead?->isOpen() ?? false)
            ->sortBy(static fn (LeadPurchase $purchase): string => $purchase->lead?->deadline_at?->toIso8601String() ?? '9999')
            ->values();
    }

    /**
     * @param  Collection<int, LeadPurchase>  $open
     * @return list<array{id: int, name: string, url: string, deadline: string, urgent: bool, meta: string}>
     */
    private function deadlineRows(Collection $open): array
    {
        return $open->take(5)->map(function (LeadPurchase $purchase): array {
            $lead = $purchase->lead;
            $presenter = new LeadPresenter($lead, $this->portalUser());
            $deadline = $lead?->deadline_at?->timezone(config('app.timezone'));
            $failed = $purchase->callAttempts->where('outcome', CallAttemptOutcome::FAILED_VALID)->count();

            return [
                'id' => (int) $purchase->getKey(),
                'name' => $presenter->name(),
                'url' => route('portal.leads.show', [
                    'tenant' => $this->portalTenant()->uuid,
                    'purchase' => $purchase->getKey(),
                ]),
                'deadline' => $this->deadlineText($deadline),
                'urgent' => $this->isDueToday($purchase),
                'meta' => __('portal.dashboard.deadlines.meta', [
                    'region' => $presenter->postalCode(),
                    'done' => $failed,
                    'total' => (int) config('lead_calls.unreachable_attempts'),
                ]),
            ];
        })->all();
    }

    private function deadlineText(?Carbon $deadline): string
    {
        if ($deadline === null) {
            return __('portal.dashboard.deadlines.none');
        }

        if ($deadline->isPast()) {
            return __('portal.dashboard.deadlines.over');
        }

        if ($deadline->isToday()) {
            return __('portal.dashboard.deadlines.today', ['time' => $deadline->format('H:i')]);
        }

        $days = (int) ceil(now()->diffInDays($deadline, absolute: true)) ?: 1;

        return trans_choice('portal.dashboard.deadlines.in_days', $days, ['count' => $days]);
    }

    private function isDueToday(LeadPurchase $purchase): bool
    {
        $deadline = $purchase->lead?->deadline_at;

        return $deadline !== null && $deadline->isToday();
    }

    /*
    |--------------------------------------------------------------------------
    | Neue Leads
    |--------------------------------------------------------------------------
    */

    /**
     * Die passenden Leads aus dem Marktplatz -- dieselbe Abfrage wie dort, mit
     * demselben Profil. Eine zweite Zaehlweise waere die Stelle, an der
     * Dashboard und Marktplatz auseinanderlaufen.
     *
     * @return Collection<int, Lead>
     */
    private function matchingLeads(): Collection
    {
        return app(MarketplaceListing::class)->for($this->portalTenant(), $this->profile());
    }

    /**
     * @param  Collection<int, Lead>  $matching
     * @return list<array{name: string, age: string, region: string, price: string, url: string, attributes: array<string, string>}>
     */
    private function suggestionCards(Collection $matching): array
    {
        $marketplace = route('portal.marketplace', ['tenant' => $this->portalTenant()->uuid]);

        // Der Preis kommt aus derselben Zusage wie im Marktplatz und beim Kauf
        // -- eine eigene Rechnung hier waere die Stelle, an der die Seite einen
        // anderen Preis nennt als der Kauf abbucht.
        $purchase = app(LeadPurchaseAction::class);

        return $matching->take(self::SUGGESTIONS)->map(function (Lead $lead) use ($marketplace, $purchase): array {
            $presenter = new LeadPresenter($lead, $this->portalUser());

            return [
                'name' => $presenter->name(),
                'age' => $this->relativeTime($lead->created_at),
                'region' => $presenter->postalCode(),
                'price' => Money::format($purchase->priceCentsOf($lead)),
                'url' => $marketplace,
                'attributes' => $this->attributes($lead),
            ];
        })->values()->all();
    }

    /**
     * Die ersten Merkmale eines Leads, ohne Kontaktfelder.
     *
     * @return array<string, string>
     */
    private function attributes(Lead $lead): array
    {
        // Beschriftungen aus der Fassung, unter der der Lead entstanden ist --
        // ohne sie stuende "halter_alter" statt "Alter des Halters" auf der
        // Karte.
        $snapshot = $lead->funnelVersion?->snapshot;
        $snapshot = is_array($snapshot) ? $snapshot : null;
        $questions = SnapshotLabels::questions($snapshot);
        $options = SnapshotLabels::options($snapshot);

        $labels = [];

        foreach ($lead->answers as $answer) {
            if ($answer->isPersonal() || count($labels) >= 3) {
                continue;
            }

            $value = $answer->value;

            if (! is_scalar($value)) {
                continue;
            }

            $label = $questions[$answer->field_key] ?? $answer->field_key;
            $labels[$label] = $options[$answer->field_key][(string) $value] ?? (string) $value;
        }

        return $labels;
    }

    /*
    |--------------------------------------------------------------------------
    | Statistik
    |--------------------------------------------------------------------------
    */

    /**
     * Die letzten 30 Tage: gekauft, berechnet, freigegeben, Erreichbarkeit.
     *
     * @return array{from: string, to: string, bought: int, captured_cents: int, captured: int, released_cents: int, released: int, rate: int, average: int}
     */
    private function stats(): array
    {
        $from = now()->subDays(self::WINDOW_DAYS);

        $purchases = LeadPurchase::query()
            ->ofBuyer($this->portalTenant())
            ->where('purchased_at', '>=', $from)
            ->get(['status', 'price_cents']);

        $captured = $purchases->where('status', PurchaseStatus::CAPTURED);
        $released = $purchases->where('status', PurchaseStatus::RELEASED);
        $decided = $captured->count() + $released->count();

        return [
            'from' => $from->format('d.m.'),
            'to' => now()->format('d.m.'),
            'bought' => $purchases->count(),
            'captured' => $captured->count(),
            'captured_cents' => (int) $captured->sum('price_cents'),
            'released' => $released->count(),
            'released_cents' => (int) $released->sum('price_cents'),
            'rate' => $decided === 0 ? 0 : (int) round($captured->count() / $decided * 100),
            'average' => $this->averageRate($from),
        ];
    }

    /**
     * Die Erreichbarkeitsquote aller Kaeufer im selben Zeitraum.
     *
     * Bewusst ueber alle Mandanten und bewusst nur als Quote: Was hier
     * herauskommt, ist eine Zahl ohne Bezug zu einem einzelnen Kaeufer.
     */
    private function averageRate(Carbon $from): int
    {
        $counts = LeadPurchase::query()
            ->withoutGlobalScopes()
            ->where('purchased_at', '>=', $from)
            ->whereIn('status', [PurchaseStatus::CAPTURED->value, PurchaseStatus::RELEASED->value])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $captured = (int) ($counts[PurchaseStatus::CAPTURED->value] ?? 0);
        $released = (int) ($counts[PurchaseStatus::RELEASED->value] ?? 0);

        return $captured + $released === 0 ? 0 : (int) round($captured / ($captured + $released) * 100);
    }

    /*
    |--------------------------------------------------------------------------
    | Spalte rechts
    |--------------------------------------------------------------------------
    */

    /**
     * @return list<array{text: string, active: bool}>
     */
    private function criteriaLines(): array
    {
        $profile = $this->profile();
        $prefixes = $profile->postalPrefixes();
        $filters = $profile->answerFilters();

        $lines = [[
            'text' => $prefixes === []
                ? __('marketplace.profile.portal.match.all_regions')
                : __('marketplace.profile.portal.match.prefixes', ['list' => implode(', ', $prefixes)]),
            'active' => $prefixes !== [],
        ]];

        if ($filters !== []) {
            $lines[] = [
                'text' => implode(' · ', array_map(
                    static fn (string $field, array $values): string => $field.' '.implode(', ', $values),
                    array_keys($filters),
                    $filters,
                )),
                'active' => true,
            ];
        }

        $lines[] = [
            'text' => $profile->auto_buy
                ? __('marketplace.profile.portal.match.auto_on')
                : __('marketplace.profile.portal.match.auto_off'),
            'active' => (bool) $profile->auto_buy,
        ];

        return $lines;
    }

    /**
     * Was zuletzt passiert ist: Geldbewegungen und Anrufe in einer Spur.
     *
     * @return list<array{text: string, when: string, tone: string, icon: string}>
     */
    private function activity(): array
    {
        $wallet = $this->portalTenant()->wallet;

        $entries = [];

        if ($wallet !== null) {
            foreach (WalletTransaction::query()->where('wallet_id', $wallet->getKey())->latest('created_at')->limit(5)->get() as $transaction) {
                $entries[] = [
                    'at' => $transaction->created_at,
                    'text' => __('portal.dashboard.activity.'.$transaction->type->value, [
                        'amount' => Money::format(abs((int) $transaction->amount_cents)),
                    ]),
                    'tone' => match ($transaction->type) {
                        WalletTransactionType::RELEASE, WalletTransactionType::REFUND => 'emerald',
                        WalletTransactionType::TOPUP, WalletTransactionType::RESERVE => 'brand',
                        default => 'zinc',
                    },
                    'icon' => $transaction->type === WalletTransactionType::RESERVE ? 'cart' : 'wallet',
                ];
            }
        }

        $attempts = CallAttempt::query()
            ->withoutGlobalScopes()
            ->whereIn('lead_purchase_id', LeadPurchase::query()->ofBuyer($this->portalTenant())->select('id'))
            ->latest('started_at')
            ->limit(5)
            ->get();

        foreach ($attempts as $attempt) {
            $entries[] = [
                'at' => $attempt->started_at,
                'text' => __('portal.dashboard.activity.call'),
                'tone' => 'zinc',
                'icon' => 'phone',
            ];
        }

        usort($entries, static fn (array $a, array $b): int => ($b['at']?->timestamp ?? 0) <=> ($a['at']?->timestamp ?? 0));

        return array_map(fn (array $entry): array => [
            'text' => $entry['text'],
            'when' => $this->relativeTime($entry['at']),
            'tone' => $entry['tone'],
            'icon' => $entry['icon'],
        ], array_slice($entries, 0, 5));
    }

    /**
     * @return array{label: string, number: string}|null
     */
    private function callerId(): ?array
    {
        $user = $this->portalUser();

        if (! $user instanceof User) {
            return null;
        }

        $callerId = app(CallerIdService::class)->forUser($user);

        if (! $callerId instanceof CallerId || ! $callerId->isUsableAsCallerId()) {
            return null;
        }

        return [
            'label' => (string) ($callerId->label ?? __('call.caller_id.portal.default_label')),
            'number' => (string) $callerId->phone_number,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Innereien
    |--------------------------------------------------------------------------
    */

    private function profile(): BuyerProfile
    {
        return BuyerProfile::query()->firstOrNew(['tenant_id' => $this->portalTenant()->getKey()]);
    }

    private function greeting(): string
    {
        $hour = (int) now()->format('G');

        $key = match (true) {
            $hour < 11 => 'morning',
            $hour < 18 => 'day',
            default => 'evening',
        };

        return __('portal.dashboard.greeting.'.$key, ['name' => $this->portalUser()?->name ?? '']);
    }

    private function relativeTime(?Carbon $moment): string
    {
        if ($moment === null) {
            return '-';
        }

        $moment = $moment->timezone(config('app.timezone'));

        return match (true) {
            $moment->isToday() => __('portal.dashboard.when.today', ['time' => $moment->format('H:i')]),
            $moment->isYesterday() => __('portal.dashboard.when.yesterday', ['time' => $moment->format('H:i')]),
            default => __('portal.dashboard.when.on', ['date' => $moment->format('d.m.'), 'time' => $moment->format('H:i')]),
        };
    }
}
