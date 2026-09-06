<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lead;
use Illuminate\Support\Facades\Schema;

/**
 * Entfernt den Personenbezug eines Leads (FB-037).
 *
 * Die einzige Stelle, an der ein Lead anonymisiert wird -- auch das
 * Loeschersuchen aus FB-038 kommt hier durch. Was verschwindet, sind die
 * personenbezogenen Angaben; was bleibt, sind Zaehl- und Preisdaten
 * (`lead_state`, `settled_price`, `settled_at`, `created_at`), damit
 * Auswertungen und Abrechnungen der Vergangenheit stimmig bleiben.
 *
 * Erweiterung durch FB-031: Sobald `leads.phone_e164` und
 * `leads.email_normalized` existieren, werden sie automatisch geleert -- sie
 * stehen bereits in PERSONAL_COLUMNS und werden nur uebersprungen, solange die
 * Spalten fehlen. Die Antworten (`lead_answers`) legt FB-031 ebenfalls erst an;
 * ihr Ueberschreiben gehoert dann hierher, weil dieses Ticket die endgueltige
 * Form der Tabelle kennt. Der Aufbewahrungslauf selbst muss dafuer nicht
 * angefasst werden.
 */
class LeadAnonymizer
{
    /**
     * Spalten auf `leads`, die Personenbezug tragen und beim Anonymisieren
     * geleert werden. Noch nicht existierende Spalten werden uebersprungen.
     *
     * @var list<string>
     */
    private const PERSONAL_COLUMNS = [
        'phone_e164',
        'email_normalized',
    ];

    /**
     * @var list<string>|null Einmal je Instanz aufgeloest, damit ein Lauf ueber
     *                        viele Leads nicht je Lead das Schema abfragt.
     */
    private ?array $resolvedColumns = null;

    /**
     * Anonymisiert einen Lead, sofern er es nicht schon ist.
     *
     * @return bool true, wenn dieser Aufruf den Lead anonymisiert hat.
     */
    public function anonymize(Lead $lead): bool
    {
        if ($lead->anonymized_at !== null) {
            return false;
        }

        $values = ['anonymized_at' => now()];

        foreach ($this->personalColumns() as $column) {
            $values[$column] = null;
        }

        $lead->forceFill($values)->save();

        return true;
    }

    /**
     * Die personenbezogenen Spalten, die es aktuell wirklich gibt.
     *
     * @return list<string>
     */
    private function personalColumns(): array
    {
        if ($this->resolvedColumns !== null) {
            return $this->resolvedColumns;
        }

        return $this->resolvedColumns = array_values(array_filter(
            self::PERSONAL_COLUMNS,
            static fn (string $column): bool => Schema::hasColumn('leads', $column),
        ));
    }
}
