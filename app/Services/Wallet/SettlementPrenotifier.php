<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Mail\Wallet\SettlementPrenotificationMail;
use App\Models\Settlement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Die SEPA-Vorabankuendigung vor einem Postpaid-Einzug (LP-POSTPAID-014).
 *
 * Der Einzug ist dadurch zweistufig: Erst entsteht das Settlement als
 * `pending`, bekommt ein angekuendigtes Belastungsdatum und der Kaeufer die
 * Ankuendigung; belastet wird erst nach Ablauf der Frist. Den zweiten Schritt
 * fuehrt der SettlementService (LP-POSTPAID-008) aus, und er darf nur
 * Forderungen belasten, fuer die Settlement::mayBeCharged() zutrifft.
 *
 * Warum die Reihenfolge "erst senden, dann stempeln": `prenotified_at` ist der
 * Nachweis, dass die Pflicht erfuellt ist. Wird der Versand nicht einmal
 * angenommen, bleibt die Spalte leer, das Settlement bleibt offen und der
 * naechste Lauf versucht es erneut -- lieber ein Tag Verzug als eine
 * Belastung ohne Ankuendigung.
 *
 * Kartenzahlungen laufen hier nicht durch: Fuer sie gibt es keine
 * Ankuendigungspflicht (PaymentMethodType::requiresPrenotification()).
 */
class SettlementPrenotifier
{
    /**
     * Kuendigt einen offenen Einzug an und haelt Datum und Zeitpunkt am
     * Settlement fest.
     *
     * @return bool Ob das Settlement nach diesem Aufruf angekuendigt ist.
     */
    public function announce(Settlement $settlement): bool
    {
        if (! $settlement->requiresPrenotification()) {
            return true;
        }

        if ($settlement->prenotified_at !== null) {
            return true;
        }

        $buyer = $settlement->buyer();

        if (! $buyer instanceof Tenant) {
            Log::warning('Vorabankuendigung ohne Kaeufer: Einzug bleibt liegen.', [
                'settlement_id' => $settlement->getKey(),
            ]);

            return false;
        }

        $recipient = $this->recipient($buyer);

        if ($recipient === null) {
            Log::warning('Vorabankuendigung ohne Empfaenger: Einzug bleibt liegen.', [
                'settlement_id' => $settlement->getKey(),
                'tenant_id' => $buyer->getKey(),
            ]);

            return false;
        }

        // Das Datum steht vor dem Versand fest und wird sofort gespeichert:
        // Die Mail ist `ShouldQueue` und serialisiert nur den Schluessel des
        // Settlements -- beim Rendern in der Queue wird die Zeile aus der
        // Datenbank neu geladen, ein nur im Speicher gesetztes Datum waere
        // dort verloren. Ohne `prenotified_at` erlaubt das Datum allein keine
        // Belastung, die Zwischenlage ist also gefahrlos.
        $settlement->charge_due_at = $this->chargeDueAt();
        $settlement->save();

        try {
            Mail::to($recipient)->send(new SettlementPrenotificationMail($settlement, $buyer));
        } catch (Throwable $exception) {
            Log::warning('Vorabankuendigung konnte nicht verschickt werden; es wird nicht belastet.', [
                'settlement_id' => $settlement->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }

        $settlement->prenotified_at = Carbon::now();
        $settlement->save();

        return true;
    }

    /**
     * Kuendigt alle offenen Einzuege an, fuer die noch keine Ankuendigung
     * draussen ist. Aufrufer ist der Scheduler-Lauf des Einzugs, vor dem
     * Belastungsschritt.
     *
     * @return int Zahl der verschickten Ankuendigungen.
     */
    public function announcePending(): int
    {
        $sent = 0;

        Settlement::query()
            ->awaitingPrenotification()
            ->with(['paymentMethod', 'wallet.owner'])
            ->each(function (Settlement $settlement) use (&$sent): void {
                if ($this->announce($settlement)) {
                    $sent++;
                }
            });

        return $sent;
    }

    /**
     * Das Belastungsdatum, das dem Kaeufer angekuendigt wird: heute zuzueglich
     * der Vorlauffrist in Werktagen (config('wallet.postpaid.prenotification_days')).
     *
     * Werktage und nicht Kalendertage, weil eine Ankuendigung am Freitag mit
     * Belastung am Samstag dem Kaeufer keinen Tag zum Decken seines Kontos
     * laesst. Gesetzliche Feiertage kennt diese Rechnung nicht -- sie sind
     * bundeslandabhaengig, und ein Tag Puffer mehr ist ueber die Frist in der
     * Konfiguration einstellbar.
     */
    public function chargeDueAt(?Carbon $from = null): Carbon
    {
        $days = max(0, (int) config('wallet.postpaid.prenotification_days', 1));

        return ($from ?? Carbon::now())->copy()->addWeekdays($days)->startOfDay();
    }

    /**
     * Empfaenger der Ankuendigung. Sie gilt dem Mandanten, nicht einer Person
     * -- genommen wird derselbe Weg wie bei den uebrigen Wallet-Meldungen.
     */
    private function recipient(Tenant $buyer): ?string
    {
        $user = $buyer->users()->first();

        if (! $user instanceof User || ! is_string($user->email) || $user->email === '') {
            return null;
        }

        return $user->email;
    }
}
