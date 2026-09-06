<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\LeadState;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Ein Lead: eine vollstaendige Funnel-Anfrage eines Endkunden (FB-030).
 *
 * Dieses Grundgeruest traegt nur den Zustand und die Abrechnungsgrundlage. Die
 * fachlichen Daten -- Funnel, Version, Antworten, Score, Ergebnis und
 * Kontaktdaten -- kommen in FB-031 dazu, sobald das Funnel-Schema (FB-010) und
 * diese Zustandsmaschine beide stehen.
 *
 * Zwei Regeln gelten fuer jeden, der dieses Model benutzt:
 *
 * 1. `lead_state` ist die einzige Zustandsspalte (Architekturleitsatz 1) und
 *    steht in $guarded. Der Zustand wechselt ausschliesslich ueber
 *    App\Services\LeadStateService::transition() (Architekturleitsatz 2) --
 *    ein direktes update(['lead_state' => ...]) ist ein Bug.
 * 2. `settled_price` und `settled_at` schreibt derselbe Dienst genau einmal,
 *    beim Eintritt in einen Endzustand, und danach nie wieder
 *    (Architekturleitsatz 4). Auch sie stehen deshalb in $guarded.
 *
 * Der Mandantenbezug kommt ueber BelongsToTenant (FB-010): mit gesetztem
 * Mandantenkontext sieht jede Abfrage nur dessen Leads.
 *
 * @property int $id
 * @property int $tenant_id
 * @property LeadState $lead_state
 * @property-read string|null $settled_price Der Cast decimal:2 liefert beim Lesen einen String,
 *                                            beim Schreiben ist jeder numerische Wert erlaubt.
 * @property-write float|string|null $settled_price
 * @property Carbon|null $settled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Bewusst $guarded statt $fillable: die drei geschuetzten Spalten sind die
     * Aussage des Tickets, alles Weitere (FB-031) soll ohne Aenderung an dieser
     * Liste befuellbar bleiben.
     *
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'lead_state',
        'settled_price',
        'settled_at',
    ];

    /**
     * Unveraenderliches Protokoll aller Zustandswechsel, aeltester Eintrag zuerst.
     *
     * @return HasMany<LeadStateLog, $this>
     */
    public function stateLog(): HasMany
    {
        return $this->hasMany(LeadStateLog::class)->oldest('id');
    }

    /**
     * Steht der Lead in einem Endzustand?
     */
    public function isInFinalState(): bool
    {
        return $this->lead_state->isFinal();
    }

    /**
     * Ist der Preis bereits festgeschrieben? Danach wird er nie wieder geaendert.
     */
    public function isSettled(): bool
    {
        return $this->settled_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lead_state' => LeadState::class,
            'settled_price' => 'decimal:2',
            'settled_at' => 'datetime',
        ];
    }
}
