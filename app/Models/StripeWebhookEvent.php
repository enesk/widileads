<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Ein bereits verarbeitetes Stripe-Ereignis (LP-POSTPAID-005).
 *
 * Die Zeile ist eine Quittung und kein Datensatz mit Inhalt: Sie sagt, dass
 * die Ereigniskennung schon durch ist. Stripe liefert jedes Ereignis
 * mindestens einmal, und ein zweites Mal verarbeiteter Mandatswiderruf wuerde
 * die Rueckstufung ein zweites Mal anstossen.
 *
 * @property int $id
 * @property string $event_id
 * @property string $type
 * @property Carbon|null $processed_at
 */
class StripeWebhookEvent extends Model
{
    protected $fillable = [
        'event_id',
        'type',
        'processed_at',
    ];

    /**
     * Merkt die Ereigniskennung vor und sagt, ob sie neu war.
     *
     * `false` heisst: schon verarbeitet, der Aufrufer soll nichts tun. Die
     * Entscheidung faellt am Unique-Index und nicht an einer vorgelagerten
     * Abfrage -- zwei gleichzeitig eintreffende Zustellungen desselben
     * Ereignisses wuerden zwischen "gibt es nicht" und "anlegen" beide
     * durchkommen.
     */
    public static function claim(string $eventId, string $type): bool
    {
        try {
            self::query()->create([
                'event_id' => $eventId,
                'type' => $type,
                'processed_at' => now(),
            ]);
        } catch (QueryException $exception) {
            // 23000 ist die Verletzung einer Eindeutigkeitsbedingung. Jede
            // andere Ursache (Tabelle fehlt, Verbindung weg) darf nicht als
            // "schon verarbeitet" durchgehen, sonst verschwindet ein Ereignis
            // stillschweigend.
            if ((string) $exception->getCode() === '23000') {
                return false;
            }

            throw $exception;
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
