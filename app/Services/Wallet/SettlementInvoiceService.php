<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Constants\SettlementStatus;
use App\Constants\WalletTransactionType;
use App\Models\LeadPurchase;
use App\Models\Settlement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\CompanyProfile;
use App\Services\InvoiceService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Invoice;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Der Beleg zu einem eingezogenen Postpaid-Betrag (LP-POSTPAID-015).
 *
 * Warum ein eigener Dienst neben App\Services\InvoiceService: Dessen Beleg
 * haengt an einer `Transaction`, die wiederum an Bestellung oder Abonnement
 * haengt, und App\Models\Invoice traegt eine nicht nullbare `transaction_id`.
 * Ein Postpaid-Einzug hat nichts davon -- er belastet das hinterlegte
 * Zahlungsmittel unmittelbar, ohne Checkout und ohne Bestellung. Eine
 * Scheinbestellung zu erzeugen, nur damit das vorhandene Rechnungswesen passt,
 * waere ein Umbau mit Nebenwirkungen auf Umsatzmetriken, Abonnementlogik und
 * die Bestelluebersicht. Wiederverwendet wird deshalb das, was ohne Kopplung
 * wiederverwendbar ist: das Paket saasykit/laravel-invoices samt Vorlage,
 * die Verkaeuferangaben aus config('invoices.*') und die Adresszeilen des
 * Kaeufers aus InvoiceService::addAddressInfo().
 *
 * Der Beleg entsteht genau einmal je Settlement, sobald es `paid` ist: Der
 * Einzug ruft ihn ueber die Zusage SettlementInvoiceIssuer, jeder andere Weg
 * in den Zustand `paid` ueber App\Observers\SettlementObserver. Er wird als
 * PDF auf der Ablage der
 * Rechnungen gespeichert; `settlements.invoice_reference` traegt danach seine
 * Belegnummer; der Dateipfad ergibt sich aus ihr und wird nicht zusaetzlich
 * gespeichert.
 *
 * Die Belegnummer ist fortlaufend und eindeutig: Serie, Jahr des Einzugs und
 * der auf fuenf Stellen aufgefuellte Schluessel des Settlements, also etwa
 * `ABR-2026-00042`. Der Schluessel steigt streng monoton -- eine eigene
 * Zaehlerspalte waere ein zweiter Wahrheitsort und unter Nebenlaeufigkeit
 * angreifbar. Luecken sind zulaessig, Dopplungen nicht.
 */
class SettlementInvoiceService implements SettlementInvoiceIssuer
{
    /** Auf so viele Stellen wird der Zaehler der Belegnummer aufgefuellt. */
    private const SEQUENCE_PADDING = 5;

    public function __construct(
        private CompanyProfile $companyProfile,
        private InvoiceService $invoiceService,
    ) {}

    /**
     * Die Zusage, die der Einzug kennt (LP-POSTPAID-008): Beleg erzeugen und
     * seine Nummer zurueckgeben. Der SettlementService faengt Fehler selbst
     * ab -- ein fehlender Beleg darf einen eingegangenen Betrag nicht
     * zurueckdrehen.
     */
    public function issue(Settlement $settlement): ?string
    {
        return $this->ensure($settlement);
    }

    /**
     * Darf fuer diesen Einzug ein Beleg entstehen?
     *
     * Derselbe Massstab wie bei den Rechnungen zu Bestellungen: Ohne den
     * Schalter und ohne vollstaendige eigene Firmenstammdaten entstuende ein
     * Beleg ohne Absender. Dazu die fachliche Bedingung -- belegt wird eine
     * Zahlung, die eingegangen ist.
     */
    public function canGenerate(Settlement $settlement): bool
    {
        if (config('invoices.enabled', false) !== true) {
            return false;
        }

        if (! $this->companyProfile->isComplete()) {
            return false;
        }

        if ($settlement->status !== SettlementStatus::PAID) {
            return false;
        }

        return $settlement->buyer() instanceof Tenant;
    }

