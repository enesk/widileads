<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\WebhookEvent;
use Database\Factories\FunnelWebhookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Ein Ereignis-Abonnement eines Funnels (FB-030e).
 *
 * Das Secret liegt verschluesselt in der Datenbank und erscheint nur einmal in
 * einer Antwort: beim Anlegen und beim Erneuern. Wer es hat, kann Ereignisse
 * faelschen, die der Empfaenger fuer echt haelt -- deshalb wird es nicht
 * nachtraeglich wieder ausgegeben.
 *
 * @property int $id
 * @property int $funnel_id
 * @property string $url
 * @property string $secret
 * @property list<string> $events
 * @property bool $active
 * @property Carbon|null $last_delivery_at
 */
class FunnelWebhook extends Model
{
    /** @use HasFactory<FunnelWebhookFactory> */
    use HasFactory;

    protected $fillable = [
        'funnel_id',
        'url',
        'secret',
        'events',
        'active',
        'last_delivery_at',
    ];

    protected $hidden = [
        'secret',
    ];

    /**
     * @return BelongsTo<Funnel, $this>
     */
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'webhook_id')->latest('id');
    }

    public function listensTo(WebhookEvent $event): bool
    {
        return $this->active && in_array($event->value, $this->events, true);
    }

    public static function newSecret(): string
    {
        return 'whsec_'.Str::random(48);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'active' => 'boolean',
            'last_delivery_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $webhook): void {
            if (($webhook->secret ?? '') === '') {
                $webhook->secret = self::newSecret();
            }
        });
    }
}
