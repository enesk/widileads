<?php

declare(strict_types=1);

namespace App\Models;

use App\Funnel\Runtime\EmbedOriginPolicy;
use Database\Factories\FunnelOriginFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Seite, die diesen Funnel einbetten darf (FB-025).
 *
 * @property int $id
 * @property int $funnel_id
 * @property string $origin
 */
class FunnelOrigin extends Model
{
    /** @use HasFactory<FunnelOriginFactory> */
    use HasFactory;

    protected $fillable = [
        'funnel_id',
        'origin',
    ];

    /**
     * @return BelongsTo<Funnel, $this>
     */
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    /**
     * Vereinheitlicht die Herkunft beim Speichern auf "schema://host[:port]".
     * Wer "https://beispiel.de/pfotencheck/" eintraegt, meint die Domain --
     * ohne Normalisierung stuende hier ein Eintrag, der nie trifft.
     *
     * @return Attribute<string, string>
     */
    protected function origin(): Attribute
    {
        return Attribute::set(
            fn (string $value): string => app(EmbedOriginPolicy::class)->normalize($value) ?? trim($value),
        );
    }
}
