<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\CreditLedgerType;
use App\Constants\TenantType;
use App\Events\Order\Ordered;
use App\Exceptions\CreditLedgerIsImmutableException;
use App\Exceptions\InsufficientCreditsException;
use App\Models\CreditLedgerEntry;
use App\Models\Currency;
use App\Models\OneTimeProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tenant;
use App\Services\CreditLedgerService;
use InvalidArgumentException;
use Tests\Feature\FeatureTest;

/**
 * FB-052: Das Guthabenkonto ist Geld.
 *
 * Drei Zusicherungen, auf die FB-054 (Kaufvorgang) und FB-059 (Abrechnung)
 * bauen: Der Saldo geht nie unter null, eine Buchung ist unveraenderlich, und
 * ein wiederholt zugestelltes Zahlungsereignis schreibt Guthaben genau einmal
 * gut.
 */
class CreditLedgerTest extends FeatureTest
{
    private function buyer(): Tenant
    {
        return Tenant::factory()->create(['type' => TenantType::BUYER]);
    }

    public function test_the_balance_is_the_sum_of_the_journal_and_never_goes_below_zero(): void
    {
        $tenant = $this->buyer();
        $service = app(CreditLedgerService::class);

        $this->assertSame(0, $service->balanceFor($tenant));

        // Ein leeres Konto laesst sich nicht belasten -- ein Kaeufer soll nicht
        // auf Kredit kaufen koennen.
        try {
            $service->debit($tenant, 1);
            $this->fail('Eine Abbuchung ohne Guthaben muss abgewiesen werden.');
        } catch (InsufficientCreditsException) {
            // erwartet
        }

        $this->assertSame(0, CreditLedgerEntry::query()->withoutGlobalScope('tenant')->count());

        $service->purchase($tenant, 10, 15000);
        $service->debit($tenant, 4);

        $this->assertSame(6, $service->balanceFor($tenant));

        // Genau bis auf null darf gebucht werden, einen Schritt weiter nicht.
        $service->debit($tenant, 6);
        $this->assertSame(0, $service->balanceFor($tenant));

        $this->expectException(InsufficientCreditsException::class);
        $service->debit($tenant, 1);
    }

    public function test_the_booking_type_decides_the_sign(): void
    {
        $tenant = $this->buyer();
        $service = app(CreditLedgerService::class);

        $service->purchase($tenant, 10, 15000);

        // Eine Abbuchung mit positivem Betrag waere eine stille Geldschoepfung.
        // debit() dreht das Vorzeichen selbst, deshalb wird hier direkt gebucht.
        $this->expectException(InvalidArgumentException::class);

        $service->adjust($tenant, 0);
    }

    public function test_entries_can_neither_be_changed_nor_deleted(): void
    {
        $tenant = $this->buyer();
        $entry = app(CreditLedgerService::class)->purchase($tenant, 10, 15000);

        // Eine Fehlbuchung wird durch eine Gegenbuchung korrigiert, nicht durch
        // Ueberschreiben -- sonst waere der Saldo nicht mehr herleitbar.
        try {
            $entry->update(['credits' => 1000]);
            $this->fail('Eine Buchung darf nicht geaendert werden koennen.');
        } catch (CreditLedgerIsImmutableException) {
            // erwartet
        }

        // Auch der Weg an update() vorbei ist versperrt.
        try {
            $entry->credits = 1000;
            $entry->save();
            $this->fail('Auch save() darf eine Buchung nicht aendern.');
        } catch (CreditLedgerIsImmutableException) {
            // erwartet
        }

        try {
            $entry->delete();
            $this->fail('Eine Buchung darf nicht geloescht werden koennen.');
        } catch (CreditLedgerIsImmutableException) {
            // erwartet
        }

        // Und der Query-Builder, den Model-Events nicht erreichen.
        try {
            CreditLedgerEntry::query()->withoutGlobalScope('tenant')->update(['credits' => 1000]);
            $this->fail('Ein Massen-Update darf eine Buchung nicht aendern.');
        } catch (CreditLedgerIsImmutableException) {
            // erwartet
        }

        try {
            CreditLedgerEntry::query()->withoutGlobalScope('tenant')->delete();
            $this->fail('Ein Massen-Delete darf eine Buchung nicht loeschen.');
        } catch (CreditLedgerIsImmutableException) {
            // erwartet
        }

        $this->assertSame(10, $entry->fresh()->credits);
    }

    public function test_a_repeated_payment_event_books_the_credits_only_once(): void
    {
        $tenant = $this->buyer();

        $package = OneTimeProduct::factory()->create([
            'name' => '50 Leads',
            'metadata' => [CreditLedgerService::PRODUCT_METADATA_KEY => 50],
        ]);

        // Vollstaendig ausgestattet, damit das Ereignis den echten Weg nimmt --
        // samt der uebrigen Zuhoerer von SaaSykit. Nur so ist mitgeprueft, dass
        // der Zuhoerer ueberhaupt am Ereignis haengt.
        $order = Order::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $this->createUser($tenant)->getKey(),
            'currency_id' => Currency::query()->where('code', 'USD')->value('id'),
            'status' => 'success',
            'total_amount' => 49900,
            'total_amount_after_discount' => 49900,
        ]);

        // Direkt angelegt statt ueber eine Factory: fuer OrderItem gibt es
        // keine, und eine anzulegen waere Arbeit an fremdem Bestand.
        OrderItem::query()->create([
            'order_id' => $order->getKey(),
            'one_time_product_id' => $package->getKey(),
            'quantity' => 2,
            'price_per_unit' => 24950,
            'price_per_unit_after_discount' => 24950,
        ]);

        // Stripe stellt Webhooks wiederholt zu. Dieselbe Bestellung darf
        // Guthaben trotzdem nur einmal gutschreiben.
        Ordered::dispatch($order);
        Ordered::dispatch($order);
        Ordered::dispatch($order);

        $entries = CreditLedgerEntry::query()->withoutGlobalScope('tenant')->get();

        $this->assertCount(1, $entries);
        $this->assertSame(CreditLedgerType::PURCHASE, $entries->first()->type);

        // Zwei Pakete zu je 50 Guthaben.
        $this->assertSame(100, $entries->first()->credits);
        $this->assertSame(49900, $entries->first()->amount_cents);
        $this->assertSame($order->getMorphClass(), $entries->first()->reference_type);
        $this->assertSame($order->getKey(), $entries->first()->reference_id);

        $this->assertSame(100, app(CreditLedgerService::class)->balanceFor($tenant->fresh()));
    }
}
