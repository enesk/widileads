<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Constants\OrderStatus;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\InvoiceService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "Bestellungen" im Portal (Entwurf `bestellungen.html`).
 *
 * Loest die Filament-Ressource App\Filament\Dashboard\Resources\Orders ab. Drei
 * Unterschiede, und keiner davon ist Gestaltung:
 *
 * 1. **Oben stehen drei Zahlen.** Die Tabelle beantwortete die Frage "wie viel
 *    habe ich dieses Jahr aufgeladen" nur, wenn jemand die Spalte addiert.
 * 2. **Die Zustaende heissen deutsch.** "All / Success / Pending / Failed"
 *    waren die Vorgaben des Starterkits.
 * 3. **Gruppiert nach Monat mit Monatssumme.** Eine flache Liste aus zwanzig
 *    Zeilen hat keine Gliederung, und Aufladungen denkt man in Monaten.
 *
 * **Diese Komponente bucht nichts.** Sie liest Bestellungen des angemeldeten
 * Benutzers im aktiven Workspace und formatiert sie. Der Knopf je Zeile fuehrt
 * an vorhandene Wege: Rechnung an den InvoiceController, offene und
 * fehlgeschlagene Zahlungen an die Aufladeseite.
 */
#[Layout('components.layouts.portal-app')]
class Orders extends Component
{
    use InteractsWithPortalTenant;

    public const TAB_ALL = 'all';

    public const TAB_PAID = 'paid';

    public const TAB_PENDING = 'pending';

    public const TAB_FAILED = 'failed';

    /** So viele Zeilen stehen zuerst da, so viele kommen je Klick dazu. */
    public const PER_PAGE = 12;

    #[Url(as: 'status', except: self::TAB_ALL)]
    public string $tab = self::TAB_ALL;

    #[Url(as: 'suche', except: '')]
    public string $search = '';

    /** Jahr als Text, leer heisst alle. */
    #[Url(as: 'jahr', except: '')]
    public string $year = '';

    #[Url(as: 'anzahl', except: self::PER_PAGE)]
    public int $visible = self::PER_PAGE;

