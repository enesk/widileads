<?php

declare(strict_types=1);

namespace App\Funnel\Runtime;

use App\Constants\FunnelFieldKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sucht einen frueheren Lead mit denselben Kontaktdaten (FB-023).
 *
 * Gefunden wird nur -- entschieden wird nichts: Der neue Lead entsteht in jedem
 * Fall und traegt lediglich einen Verweis auf den mutmasslichen Vorgaenger.
 * Ueber Dublette oder Neuanfrage entscheidet der Pruefjob in FB-033, der den
 * zeitlichen Abstand und den Funnel mitbeurteilen kann.
 *
 * Die dafuer noetigen Spalten (`email_normalized`, `phone_e164`, `funnel_id`)
 * legt FB-031 an. Solange sie fehlen, liefert der Finder nichts, statt die
 * Strecke mit einem SQL-Fehler abzubrechen.
 */
class DuplicateLeadFinder
{
    /**
     * Erforderliche Spalten an `leads`. Fehlt eine, ist die Pruefung noch nicht
     * moeglich.
     */
    private const REQUIRED_COLUMNS = ['funnel_id', 'email_normalized', 'phone_e164', 'created_at'];

    /**
     * @param  array<string, mixed>  $answers
     */
    public function findRecentDuplicate(?int $funnelId, array $answers): ?int
    {
        if ($funnelId === null || ! $this->leadsTableIsReady()) {
            return null;
        }

        $email = $this->answerFor($answers, FunnelFieldKey::EMAIL);
        $phone = $this->answerFor($answers, FunnelFieldKey::TELEFON);

        if ($email === null && $phone === null) {
            return null;
        }

        $since = now()->subDays((int) config('funnel.public.duplicate_window_days'));

        $duplicate = DB::table('leads')
            ->where('funnel_id', $funnelId)
            ->where('created_at', '>=', $since)
            ->where(function ($query) use ($email, $phone): void {
                if ($email !== null) {
                    $query->orWhere('email_normalized', $email);
                }

                if ($phone !== null) {
                    $query->orWhere('phone_e164', $phone);
                }
            })
            ->orderByDesc('created_at')
            ->value('id');

        return $duplicate === null ? null : (int) $duplicate;
    }

    /**
     * Sind die Spalten aus FB-031 schon da?
     */
    public function leadsTableIsReady(): bool
    {
        if (! Schema::hasTable('leads')) {
            return false;
        }

        return Schema::hasColumns('leads', self::REQUIRED_COLUMNS);
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function answerFor(array $answers, FunnelFieldKey $fieldKey): ?string
    {
        $value = $answers[$fieldKey->value] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