    /**
     * Erzeugt den Beleg, falls noetig, und gibt seine Belegnummer zurueck.
     *
     * Ist er schon da, wird nichts neu gerendert: Ein einmal ausgestellter
     * Beleg ist ein Dokument und kein Bericht -- er darf sich nicht aendern,
     * nur weil spaeter jemand die Seite aufruft.
     *
     * @return string|null Belegnummer, oder null wenn kein Beleg entstehen darf.
     */
    public function ensure(Settlement $settlement, bool $regenerate = false): ?string
    {
        if (! $this->canGenerate($settlement)) {
            return null;
        }

        $reference = $this->reference($settlement);

        if (! $regenerate && $this->exists($settlement)) {
            $this->rememberReference($settlement, $reference);

            return $reference;
        }

        $this->render($settlement)->save();

        $this->rememberReference($settlement, $reference);

        return $reference;
    }

    /**
     * Liefert den Beleg zum Abruf aus. Fehlt die Datei -- etwa weil der Beleg
     * vor der Pflege der Firmenstammdaten faellig war --, wird sie hier
     * nachgeholt.
     */
    public function download(Settlement $settlement): ?BinaryFileResponse
    {
        if ($this->ensure($settlement) === null) {
            return null;
        }

        $storage = Storage::disk((string) config('invoices.disk'));
        $path = $this->path($settlement);

        if (! $storage->exists($path)) {
            return null;
        }

        return response()->file($storage->path($path));
    }

    /**
     * Liegt die Belegdatei auf der Ablage?
     */
    public function exists(Settlement $settlement): bool
    {
        return Storage::disk((string) config('invoices.disk'))->exists($this->path($settlement));
    }

    /**
     * Die Belegnummer dieses Einzugs. Sie wird berechnet und nicht geraten:
     * Derselbe Einzug bekommt immer dieselbe Nummer, auch wenn die Spalte
     * `invoice_reference` noch leer ist.
     */
    public function reference(Settlement $settlement): string
    {
        return sprintf(
            '%s-%s',
            $this->series($settlement),
            str_pad((string) $settlement->getKey(), self::SEQUENCE_PADDING, '0', STR_PAD_LEFT),
        );
    }

    /**
     * Die Positionen des Belegs.
     *
     * Grundlage ist das Journal des Kaeufer-Wallets und nicht die Tabelle der
     * Kaeufe: Nur so ist die Summe der Positionen aus derselben Quelle wie der
     * eingezogene Betrag. Aufgefuehrt wird, was den offenen Betrag im Zeitraum
     * bewegt hat -- abgerechnete Leadkaeufe, Erstattungen und Gebuehren
     * (Mahnung, Ruecklastschrift, LP-POSTPAID-009).
     *
     * Der Aufschlag steht nicht als eigene Zeile: Er ist Teil des Betrags, mit
     * dem das Kaeufer-Wallet belastet wurde (PurchaseService::buyerTotalCents),
     * und wuerde als zweite Zeile die Summe verdoppeln. Er steht deshalb im
     * Zusatz der Leadzeile, wo er auch hingehoert -- der Kaeufer sieht Preis
     * und Aufschlag nebeneinander.
     *
     * @return list<array{title: string, description: string, amount_cents: int}>
     */
    public function positions(Settlement $settlement): array
    {
        $movements = $this->movements($settlement);
        $positions = [];
        $sum = 0;

        foreach ($movements as $movement) {
            $position = $this->positionFor($movement);

            if ($position === null) {
                continue;
            }

            $positions[] = $position;
            $sum += $position['amount_cents'];
        }

        $difference = (int) $settlement->amount_cents - $sum;

        // Alles, was den offenen Betrag sonst noch bewegt hat: ein Rest aus
        // dem Vorzeitraum, eine zwischenzeitliche Aufladung, ein Guthaben aus
        // Prepaid-Zeiten. Ohne diese Zeile liefen Positionen und eingezogener
        // Betrag auseinander, und genau das macht einen Beleg wertlos.
        if ($difference !== 0) {
            $positions[] = [
                'title' => (string) __($difference > 0
                    ? 'marketplace.wallet.settlement_invoice.items.carried_over'
                    : 'marketplace.wallet.settlement_invoice.items.credited'),
                'description' => (string) __('marketplace.wallet.settlement_invoice.items.balance_hint'),
                'amount_cents' => $difference,
            ];
        }

        return $positions;
    }

