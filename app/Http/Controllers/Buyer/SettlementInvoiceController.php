<?php

declare(strict_types=1);

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Settlement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Wallet\SettlementInvoiceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Liefert den Beleg zu einem Postpaid-Einzug aus (LP-POSTPAID-015).
 *
 * Eine eigene Adresse statt eines Knopfes in der Livewire-Komponente: Ein PDF
 * ist ein Download und keine Zustandsaenderung, und ein Link laesst sich
 * teilen, neu laden und in einem neuen Tab oeffnen.
 *
 * Gepruefte Bedingung ist die Mitgliedschaft im Workspace des Wallets, nicht
 * der Workspace im Pfad: Der Beleg gehoert dem Kaeufer. Ein fremder Beleg ist
 * dabei nicht "verboten", sondern unbekannt -- sonst verriete die Antwort,
 * dass es ihn gibt.
 */
class SettlementInvoiceController extends Controller
{
    public function __construct(private SettlementInvoiceService $invoices) {}

    public function __invoke(Request $request, Settlement $settlement): BinaryFileResponse
    {
        $user = $request->user();
        $buyer = $settlement->buyer();

        if (! $user instanceof User || ! $buyer instanceof Tenant) {
            abort(404);
        }

        if (! $user->tenants()->whereKey($buyer->getKey())->exists()) {
            abort(404);
        }

        $response = $this->invoices->download($settlement);

        if ($response === null) {
            abort(404);
        }

        return $response;
    }
}
