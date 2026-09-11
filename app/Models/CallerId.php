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
     * Bestaetigt und nicht abgelaufen -- nur dann darf die Nummer als
     * Rufnummernanzeige verwendet werden (FB-081).
     */
    public function isUsableAsCallerId(): bool
    {
        return $this->status->isVerified();
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