    /**
     * Beginn des Abrechnungszeitraums: das Ende des vorherigen Einzugs
     * desselben Wallets. Beim ersten Einzug gibt es keinen -- dann zaehlt
     * alles bis zum Stichtag.
     */
    public function periodStart(Settlement $settlement): ?Carbon
    {
        $previous = Settlement::query()
            ->where('wallet_id', $settlement->wallet_id)
            ->where('id', '<', $settlement->getKey())
            ->orderByDesc('id')
            ->first();

        return $previous?->created_at;
    }

    /**
     * Ende des Abrechnungszeitraums: der Stichtag, zu dem der offene Betrag
     * zur Forderung zusammengefasst wurde. Das ist das Anlegen des
     * Settlements, nicht der Zahlungseingang -- was danach gekauft wurde,
     * steht auf dem naechsten Beleg.
     */
    public function periodEnd(Settlement $settlement): Carbon
    {
        return $settlement->created_at ?? Carbon::now();
    }

    /**
     * Pfad der Belegdatei auf der Ablage der Rechnungen, einschliesslich
     * Endung. Er ergibt sich aus Belegnummer und Stichtag und wird deshalb
     * nirgends gespeichert.
     */
    public function path(Settlement $settlement): string
    {
        return $this->basePath($settlement).'.pdf';
    }

    /**
     * Die im Beleg enthaltene Umsatzsteuer in Cent.
     *
     * Gerechnet wird wie auf der Aufladeseite: Der Betrag ist ein Bruttobetrag,
     * die Steuer ist der darin enthaltene Anteil. Steht der Satz auf 0, faellt
     * die Zeile weg, statt einen Satz zu behaupten, der nicht abgerechnet wird.
     */
    public function vatCents(Settlement $settlement): int
    {
        $percent = (int) config('wallet.topup_vat_percent');

        if ($percent <= 0) {
            return 0;
        }

        $gross = (int) $settlement->amount_cents;

        return (int) round($gross - $gross / (1 + $percent / 100));
    }

    /**
     * Baut den Beleg zusammen. Die Vorlage, die Verkaeuferangaben und das
     * Zahlenformat kommen unveraendert aus dem Rechnungspaket.
     */
    private function render(Settlement $settlement): Invoice
    {
        $buyer = $settlement->buyer();
        $amountCents = (int) $settlement->amount_cents;

        $invoice = Invoice::make((string) __('marketplace.wallet.settlement_invoice.title'))
            ->buyer($this->party($settlement, $buyer))
            ->series($this->series($settlement))
            ->delimiter('-')
            ->sequencePadding(self::SEQUENCE_PADDING)
            ->sequence((int) $settlement->getKey())
            ->date($settlement->paid_at ?? $this->periodEnd($settlement))
            ->dateFormat((string) config('app.date_format'))
            ->status((string) __('marketplace.wallet.settlement_invoice.status_paid'))
            ->notes($this->notes($settlement))
            ->logo(public_path((string) config('app.logo.dark')))
            ->formattedTotalAmount(Money::format($amountCents));

        $vatCents = $this->vatCents($settlement);

        if ($vatCents > 0) {
            $invoice->formattedTotalTaxes(Money::format($vatCents));
        }

        foreach ($this->positions($settlement) as $position) {
            $item = InvoiceItem::make($position['title'])
                ->quantity(1)
                ->formattedPricePerUnit(Money::format($position['amount_cents']));

            if ($position['description'] !== '') {
                $item->description($position['description']);
            }

            $invoice->addItem($item);
        }

        // Der Dateiname wird zuletzt gesetzt: sequence() setzt ihn selbst auf
        // den Vorgabewert zurueck.
        return $invoice->filename($this->basePath($settlement));
    }

