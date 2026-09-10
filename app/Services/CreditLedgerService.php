<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\CreditLedgerType;
use App\Exceptions\InsufficientCreditsException;
use App\Models\CreditLedgerEntry;
use App\Models\Scopes\TenantScopes;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Das Guthabenkonto der Kaeufer (FB-052).
 *
 * Die einzige Stelle, an der Buchungen entstehen. Der Saldo ist die Summe des
 * Journals, kein gepflegter Zaehler: ein Zaehler kann von der Historie
 * abweichen, und dann ist nicht mehr feststellbar, welcher Wert stimmt. Bei
 * Geld ist die Historie die Wahrheit.
 *
 * **Der Saldo wird nie negativ.** Jede Buchung, die ihn verringert, laeuft in
 * einer Transaktion unter Sperre des Mandanten-Datensatzes: erst sperren, dann
 * den Saldo frisch aus dem Journal lesen, dann buchen. Zwei gleichzeitige
 * Abbuchungen desselben Mandanten koennen so nicht gemeinsam ins Minus laufen,
 * weil die zweite auf die Sperre wartet und danach den bereits verringerten
 * Saldo sieht. Der eigentliche Kaufvorgang samt seiner Reservierung ist FB-054;
 * die Sperre muss aber schon hier richtig sein, sonst baut FB-054 auf Sand.
 *
 * Der gecachte Saldo ist ausschliesslich fuer die Anzeige. Die Deckungspruefung
 * liest immer aus der Datenbank -- ein Cache, der einer Kaufentscheidung
 * zugrunde liegt, ist ein Fehler mit Geldfolge.
 *
 * Jede Buchung traegt ihre Waehrung (FB-052a). Sie gilt fuer die ganze Buchung,
 * nicht nur fuer amount_cents: Ein Guthabenkonto ist in einer Waehrung gefuehrt,
 * auch wenn eine Abbuchung selbst kein Geld bewegt. Ohne Angabe gilt die
 * Standardwaehrung der Installation -- ein Guthabenkauf nennt dagegen die
 * Waehrung, in der tatsaechlich bezahlt wurde.
 */
class CreditLedgerService
{
    /**
     * Schluessel im Metadatenfeld eines Einmalkauf-Produkts, unter dem die
     * Anzahl der enthaltenen Lead-Guthaben steht. Kein Schwellwert, sondern
     * ein Vertrag mit der Produktpflege im Admin-Panel -- deshalb hier und
     * nicht in der Konfiguration.
     */
    public const PRODUCT_METADATA_KEY = 'credits';

    /**
     * Guthaben eines Mandanten -- fuer die Anzeige, aus dem Cache.
     */
    public function balanceFor(Tenant $tenant): int
    {
        return Cache::remember(
            self::cacheKey($tenant),
            (int) config('funnel.marketplace.credit.balance_cache_ttl'),
            fn (): int => $this->readBalance($tenant),
        );
    }

    /**
     * Guthabenkauf. Idempotent ueber den Beleg: dasselbe Ereignis schreibt auch
     * bei wiederholter Zustellung nur eine Buchung.
     */
    public function purchase(
        Tenant $tenant,
        int $credits,
        ?int $amountCents = null,
        ?Model $reference = null,
        ?string $currency = null,
    ): CreditLedgerEntry {
        return $this->book($tenant, CreditLedgerType::PURCHASE, $credits, $amountCents, $reference, $currency);
    }

    /**
     * Abbuchung beim Kauf eines Leads. Erwartet die Anzahl als positive Zahl
     * und bucht sie negativ -- ein Aufrufer soll sich nicht mit Vorzeichen
     * befassen muessen.
     *
     * @throws InsufficientCreditsException wenn das Guthaben nicht reicht
     */
    public function debit(
        Tenant $tenant,
        int $credits,
        ?Model $reference = null,
        ?string $currency = null,
    ): CreditLedgerEntry {
        return $this->book($tenant, CreditLedgerType::DEBIT, -abs($credits), null, $reference, $currency);
    }

    /**
     * Gutschrift nach einer anerkannten Reklamation (FB-058).
     */
    public function refund(
        Tenant $tenant,
        int $credits,
        ?Model $reference = null,
        ?string $currency = null,
    ): CreditLedgerEntry {
        return $this->book($tenant, CreditLedgerType::REFUND, abs($credits), null, $reference, $currency);
    }

