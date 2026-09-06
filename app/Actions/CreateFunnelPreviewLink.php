<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Funnel;
use Illuminate\Support\Facades\URL;

/**
 * Erzeugt einen signierten, befristeten Vorschau-Link (FB-018).
 *
 * Der Link ist die Zugangskontrolle: Wer ihn hat, sieht die Strecke -- auch
 * ohne Anmeldung und auch dann, wenn der Funnel noch nie veroeffentlicht wurde.
 * Deshalb ist er befristet; die Dauer steht in
 * `config('funnel.preview.link_ttl_minutes')`.
 */
class CreateFunnelPreviewLink
{
    public function handle(Funnel $funnel): string
    {
        return URL::temporarySignedRoute(
            'funnel.preview',
            now()->addMinutes((int) config('funnel.preview.link_ttl_minutes')),
            ['token' => $funnel->public_token],
        );
    }
}
