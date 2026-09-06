<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\FunnelFieldKey;
use App\Models\Lead;
use App\Models\LeadAnswer;
use Illuminate\Support\Facades\DB;

/**
 * Entfernt den Personenbezug eines Leads (FB-037, vervollstaendigt in FB-031).
 *
 * Die einzige Stelle, an der ein Lead anonymisiert wird -- auch das
 * Loeschersuchen aus FB-038 kommt hier durch. Was verschwindet, sind die
 * personenbezogenen Angaben; was bleibt, sind Zaehl- und Preisdaten
 * (`lead_state`, `settled_price`, `settled_at`, `created_at`), damit
 * Auswertungen und Abrechnungen der Vergangenheit stimmig bleiben.
 *
 * Anonymisiert wird an zwei Stellen:
 *
 * 1. Die Kontaktspalten des Leads (`phone_e164`, `email_normalized`).
 * 2. Die Rohantworten: Der Wert jeder Antwort auf einen reservierten
 *    Kontakt-Feldschluessel (Vorname, Nachname, Name, E-Mail, Telefon, PLZ,
 *    Einwilligung) wird auf null gesetzt. Die Zeile bleibt stehen -- wer
 *    spaeter zaehlt, wie viele Anfragen eine Telefonnummer enthielten, soll
 *    weiterhin zaehlen koennen; nur die Nummer selbst ist weg.
 *
 * Fachliche Antworten (Tierart, Alter, Vorerkrankungen) bleiben unangetastet:
 * Sie sind nach der Anonymisierung keiner Person mehr zuzuordnen und tragen die
 * Auswertung des Funnels.
 */
class LeadAnonymizer
{
    /**
     * Spalten auf `leads`, die Personenbezug tragen und beim Anonymisieren
     * geleert werden.
     *
     * @var list<string>
     */
    private const PERSONAL_COLUMNS = [
        'phone_e164',
        'email_normalized',
    ];

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

        return DB::transaction(function () use ($lead): bool {
            $values = ['anonymized_at' => now()];

            foreach (self::PERSONAL_COLUMNS as $column) {
                $values[$column] = null;
            }

            $lead->forceFill($values)->save();

            $this->clearPersonalAnswers($lead);

            return true;
        });
    }

    /**
     * Leert die Werte aller Antworten mit Personenbezug -- ohne die Zeilen zu
     * loeschen.
     */
    private function clearPersonalAnswers(Lead $lead): void
    {
        LeadAnswer::query()
            ->where('lead_id', $lead->getKey())
            ->whereIn('field_key', FunnelFieldKey::values())
            ->update(['value' => null]);
    }
}
