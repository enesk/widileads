<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Models\Settlement;

/**
 * Erzeugt den Beleg zu einem erfolgreich eingezogenen Settlement
 * (LP-POSTPAID-008) und gibt dessen Nummer zurueck.
 *
 * Warum eine Zusage und keine feste Klasse: Der Einzug (LP-POSTPAID-008) und
 * der Beleg (LP-POSTPAID-015) sind zwei Vorhaben. Der Einzug muss den Beleg
 * anstossen koennen, ohne zu wissen, ob er ueber den vorhandenen
 * InvoiceService laeuft oder ueber ein eigenes, schlankes Dokument -- diese
 * Entscheidung faellt in LP-POSTPAID-015, gebunden wird in AppServiceProvider.
 *
 * `null` ist ein zulaessiges Ergebnis: Entsteht kein Beleg, bleibt die Spalte
 * `settlements.invoice_reference` leer und die Bestaetigungsmail laesst den
 * Belegverweis weg, statt einen zu behaupten.
 *
 * Der Beleg darf den Einzug nie scheitern lassen: Das Geld ist eingegangen und
 * gebucht, bevor diese Zusage gerufen wird.
 */
interface SettlementInvoiceIssuer
{
    /**
     * @return string|null Belegnummer, oder null wenn kein Beleg entstanden ist.
     */
    public function issue(Settlement $settlement): ?string;
}