    /**
     * Der Kaeufer, wie er auf dem Beleg steht. Anschrift und Steuernummer
     * kommen aus derselben Quelle wie bei den Rechnungen zu Bestellungen.
     */
    private function party(Settlement $settlement, Tenant $buyer): Buyer
    {
        $customFields = [];

        $email = $this->email($buyer);

        if ($email !== null) {
            $customFields['email'] = $email;
        }

        $customFields[(string) __('marketplace.wallet.settlement_invoice.fields.period')] = $this->periodLabel($settlement);
        $customFields[(string) __('marketplace.wallet.settlement_invoice.fields.method')] = $this->methodLabel($settlement);

        return new Buyer([
            'name' => $buyer->name,
            'custom_fields' => $this->invoiceService->addAddressInfo($buyer, $customFields),
        ]);
    }

    /**
     * Die Buchungen des Kaeufer-Wallets im Abrechnungszeitraum, aelteste
     * zuerst. Gutschriften aus Aufladung und dem Eingang frueherer Einzuege
     * stehen bewusst nicht dabei -- sie sind keine Leistung, die berechnet
     * wird, und gehen in die Ausgleichszeile ein.
     *
     * @return EloquentCollection<int, WalletTransaction>
     */
    private function movements(Settlement $settlement): EloquentCollection
    {
        $start = $this->periodStart($settlement);

        /** @var EloquentCollection<int, WalletTransaction> $movements */
        $movements = WalletTransaction::query()
            ->where('wallet_id', $settlement->wallet_id)
            ->whereIn('type', [
                WalletTransactionType::CAPTURE->value,
                WalletTransactionType::REFUND->value,
                WalletTransactionType::FEE->value,
            ])
            ->where('created_at', '<=', $this->periodEnd($settlement))
            ->when($start !== null, fn ($query) => $query->where('created_at', '>', $start))
            ->orderBy('id')
            ->with('reference')
            ->get();

        $purchases = $movements
            ->map(static fn (WalletTransaction $movement) => $movement->reference)
            ->filter(static fn ($reference): bool => $reference instanceof LeadPurchase);

        if ($purchases->isNotEmpty()) {
            (new EloquentCollection($purchases->all()))->loadMissing('lead.funnel');
        }

        return $movements;
    }

    /**
     * Eine Buchung als Belegposition. Betraege stehen aus Sicht des Kaeufers:
     * positiv, was er schuldet, negativ, was ihm gutgeschrieben wurde.
     *
     * @return array{title: string, description: string, amount_cents: int}|null
     */
    private function positionFor(WalletTransaction $movement): ?array
    {
        $amountCents = -1 * (int) $movement->amount_cents;

        if ($amountCents === 0) {
            return null;
        }

        $purchase = $movement->reference instanceof LeadPurchase ? $movement->reference : null;

        if ($purchase === null) {
            return [
                'title' => (string) $movement->description,
                'description' => '',
                'amount_cents' => $amountCents,
            ];
        }

        $funnel = $purchase->lead?->funnel?->name;

        return [
            'title' => (string) __('marketplace.wallet.settlement_invoice.items.lead', [
                'lead' => (string) $purchase->lead_id,
            ]),
            'description' => $this->purchaseDescription($purchase, $funnel, $movement->type),
            'amount_cents' => $amountCents,
        ];
    }

