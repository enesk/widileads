<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\PhoneVerificationChannel;
use App\Constants\PhoneVerificationStatus;
use Database\Factories\PhoneVerificationAttemptFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ein Versuch, eine Rufnummer zu bestaetigen (LP-ONBOARD-004).
 *
 * Protokoll, nicht Zustand: Die Zeile sagt, dass ein Code angefordert wurde
 * und was Twilio dazu gemeldet hat. Der Code selbst liegt bei Twilio und
 * taucht hier nirgends auf.
 *
 * Geschrieben wird ausschliesslich im App\Services\Phone\PhoneVerificationService.
 * Jede andere Schreibstelle wuerde die Rate-Limits verfaelschen -- sie zaehlen
 * genau diese Zeilen.
 *
 * Es gibt kein `updated_at`: Die Zeile wird nach dem Anlegen nur noch einmal
 * angefasst, naemlich wenn die Pruefung sie auf `approved` setzt.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $phone_e164
 * @property PhoneVerificationChannel $channel
 * @property string|null $verification_sid
 * @property PhoneVerificationStatus $status
 * @property Carbon|null $cleared_at
 * @property Carbon|null $created_at
 */
class PhoneVerificationAttempt extends Model
{
    /** @use HasFactory<PhoneVerificationAttemptFactory> */
    use HasFactory;

    /**
     * Kein `updated_at` -- siehe Migration.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'phone_e164',
        'channel',
        'verification_sid',
        'status',
    ];

    /**
     * Versuche, die gegen ein Limit zaehlen: Es ging wirklich ein Code raus,
     * und der Support hat die Zeile nicht aus dem Zaehler genommen.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeCounting(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', PhoneVerificationStatus::FAILED)
            ->whereNull('cleared_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => PhoneVerificationChannel::class,
            'status' => PhoneVerificationStatus::class,
            'cleared_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