    /**
     * Manuelle Korrektur durch den Plattform-Admin -- auch der Weg fuer
     * Guthaben auf Rechnung. Das Vorzeichen bestimmt der Aufrufer.
     */
    public function adjust(
        Tenant $tenant,
        int $credits,
        ?int $amountCents = null,
        ?Model $reference = null,
        ?string $currency = null,
    ): CreditLedgerEntry {
        return $this->book($tenant, CreditLedgerType::ADJUSTMENT, $credits, $amountCents, $reference, $currency);
    }

    /**
     * Schreibt eine Buchung.
     *
     * @throws InvalidArgumentException bei einem Vorzeichen, das nicht zur Buchungsart passt
     * @throws InsufficientCreditsException wenn eine Abbuchung den Saldo unter null druecken wuerde
     */
    private function book(
        Tenant $tenant,
        CreditLedgerType $type,
        int $credits,
        ?int $amountCents,
        ?Model $reference,
        ?string $currency = null,
    ): CreditLedgerEntry {
        $currency = self::normalizeCurrency($currency);

        if (! $type->allowsCredits($credits)) {
            throw new InvalidArgumentException(
                sprintf('Eine Buchung vom Typ "%s" laesst den Betrag %d nicht zu.', $type->value, $credits),
            );
        }

        $entry = DB::transaction(function () use ($tenant, $type, $credits, $amountCents, $reference, $currency): CreditLedgerEntry {
            // Sperrt den Mandanten fuer die Dauer der Transaktion. Alle
            // Buchungen desselben Mandanten laufen dadurch nacheinander, egal
            // aus welchem Prozess sie kommen.
            Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->first();

            if ($reference !== null) {
                $existing = $this->existingEntryFor($tenant, $type, $reference);

                if ($existing !== null) {
                    return $existing;
                }
            }

            if ($type->reducesBalance($credits)) {
                $balance = $this->readBalance($tenant);

                if ($balance + $credits < 0) {
                    throw InsufficientCreditsException::for($balance, abs($credits));
                }
            }

            return CreditLedgerEntry::query()->create([
                'tenant_id' => $tenant->getKey(),
                'type' => $type,
                'credits' => $credits,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
            ]);
        });

        Cache::forget(self::cacheKey($tenant));

        return $entry;
    }

    /**
     * Buchung zu diesem Beleg, falls es sie schon gibt.
     *
     * Der Unique-Index auf (type, reference_type, reference_id) ist die
     * eigentliche Absicherung; diese Abfrage erspart nur den Fehlerfall im
     * Regelbetrieb. Beide zusammen machen die Buchung idempotent -- was
     * gebraucht wird, weil Stripe Webhooks wiederholt zustellt.
     */
    private function existingEntryFor(Tenant $tenant, CreditLedgerType $type, Model $reference): ?CreditLedgerEntry
    {
        return CreditLedgerEntry::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->where('tenant_id', $tenant->getKey())
            ->where('type', $type->value)
            ->where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->first();
    }

    /**
     * Saldo direkt aus dem Journal. Innerhalb einer Buchung ist das der einzige
     * zulaessige Weg -- der Cache taugt zur Anzeige, nicht zur Entscheidung.
     */
    private function readBalance(Tenant $tenant): int
    {
        return (int) CreditLedgerEntry::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->where('tenant_id', $tenant->getKey())
            ->sum('credits');
    }

    /**
     * Waehrungscode in der Form, in der er gespeichert wird: drei Grossbuchstaben.
     * Ohne Angabe die Standardwaehrung der Installation.
     *
     * @throws InvalidArgumentException bei einem Code, der kein ISO-4217-Code sein kann
     */
    private static function normalizeCurrency(?string $currency): string
    {
        $code = strtoupper(trim($currency ?? (string) config('app.default_currency')));

        if (preg_match('/^[A-Z]{3}$/', $code) !== 1) {
            throw new InvalidArgumentException(
                sprintf('"%s" ist kein Waehrungscode nach ISO 4217.', $code),
            );
        }

        return $code;
    }

    private static function cacheKey(Tenant $tenant): string
    {
        return 'funnel.credit_balance.'.$tenant->getKey();
    }
}