    /**
     * Der Zusatz einer Leadzeile: Funnel, Kaufdatum und die Aufteilung in
     * Leadpreis und Aufschlag.
     */
    private function purchaseDescription(LeadPurchase $purchase, ?string $funnel, WalletTransactionType $type): string
    {
        $pieces = [];

        if ($funnel !== null && $funnel !== '') {
            $pieces[] = (string) __('marketplace.wallet.settlement_invoice.items.funnel', ['funnel' => $funnel]);
        }

        if ($purchase->captured_at !== null) {
            $pieces[] = (string) __('marketplace.wallet.settlement_invoice.items.captured_at', [
                'date' => $purchase->captured_at->translatedFormat((string) config('app.date_format')),
            ]);
        }

        if ((int) $purchase->surcharge_cents > 0) {
            $pieces[] = (string) __('marketplace.wallet.settlement_invoice.items.split', [
                'price' => Money::format((int) $purchase->price_cents),
                'surcharge' => Money::format((int) $purchase->surcharge_cents),
            ]);
        }

        if ($type === WalletTransactionType::REFUND) {
            $pieces[] = (string) __('marketplace.wallet.settlement_invoice.items.refund');
        }

        return implode(' · ', $pieces);
    }

    /**
     * Der Hinweisblock unter den Positionen: Zeitraum, Zahlungsmittel und der
     * Satz, dass nichts mehr zu tun ist -- der Betrag ist bereits eingezogen.
     */
    private function notes(Settlement $settlement): string
    {
        $percent = (int) config('wallet.topup_vat_percent');

        $notes = [
            (string) __('marketplace.wallet.settlement_invoice.notes.paid', [
                'date' => ($settlement->paid_at ?? $this->periodEnd($settlement))
                    ->translatedFormat((string) config('app.date_format')),
                'method' => $this->methodLabel($settlement),
            ]),
        ];

        if ($percent > 0) {
            $notes[] = (string) __('marketplace.wallet.settlement_invoice.notes.vat', ['percent' => $percent]);
        }

        return implode("\n", $notes);
    }

    /**
     * Serie der Belegnummer samt Jahr des Einzugs.
     */
    private function series(Settlement $settlement): string
    {
        $series = (string) config('wallet.postpaid.settlement_invoice_series', 'ABR');

        return $series.'-'.$this->periodEnd($settlement)->format('Y');
    }

    /**
     * Pfad ohne Endung -- so erwartet ihn das Rechnungspaket.
     */
    private function basePath(Settlement $settlement): string
    {
        return sprintf(
            '%s/settlements/%s/%s',
            (string) config('invoices.path'),
            $this->periodEnd($settlement)->format('Y/m'),
            $this->reference($settlement),
        );
    }

    /**
     * Haelt die Belegnummer am Settlement fest, ohne Beobachter erneut
     * auszuloesen -- der Beleg entsteht selbst aus einem Beobachter.
     */
    private function rememberReference(Settlement $settlement, string $reference): void
    {
        if ($settlement->invoice_reference === $reference) {
            return;
        }

        $settlement->invoice_reference = $reference;
        $settlement->saveQuietly();
    }

    private function periodLabel(Settlement $settlement): string
    {
        $format = (string) config('app.date_format');
        $start = $this->periodStart($settlement);
        $end = $this->periodEnd($settlement);

        if ($start === null) {
            return (string) __('marketplace.wallet.settlement_invoice.period_until', [
                'end' => $end->translatedFormat($format),
            ]);
        }

        return (string) __('marketplace.wallet.settlement_invoice.period', [
            'start' => $start->translatedFormat($format),
            'end' => $end->translatedFormat($format),
        ]);
    }

    /**
     * Wie eingezogen wurde -- Lastschrift oder Karte, jeweils mit den letzten
     * Stellen. Fehlt das Zahlungsmittel, weil der Kaeufer es entfernt hat,
     * bleibt es bei der allgemeinen Angabe.
     */
    private function methodLabel(Settlement $settlement): string
    {
        $method = $settlement->paymentMethod;

        if ($method === null) {
            return (string) __('marketplace.wallet.settlement_invoice.method_unknown');
        }

        return $method->label();
    }

    /**
     * Kontaktadresse des Kaeufers, wie bei den uebrigen Wallet-Meldungen: der
     * erste Benutzer des Mandanten.
     */
    private function email(Tenant $buyer): ?string
    {
        $user = $buyer->users()->first();

        if (! $user instanceof User || ! is_string($user->email) || $user->email === '') {
            return null;
        }

        return $user->email;
    }
}
