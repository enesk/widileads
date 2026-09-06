<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\LeadState;
use App\Dto\LeadContact;
use App\Models\Concerns\BelongsToTenant;
use App\Services\LeadContactResolver;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property int|null $funnel_id
 * @property int|null $funnel_version_id
 * @property int|null $public_session_id
 * @property int|null $duplicate_of_lead_id
 * @property-read Funnel|null $funnel
 * @property-read FunnelVersion|null $funnelVersion
 * @property-read PublicSession|null $publicSession Null, sobald die Sitzung aufgeraeumt wurde.
 * @property-read Lead|null $duplicateOf
 * @property LeadState $lead_state
 * @property int $score
 * @property string|null $result_key
 * @property-read string|null $price_at_creation
 * @property-write float|string|null $price_at_creation
 * @property string|null $phone_e164
 * @property string|null $email_normalized
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $utm_term
 * @property string|null $utm_content
 * @property string|null $referrer
 * @property string|null $embed_origin
 * @property string|null $ip_hash
 * @property string|null $user_agent
 * @property-read string|null $settled_price Der Cast decimal:2 liefert beim Lesen einen String,
 *                                            beim Schreiben ist jeder numerische Wert erlaubt.
 * @property-write float|string|null $settled_price
 * @property Carbon|null $settled_at
 * @property Carbon|null $anonymized_at Gesetzt heisst: der Personenbezug wurde entfernt (FB-037).
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
     * @return BelongsTo<Funnel, $this>
     */
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    /**
     * Die Fassung, die der Endkunde gesehen hat -- nicht der aktuelle Stand des
     * Funnels. Zusammen mit result_key ist der Lead damit dauerhaft erklaerbar.
     *
     * @return BelongsTo<FunnelVersion, $this>
     */
    public function funnelVersion(): BelongsTo
    {
        return $this->belongsTo(FunnelVersion::class);
    }

    /**
     * Die Sitzung, aus der der Lead entstanden ist. Sie ist die alleinige Quelle
     * fuer Verlauf und Zeitstempel (FB-021) -- der Lead fuehrt sie nicht doppelt.
     *
     * @return BelongsTo<PublicSession, $this>
     */
    public function publicSession(): BelongsTo
    {
        return $this->belongsTo(PublicSession::class);
    }

    /**
     * Frueherer Lead mit denselben Kontaktdaten, falls FB-023 einen gefunden
     * hat. Ein Verweis, kein Urteil -- entschieden wird im Pruefjob (FB-033).
     *
     * @return BelongsTo<Lead, $this>
     */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_lead_id');
    }

    /**
     * Rohantworten, Feldschluessel je einmal.
     *
     * @return HasMany<LeadAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(LeadAnswer::class);
    }

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
     * Kontaktdaten dieses Leads aus Sicht eines Betrachters -- im Klartext
     * oder verdeckt (FB-032).
     *
     * Der einzige Weg, an Kontaktdaten eines Leads zu kommen. Views und
     * API-Resources rufen ausschliesslich das auf; wer stattdessen
     * `email_normalized` oder eine Antwort direkt ausgibt, umgeht die
     * Maskierung -- der Architektur-Test aus FB-042 schlaegt darauf an.
     */
    public function contactFor(?User $viewer): LeadContact
    {
        return app(LeadContactResolver::class)->for($this, $viewer);
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
            'score' => 'integer',
            'price_at_creation' => 'decimal:2',
            'settled_price' => 'decimal:2',
            'settled_at' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }
}
