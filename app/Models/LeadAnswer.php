<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\FunnelFieldKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eine Rohantwort eines Leads (FB-031).
 *
 * Gespeichert wird, was der Endkunde geantwortet hat -- bereits normalisiert
 * durch die Fragetyp-Handler, aber sonst unveraendert (Architekturleitsatz 3:
 * Beweis vor Bewertung). Der Wert liegt als JSON, weil eine Mehrfachauswahl
 * eine Liste ist und eine Zahl eine Zahl bleiben soll.
 *
 * Ein `value` von null bedeutet: Der Personenbezug wurde entfernt (FB-037).
 *
 * @property int $id
 * @property int $lead_id
 * @property string $field_key
 * @property mixed $value
 * @property Carbon|null $created_at
 */
class LeadAnswer extends Model
{
    protected $table = 'lead_answers';

    public const UPDATED_AT = null;

    protected $fillable = [
        'lead_id',
        'field_key',
        'value',
    ];

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Traegt diese Antwort einen Personenbezug? Entscheidend sind die
     * reservierten Kontakt-Feldschluessel aus FB-010.
     */
    public function isPersonal(): bool
    {
        return FunnelFieldKey::isReserved($this->field_key);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
