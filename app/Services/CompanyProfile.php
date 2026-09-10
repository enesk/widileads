<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Config;

/**
 * Die eigenen Firmenstammdaten des Portalbetreibers (FB-Ticket #31).
 *
 * Quelle ist ausschliesslich die Tabelle `configs`, gepflegt ueber die
 * Admin-Seite "Rechnungen" (App\Livewire\Filament\InvoiceSettings). Bewusst
 * NICHT ueber config('invoices.seller.attributes.*') gelesen: dort standen die
 * Platzhalter des Starterkits ("SaaSykit Company Inc.", "SaaSy Street 123"),
 * inzwischen Leerstrings (FB-Ticket #35). Ein Impressum mit erfundener
 * Anschrift ist schlimmer als keins.
 *
 * Solange die Pflichtangaben fehlen, meldet isComplete() false. Daran haengen
 * die Impressum-Seite (404), der Verweis im Portal-Fuss (wird nicht gezeigt)
 * und die Rechnungserzeugung (App\Services\InvoiceService). Sobald die Daten
 * im Admin stehen, greifen alle drei von selbst.
 */
class CompanyProfile
{
    /**
     * Firmenname, genau wie im Register eingetragen.
     */
    public function name(): ?string
    {
        return $this->value('invoices.seller.attributes.name');
    }

    /**
     * Vollstaendige Anschrift. Freitext, darf mehrzeilig sein.
     */
    public function address(): ?string
    {
        return $this->value('invoices.seller.attributes.address');
    }

    /**
     * Firmenkennung, in Deutschland ueblicherweise die Handelsregisternummer.
     */
    public function registrationNumber(): ?string
    {
        return $this->value('invoices.seller.attributes.code');
    }

    /**
     * Umsatzsteuer-Identifikationsnummer.
     */
    public function vatId(): ?string
    {
        return $this->value('invoices.seller.attributes.vat');
    }

    public function phone(): ?string
    {
        return $this->value('invoices.seller.attributes.phone');
    }

    /**
     * Kontaktadresse: die Support-Adresse, sonst der Mailabsender.
     */
    public function email(): ?string
    {
        return $this->value('app.support_email') ?? $this->value('mail.from.address');
    }

    /**
     * Reichen die Angaben fuer ein Impressum nach Paragraf 5 DDG?
     *
     * Verlangt sind Name, ladungsfaehige Anschrift und eine elektronische
     * Kontaktmoeglichkeit. Register- und Steuernummer sind nur zu nennen,
     * sofern vorhanden, und deshalb hier nicht Bedingung.
     */
    public function isComplete(): bool
    {
        return $this->name() !== null
            && $this->address() !== null
            && $this->email() !== null;
    }

    /**
     * Gepflegter Wert aus `configs` oder null -- Leerstrings zaehlen als leer.
     */
    private function value(string $key): ?string
    {
        $value = trim((string) Config::get($key));

        return $value === '' ? null : $value;
    }
}
