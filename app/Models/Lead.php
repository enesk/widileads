<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\LeadContactStatus;
use App\Constants\LeadResolutionReason;
use App\Constants\LeadState;
use App\Dto\LeadContact;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScopes;
use App\Services\LeadContactResolver;
use App\Services\LeadPurchaseLookup;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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
 * @property string $uuid
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
 * @property string|null $postal_code
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
 * @property Carbon|null $delivered_at
 * @property Carbon|null $deadline_at
 * @property LeadContactStatus $contact_status
 * @property Carbon|null $resolved_at
 * @property LeadResolutionReason|null $resolved_by
 * @property Carbon|null $phone_revealed_at
 * @property Carbon|null $reminder_sent_at Gesetzt heisst: die Erinnerung vor Fristende ist raus (FB-084).
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
     * Ein frisch gebauter Lead ist erreichbarkeitsseitig offen -- derselbe
     * Wert, den auch die Datenbank setzt. So ist contact_status nie null und
     * isOpen() auch vor dem ersten Speichern beantwortbar.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'contact_status' => 'open',
    ];

    /**
     * Die Rufnummer verlaesst das Model nie ueber eine Serialisierung.
     *
     * $hidden wirkt auf toArray() und toJson() -- und damit auf Livewire, auf
     * API-Resources und auf jedes versehentliche dd($lead) im Frontend. Wer
     * die Nummer wirklich braucht, holt sie ueber contactFor() -- oder, wenn es
     * ausdruecklich um die Freigabe nach Abrechnung geht, ueber
     * revealedPhone() bzw. maskedPhone() (FB-085); ein direkter Spaltenzugriff
     * bleibt technisch moeglich, faellt aber im Architektur-Test auf.
     *
     * @var list<string>
     */
    protected $hidden = [
        'phone_e164',
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
     * Alle Anrufversuche zu diesem Lead, neueste zuerst (FB-082).
     *
     * Ueber alle Kaeufer hinweg: Bei einem geteilten Lead (FB-055) ruft jeder
     * Kaeufer fuer sich an, die Frist gilt aber dem Lead als Ganzem.
     *
     * @return HasMany<CallAttempt, $this>
     */
    public function callAttempts(): HasMany
    {
        return $this->hasMany(CallAttempt::class)->latest('created_at');
    }

    /**
     * Die gueltigen erfolglosen Versuche, aelteste zuerst.
     *
     * Die Menge, aus der das Regelwerk (FB-083) Zahl und Zeitspanne der
     * Versuche liest. Verworfene Versuche -- etwa unter dem Mindestabstand --
     * sind hier bewusst nicht dabei.
     *
     * @return HasMany<CallAttempt, $this>
     */
    public function validFailedAttempts(): HasMany
    {
        return $this->hasMany(CallAttempt::class)->validFailed();
    }

    /**
     * Laeuft die Erreichbarkeitspruefung noch?
     *
     * Nur dann darf angerufen werden und nur dann zaehlt ein Versuch. Ist der
     * Lead bereits entschieden, aendert kein weiterer Anruf etwas daran.
     */
    public function isOpen(): bool
    {
        return ! $this->contact_status->isResolved();
    }

    /**
     * Ist die Frist abgelaufen? Ohne Auslieferung laeuft keine Frist.
     */
    public function isPastCallDeadline(): bool
    {
        return $this->deadline_at !== null && $this->deadline_at->isPast();
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
     * Kontaktdaten aus Sicht eines Workspaces (FB-030d).
     *
     * Ueber die API fragt das Token eines Tenants, nicht ein Benutzer.
     */
    public function contactForTenant(?Tenant $tenant): LeadContact
    {
        return app(LeadContactResolver::class)->forTenant($this, $tenant);
    }

    /**
     * Die Rufnummer in der Fassung, die ein Kaeufer vor der Abrechnung sieht
     * (FB-085): `+49 171 ***** 67`.
     *
     * Maskiert wird nicht hier, sondern in App\Dto\LeadContact
     * (Architekturleitsatz 5) -- diese Methode ist nur der bequeme Zugang vom
     * Lead aus.
     */
    public function maskedPhone(): ?string
    {
        return LeadContact::maskPhoneToLastDigits($this->phone_e164);
    }

    /**
     * Ist die Rufnummer fuer den Kaeufer freigegeben? (FB-085)
     *
     * Genau ein Fall gibt sie frei: Die Erreichbarkeitspruefung ist mit
     * `billable` zu Ende gegangen, der Lead wird also abgerechnet. Bei
     * `unreachable` bleibt sie dauerhaft verdeckt -- der Kaeufer bekommt eine
     * Gutschrift und keine Nummer.
     */
    public function isPhoneReleased(): bool
    {
        return $this->contact_status === LeadContactStatus::BILLABLE;
    }

    /**
     * Die volle Rufnummer fuer einen Kaeufer -- oder null (FB-085).
     *
     * Zwei Bedingungen, beide zwingend: Der Lead ist abgerechnet
     * (`contact_status = billable`) und der fragende Mandant hat ihn gekauft.
     * Fehlt eine davon, kommt null zurueck; die verdeckte Fassung holt sich der
     * Aufrufer ueber maskedPhone().
     *
     * Beim ersten erfolgreichen Abruf wird `phone_revealed_at` gesetzt. Der
     * Zeitstempel ist der Beleg dafuer, wann der Kaeufer die Nummer bekommen
     * hat -- er wird nie ueberschrieben.
     */
    public function revealedPhone(Tenant $buyer): ?string
    {
        if (! $this->isPhoneReleased()) {
            return null;
        }

        if (! app(LeadPurchaseLookup::class)->hasPurchased($buyer, $this)) {
            return null;
        }

        $phone = $this->phone_e164;

        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $this->markPhoneRevealed();

        return $phone;
    }

    /**
     * Haelt fest, dass die Rufnummer einem Kaeufer ausgeliefert wurde.
     *
     * Nur beim ersten Mal und ohne Umweg ueber das Model: Ein save() wuerde
     * andere Aenderungen mitschreiben, die zufaellig am Objekt haengen. Der
     * Mandanten-Scope muss dabei weichen -- der Lead gehoert dem Betreiber, die
     * Freigabe passiert aber im Kontext des Kaeufers.
     */
    public function markPhoneRevealed(): void
    {
        if ($this->phone_revealed_at !== null) {
            return;
        }

        $now = Carbon::now();

        static::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->whereKey($this->getKey())
            ->whereNull('phone_revealed_at')
            ->update(['phone_revealed_at' => $now]);

        $this->setAttribute('phone_revealed_at', $now);
        $this->syncOriginalAttribute('phone_revealed_at');
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

    protected static function booted(): void
    {
        static::creating(function (self $lead): void {
            if (($lead->uuid ?? '') === '') {
                $lead->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lead_state' => LeadState::class,
            'contact_status' => LeadContactStatus::class,
            'resolved_by' => LeadResolutionReason::class,
            'score' => 'integer',
            'price_at_creation' => 'decimal:2',
            'settled_price' => 'decimal:2',
            'settled_at' => 'datetime',
            'delivered_at' => 'datetime',
            'deadline_at' => 'datetime',
            'resolved_at' => 'datetime',
            'phone_revealed_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }
}
