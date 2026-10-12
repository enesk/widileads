<?php

namespace Tests\Feature\Http\Controllers;

use App\Constants\OrderStatus;
use App\Constants\TransactionStatus;
use App\Models\Config;
use App\Models\Currency;
use App\Models\OneTimeProduct;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Feature\FeatureTest;

class InvoiceControllerTest extends FeatureTest
{
    public function test_generate()
    {
        config(['invoices.enabled' => true]);
        $this->completeCompanyProfile();

        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);
        $this->actingAs($user);

        $product = OneTimeProduct::factory()->create([
            'slug' => 'product-slug-'.Str::random(20),
            'is_active' => true,
        ]);

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'total_amount' => 10,
            'currency_id' => 1,
            'status' => OrderStatus::SUCCESS->value,
        ]);

        $orderItem = $order->items()->create([
            'one_time_product_id' => $product->id,
            'quantity' => 1,
            'price_per_unit' => 10,
            'currency_id' => Currency::where('code', 'USD')->first()->id,
        ]);

        $transaction = Transaction::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'amount' => 100,
            'currency_id' => Currency::where('code', 'USD')->first()->id,
            'order_id' => $order->id,
            'status' => TransactionStatus::SUCCESS->value,
        ]);

        $transactionUuid = $transaction->uuid;

        $response = $this->get(route('invoice.generate', ['transactionUuid' => $transactionUuid]));
        $response->assertStatus(200);

        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));

    }

    public function test_invoice_generation_work_only_if_enabled()
    {
        config(['invoices.enabled' => false]);

        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);

        $transaction = Transaction::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'amount' => 100,
            'currency_id' => Currency::where('code', 'USD')->first()->id,
            'status' => TransactionStatus::SUCCESS->value,
        ]);

        $this->expectException(NotFoundHttpException::class);

        $this->get(route('invoice.generate', ['transactionUuid' => $transaction->uuid]));
    }

    /**
     * Firmenstammdaten des Betreibers hinterlegen.
     *
     * Seit FB-Ticket #31 erzeugt InvoiceService keine Rechnung, solange Name,
     * Anschrift und eine Kontaktadresse fehlen (CompanyProfile::isComplete()):
     * Eine Rechnung ohne Absender ist keine. Quelle ist ausschliesslich die
     * Tabelle `configs`, deshalb hier Config::set() und nicht config().
     */
    private function completeCompanyProfile(): void
    {
        Config::set('invoices.seller.attributes.name', 'Widimedia GmbH');
        Config::set('invoices.seller.attributes.address', 'Teststrasse 1, 76131 Karlsruhe');
        Config::set('app.support_email', 'support@example.com');
    }
}
