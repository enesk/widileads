<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Der Weg, auf dem der Bestaetigungscode beim Nutzer ankommt (LP-ONBOARD-004).
 *
 * Der Case-Wert steht so in `users.phone_verification_channel` und in
 * `phone_verification_attempts.channel` und ist zugleich der Kanalname, den
 * Twilio Verify erwartet ("sms", "call") -- deshalb darf er nicht uebersetzt
 * oder umbenannt werden.
 *
 * Die Reihenfolge ist Fachlogik: SMS ist der Regelweg, der Anruf der Ausweg
 * fuer Nummern, bei denen keine SMS ankommt (Festnetz, Weiterleitung,
 * Mailbox). Der Anruf wird erst angeboten, nachdem eine SMS versucht wurde --
 * sonst waere er die bequemere Variante fuer jeden und Twilio rechnet ihn
 * teurer ab.
 */
enum PhoneVerificationChannel: string
{
    case SMS = 'sms';

    case CALL = 'call';

    /**
     * Der Regelweg. Steht als Standard in start().
     */
    public static function default(): self
    {
        return self::SMS;
    }

    /**
     * Darf dieser Kanal erst nach einem SMS-Versuch angeboten werden?
     */
    public function requiresPreviousSmsAttempt(): bool
    {
        return $this === self::CALL;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $channel): string => $channel->value, self::cases());
    }
}
