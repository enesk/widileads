<?php

declare(strict_types=1);

namespace App\Events\Postpaid;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Ein Postpaid-Wallet soll zurueckgestuft werden (LP-POSTPAID-005).
 *
 * Ausgeloest, wenn die Voraussetzung des Nachzahlungsverfahrens wegfaellt --
 * zunaechst der Widerruf des Standard-Zahlungsmittels durch Kaeufer oder Bank
 * (Grund `payment_method_revoked`). Weitere Gruende kommen mit der
 * Zahlungsstoerung hinzu (LP-POSTPAID-009), etwa der endgueltig gescheiterte
 * Einzug oder die Ruecklastschrift.
 *
 * Das Ereignis stuft selbst nicht zurueck: Was eine Rueckstufung bedeutet --
 * Rahmen auf 0, Sperre, Gebuehr, Benachrichtigung -- entscheidet der Zuhoerer
 * aus LP-POSTPAID-009. Hier steht nur die Feststellung, dass die Grundlage
 * entfallen ist. So bleibt die Stelle, die den Widerruf bemerkt (der Webhook),
 * frei von der Frage, was daraus folgt.
 *
 * Das Ereignis traegt bewusst nur Kennungen und keine Modelle: Der Zuhoerer
 * laeuft womoeglich spaeter, und ein serialisiertes Wallet waere dann veraltet.
 *
 * ShouldDispatchAfterCommit, weil der Widerruf des Zahlungsmittels in einer
 * Transaktion geschrieben wird -- der Zuhoerer soll den neuen Stand sehen.
 */
class PostpaidDowngradeRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** Das Standard-Zahlungsmittel wurde widerrufen oder abgehaengt. */
    public const REASON_PAYMENT_METHOD_REVOKED = 'payment_method_revoked';

    /**
     * Beim faelligen Einzug war kein einsatzbereites Zahlungsmittel mehr
     * hinterlegt (LP-POSTPAID-008). Die Forderung besteht weiter, eingezogen
     * werden kann sie nicht.
     */
    public const REASON_NO_PAYMENT_METHOD = 'no_payment_method';

    /**
     * Der Einzug ist nach dem zweiten Versuch endgueltig gescheitert
     * (LP-POSTPAID-008). Gemeint ist ausschliesslich der Zahlungsfehler --
     * eine technische Stoerung auf unserer Seite fuehrt bewusst NICHT hierher.
     */
    public const REASON_SETTLEMENT_FAILED = 'settlement_failed';

    /**
     * Eine bereits gutgeschriebene Lastschrift wurde zurueckgegeben
     * (LP-POSTPAID-009). Der haerteste Fall: Das Geld war da und ist wieder
     * weg, zusaetzlich faellt eine Ruecklastschriftgebuehr an.
     */
    public const REASON_SEPA_RETURN = 'sepa_return';

    /**
     * Der Kaeufer hat eine Kartenbelastung bei seiner Bank angefochten
     * (LP-POSTPAID-009). Wie die Ruecklastschrift eine Rueckbuchung, nur ueber
     * den Kartenweg.
     */
    public const REASON_CHARGEBACK = 'chargeback';

    /**
     * @param  int  $walletId  Das betroffene Kauf-Wallet.
     * @param  string  $reason  Grund der Rueckstufung; landet in
     *                          wallets.postpaid_disabled_reason.
     * @param  int|null  $paymentMethodId  Das ausloesende Zahlungsmittel, falls es
     *                                     eines gab.
     */
    public function __construct(
        public int $walletId,
        public string $reason,
        public ?int $paymentMethodId = null,
    ) {}
}