    public function booted(): void
    {
        abort_unless(Gate::allows('marketplace.access', $this->portalTenant()), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['tab', 'search', 'year'], true)) {
            $this->visible = self::PER_PAGE;
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, $this->tabLabels()) ? $tab : self::TAB_ALL;
        $this->visible = self::PER_PAGE;
    }

    public function loadMore(): void
    {
        $this->visible += self::PER_PAGE;
    }

    public function render(): View
    {
        $matched = $this->matchedOrders();
        $total = $matched->count();
        $visible = max(self::PER_PAGE, $this->visible);
        $shown = $matched->slice(0, $visible)->values();

        return view('livewire.portal.orders', [
            'stats' => $this->stats(),
            'tabs' => $this->tabs(),
            'years' => $this->years(),
            'groups' => $this->groups($shown, $matched),
            'shownCount' => $shown->count(),
            'total' => $total,
            'remaining' => max(0, $total - $shown->count()),
            'nextBatch' => min(self::PER_PAGE, max(0, $total - $shown->count())),
            'topUpUrl' => route('portal.wallet', ['tenant' => $this->portalTenant()->uuid]),
            'walletUrl' => route('portal.transactions', ['tenant' => $this->portalTenant()->uuid]),
        ])->title(__('marketplace.orders.heading'));
    }

    /**
     * Die drei Zahlen oben.
     *
     * "Dieses Jahr" zaehlt nur bezahlte Bestellungen: Eine offene Zahlung ist
     * kein aufgeladenes Guthaben, und die Zahl soll zum Guthaben passen.
     *
     * @return array{year: string, count: int, pending: string, hasPending: bool}
     */
    private function stats(): array
    {
        $orders = $this->baseQuery()->get();
        $thisYear = $orders->filter(
            static fn (Order $order): bool => $order->created_at?->year === now()->year,
        );

        $pendingCents = (int) $orders
            ->filter(fn (Order $order): bool => $this->tabOf($order) === self::TAB_PENDING)
            ->sum(static fn (Order $order): int => (int) $order->total_amount_after_discount);

        return [
            'year' => Money::format((int) $thisYear
                ->filter(fn (Order $order): bool => $this->tabOf($order) === self::TAB_PAID)
                ->sum(static fn (Order $order): int => (int) $order->total_amount_after_discount)),
            'count' => $orders->count(),
            'pending' => Money::format($pendingCents),
            'hasPending' => $pendingCents > 0,
        ];
    }

    /**
     * Die Reiter samt Zaehlern. Gezaehlt wird ueber Suche und Jahr, aber nicht
     * ueber den eigenen Reiter.
     *
     * @return list<array{key: string, label: string, count: int, active: bool}>
     */
    private function tabs(): array
    {
        $orders = $this->searchedOrders();

        return array_map(function (string $key, string $label) use ($orders): array {
            return [
                'key' => $key,
                'label' => $label,
                'count' => $key === self::TAB_ALL
                    ? $orders->count()
                    : $orders->filter(fn (Order $order): bool => $this->tabOf($order) === $key)->count(),
                'active' => $this->tab === $key,
            ];
        }, array_keys($this->tabLabels()), array_values($this->tabLabels()));
    }

    /**
     * @return array<string, string>
     */
    private function tabLabels(): array
    {
        return [
            self::TAB_ALL => (string) __('marketplace.orders.tabs.all'),
            self::TAB_PAID => (string) __('marketplace.orders.tabs.paid'),
            self::TAB_PENDING => (string) __('marketplace.orders.tabs.pending'),
            self::TAB_FAILED => (string) __('marketplace.orders.tabs.failed'),
        ];
    }

    /**
     * In welchen Reiter eine Bestellung faellt.
     *
     * Erstattete und strittige Bestellungen bekommen eine eigene Pille, aber
     * keinen eigenen Reiter -- sie stehen nur unter "Alle". Vier Reiter sind
     * die Grenze dessen, was auf ein Telefon passt, und beide Faelle sind
     * selten.
     */
    private function tabOf(Order $order): string
    {
        return match ($order->status) {
            OrderStatus::SUCCESS->value, OrderStatus::SUCCESS => self::TAB_PAID,
            OrderStatus::NEW->value, OrderStatus::NEW, OrderStatus::PENDING->value, OrderStatus::PENDING => self::TAB_PENDING,
            OrderStatus::FAILED->value, OrderStatus::FAILED => self::TAB_FAILED,
            default => 'other',
        };
    }

    /**
     * Die Jahre, in denen es Bestellungen gibt.
     *
     * @return list<string>
     */
    private function years(): array
    {
        return $this->baseQuery()
            ->get()
            ->map(static fn (Order $order): ?int => $order->created_at?->year)
            ->filter()
            ->unique()
            ->sortDesc()
            ->map(static fn (int $year): string => (string) $year)
            ->values()
            ->all();
    }

    /**
     * Die Bestellungen nach Monat, neueste Gruppe zuerst, mit Monatssumme.
     *
     * **Die Summe zaehlt den ganzen Monat, nicht die geladenen Zeilen.** Wer
     * nur zwoelf von vierzehn Zeilen sieht, soll trotzdem lesen, was der Monat
     * gekostet hat -- eine Summe, die sich beim Nachladen aendert, ist keine
     * Summe.
     *
     * @param  EloquentCollection<int, Order>  $shown  die Zeilen dieser Ansicht
     * @param  EloquentCollection<int, Order>  $all  alle Treffer, fuer die Summen
     * @return list<array{key: string, title: string, sum: string, rows: list<array<string, mixed>>}>
     */
    private function groups(EloquentCollection $shown, EloquentCollection $all): array
    {
        $sums = [];

        foreach ($all as $order) {
            $key = $order->created_at?->format('Y-m') ?? 'unbekannt';
            $sums[$key] = ($sums[$key] ?? 0) + (int) $order->total_amount_after_discount;
        }

        $groups = [];

        foreach ($shown as $order) {
            $key = $order->created_at?->format('Y-m') ?? 'unbekannt';

            $groups[$key]['title'] ??= $order->created_at?->translatedFormat('F Y')
                ?? (string) __('marketplace.orders.unknown_month');
            $groups[$key]['rows'][] = $this->row($order);
        }

        return array_map(fn (string $key, array $group): array => [
            'key' => $key,
            'title' => $group['title'],
            'sum' => Money::format((int) ($sums[$key] ?? 0)),
            'rows' => $group['rows'],
        ], array_keys($groups), array_values($groups));
    }

    /**
     * Eine Zeile, fertig fuer die Ansicht.
     *
     * @return array<string, mixed>
     */
    private function row(Order $order): array
    {
        $tab = $this->tabOf($order);

        return [
            'id' => (int) $order->getKey(),
            'number' => $this->orderNumber($order),
            'date' => $order->created_at?->format('d.m., H:i') ?? '-',
            'amount' => Money::format((int) $order->total_amount_after_discount),
            'method' => $this->paymentMethod($order),
            'tab' => $tab,
            'status' => $this->statusPill($order, $tab),
            'invoiceUrl' => $tab === self::TAB_PAID ? $this->invoiceUrl($order) : null,
        ];
    }

    /**
     * Die Bestellnummer, wie sie der Kaeufer lesen soll.
     *
     * Eine eigene Spalte dafuer gibt es nicht; die Bestellung hat eine
     * fortlaufende Kennung und eine UUID. Die UUID ist unlesbar und taugt nicht
     * zum Vorlesen am Telefon, deshalb hier eine Nummer aus Jahr und Kennung.
     * Sie ist eindeutig und stabil -- aber reine Anzeige: Gesucht wird auch
     * nach ihr, gespeichert wird sie nirgends.
     */
    private function orderNumber(Order $order): string
    {
        return sprintf(
            'WL-%s-%04d',
            $order->created_at?->format('Y') ?? date('Y'),
            (int) $order->getKey(),
        );
    }

    /**
     * Womit bezahlt wurde.
     *
     * Der Entwurf zeigt "Karte •••• 4417". Kartenmarke und Endziffern liegen
     * hier nicht vor -- weder die Bestellung noch die Zahlung speichert sie,
     * und sie beim Anbieter nachzufragen waere ein Netzaufruf je Zeile.
     * Deshalb steht der Name des Zahlungswegs.
     */
    private function paymentMethod(Order $order): string
    {
        $name = $order->paymentProvider?->name;

        return is_string($name) && $name !== ''
            ? $name
            : (string) __('marketplace.orders.method_unknown');
    }

    /**
     * @return array{label: string, tone: string, icon: string}
     */
    private function statusPill(Order $order, string $tab): array
    {
        $status = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;

        if ($status === OrderStatus::REFUNDED->value) {
            return ['label' => (string) __('marketplace.orders.status.refunded'), 'tone' => 'neutral', 'icon' => 'arrow-left'];
        }

        if ($status === OrderStatus::DISPUTED->value) {
            return ['label' => (string) __('marketplace.orders.status.disputed'), 'tone' => 'amber', 'icon' => 'alert'];
        }

        return match ($tab) {
            self::TAB_PAID => ['label' => (string) __('marketplace.orders.status.paid'), 'tone' => 'emerald', 'icon' => 'check'],
            self::TAB_PENDING => ['label' => (string) __('marketplace.orders.status.pending'), 'tone' => 'amber', 'icon' => 'clock'],
            self::TAB_FAILED => ['label' => (string) __('marketplace.orders.status.failed'), 'tone' => 'red', 'icon' => 'alert-circle'],
            default => ['label' => (string) __('marketplace.orders.status.other'), 'tone' => 'neutral', 'icon' => 'info'],
        };
    }

    /**
     * Die Rechnung zu dieser Bestellung -- oder null, wenn es keine gibt.
     */
    private function invoiceUrl(Order $order): ?string
    {
        $transaction = Transaction::query()
            ->where('order_id', $order->getKey())
            ->where('user_id', $this->portalUser()?->getKey())
            ->latest('id')
            ->first();

        if (! $transaction instanceof Transaction) {
            return null;
        }

        return app(InvoiceService::class)->canGenerateInvoices($transaction)
            ? route('invoice.generate', ['transactionUuid' => $transaction->uuid])
            : null;
    }

    /**
     * Die Bestellungen nach Suche, Jahr und Reiter.
     *
     * @return EloquentCollection<int, Order>
     */
    private function matchedOrders(): EloquentCollection
    {
        $orders = $this->searchedOrders();

        if ($this->tab !== self::TAB_ALL) {
            $orders = $orders->filter(fn (Order $order): bool => $this->tabOf($order) === $this->tab);
        }

        return new EloquentCollection($orders->values()->all());
    }

    /**
     * Die Bestellungen nach Suche und Jahr, ohne Reiter.
     *
     * Gesucht wird nach Nummer und Betrag. Beides steht nicht so in der
     * Datenbank, wie es auf dem Bildschirm steht -- die Nummer entsteht erst
     * beim Anzeigen, der Betrag steht in Cent. Deshalb wird hier im Speicher
     * gefiltert und nicht in SQL. Die Menge ist die eines Kaeufers, nicht die
     * der Plattform.
     *
     * @return EloquentCollection<int, Order>
     */
    private function searchedOrders(): EloquentCollection
    {
        $orders = $this->baseQuery()->get();

        if ($this->year !== '') {
            $year = (int) $this->year;
            $orders = $orders->filter(static fn (Order $order): bool => $order->created_at?->year === $year);
        }

        $term = mb_strtolower(trim($this->search));

        if ($term !== '') {
            $orders = $orders->filter(function (Order $order) use ($term): bool {
                $haystack = mb_strtolower(implode(' ', [
                    $this->orderNumber($order),
                    (string) $order->uuid,
                    Money::format((int) $order->total_amount_after_discount),
                    Money::decimal((int) $order->total_amount_after_discount),
                ]));

                return str_contains($haystack, $term);
            });
        }

        return new EloquentCollection($orders->values()->all());
    }

    /**
     * Nur die eigenen Bestellungen dieses Workspace.
     *
     * Beides zusammen, nicht eines davon: Ein Benutzer kann mehreren
     * Workspaces angehoeren, und ein Workspace mehreren Benutzern.
     *
     * @return Builder<Order>
     */
    private function baseQuery(): Builder
    {
        return Order::query()
            ->with(['paymentProvider'])
            ->where('user_id', $this->portalUser()?->getKey())
            ->where('tenant_id', $this->portalTenant()->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
