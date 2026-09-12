<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Settlement;
use App\Services\Wallet\SettlementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Stoesst den Einzug einer Forderung beim Zahlungsanbieter an
 * (LP-POSTPAID-008).
 *
 * Warum ueberhaupt ein Job: Der Einzug haengt an einem fremden Dienst. Weder
 * die Abrechnung eines Leads (Schwellwert-Einzug) noch der Scheduler-Lauf
 * sollen darauf warten oder daran scheitern, dass Stripe gerade langsam ist.
 *
 * Drei Versuche, dann Schluss. Was hier scheitert, ist eine technische
 * Stoerung -- eine Ablehnung des Zahlungsmittels behandelt der
 * SettlementService selbst und wirft nicht. Deshalb fuehrt das Aufgeben
 * ausdruecklich NICHT zur Rueckstufung: Ein Netzwerkfehler auf unserer Seite
 * ist kein Zahlungsverzug des Kunden. Stattdessen wird die Forderung
 * stillgelegt und ein Mensch bekommt Bescheid.
 *
 * Das Settlement wandert als Kennung und nicht als Modell durch die Queue: Der
 * Job laeuft womoeglich Minuten spaeter, ein serialisierter Zustand waere dann
 * veraltet.
 */
class ChargeSettlementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Abstaende zwischen den Versuchen. Der erste Wiederholungsversuch kommt
     * schnell (kurze Stoerung), der zweite deutlich spaeter (Ausfall).
     *
     * @var list<int>
     */
    public array $backoff = [30, 300];

    public function __construct(public int $settlementId) {}

    public function handle(SettlementService $settlements): void
    {
        $settlement = Settlement::query()->find($this->settlementId);

        if (! $settlement instanceof Settlement) {
            return;
        }

        $settlements->charge($settlement);
    }

    /**
     * Nach dem letzten Versuch: Forderung stilllegen, Betreiber melden, keine
     * Rueckstufung.
     */
    public function failed(Throwable $exception): void
    {
        $settlement = Settlement::query()->find($this->settlementId);

        if (! $settlement instanceof Settlement) {
            return;
        }

        app(SettlementService::class)->markTechnicallyFailed($settlement, $exception->getMessage());
    }
}
