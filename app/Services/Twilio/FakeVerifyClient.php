<?php

declare(strict_types=1);

namespace App\Services\Twilio;

use App\Constants\PhoneVerificationChannel;
use Illuminate\Support\Str;

/**
 * Rufnummernbestaetigung ohne Twilio (LP-ONBOARD-004).
 *
 * Fuer lokale Entwicklung, Staging und Tests: Es geht keine SMS raus, der
 * Versand steht im Log, und genau ein Code wird akzeptiert
 * (`phone_verification.fake_code`, Vorgabe 000000).
 *
 * In Produktion darf diese Klasse nicht zum Einsatz kommen. Dafuer sorgt nicht
 * sie selbst, sondern die Bindung im AppServiceProvider -- eine Attrappe, die
 * sich selbst fuer unbedenklich erklaert, ist keine Absicherung.
 */
class FakeVerifyClient implements VerifyClient
{
    public function startVerification(string $phoneE164, PhoneVerificationChannel $channel): string
    {
        $sid = 'VEfake'.Str::lower(Str::random(26));

        logger()->info('Rufnummernbestaetigung (Attrappe): Code waere versandt.', [
            'phone_e164' => $phoneE164,
            'channel' => $channel->value,
            'verification_sid' => $sid,
            'code' => $this->expectedCode(),
        ]);

        return $sid;
    }

    public function checkVerification(string $phoneE164, string $code): bool
    {
        return hash_equals($this->expectedCode(), trim($code));
    }

    private function expectedCode(): string
    {
        return (string) config('phone_verification.fake_code', '000000');
    }
}
