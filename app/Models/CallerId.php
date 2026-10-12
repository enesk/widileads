<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\CallerIdStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CallerIdFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Die Rufnummer eines Kaeufer-Mitarbeiters samt Stand ihrer Bestaetigung (FB-080).
 *
 * Der Stand entsteht ausschliesslich in App\Services\CallerIdService -- entweder
 * beim Anfordern der Bestaetigung oder im Rueckruf von Twilio. Deshalb stehen
 * `status`, die Twilio-Kennungen und die Zeitstempel in $guarded: Eine Nummer,
 * die sich selbst bestaetigt, waere genau die Luecke, die die Bestaetigung
 * schliessen soll.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property string $phone_number
 * @property string|null $label
 * @property CallerIdStatus $status
 * @property string|null $validation_sid
 * @property string|null $validation_code
 * @property Carbon|null $requested_at
 * @property Carbon|null $verified_at
 * @property Carbon|null $expires_at
 */
class CallerId extends Model
{
    /** @use HasFactory<CallerIdFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'status',
        'validation_sid',
        'validation_code',
        'requested_at',
        'verified_at',
        'expires_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Darf diese Nummer als Rufnummernanzeige zum Lead gewaehlt werden?
     *
     * Zwei Bedingungen, und die zweite kam mit Ticket #96 dazu: Die Zeile ist
     * bestaetigt UND es gibt einen Validation Request bei Twilio
     * (`validation_sid`). Twilio nimmt als Caller-ID ausschliesslich Nummern
     * an, die dem Konto gehoeren oder dort als Verified Caller ID stehen --
     * eine bei uns gruene Zeile ohne diesen Vorgang fuehrt beim Anruf zu
     * Fehler 21210.
     *
     * Der Unterschied ist nicht theoretisch: Die Bestaetigung im Onboarding
     * laeuft ueber Twilio **Verify** (SMS-Code). Verify beweist, dass der
     * Nutzer die Nummer besitzt, und legt im Konto keinen Caller-ID-Eintrag
     * an. Solange nicht entschieden ist, wie die Luecke geschlossen wird
     * (Ticket #96), gilt eine so uebernommene Nummer hier als nicht
     * anrufbereit -- lieber ein ehrliches "bestaetige zuerst deine Nummer" im
     * Portal als ein Anruf, den Twilio ablehnt.
     *
     * Keine Zusicherung ueber den aktuellen Stand bei Twilio: Wird der
     * Eintrag dort von Hand geloescht, bleibt `validation_sid` hier stehen.
     */
    public function isUsableAsCallerId(): bool
    {
        return $this->status->isVerified() && $this->validation_sid !== null;
    }

    /**
     * Die begonnene Bestaetigung ist verfallen: Der angesagte Code gilt nicht
     * mehr, es muss neu begonnen werden.
     */
    public function hasExpiredValidation(): bool
    {
        return $this->status === CallerIdStatus::PENDING
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CallerIdStatus::class,
            'requested_at' => 'datetime',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
