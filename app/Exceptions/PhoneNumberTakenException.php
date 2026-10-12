<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Die Nummer ist bereits bei einem anderen Konto bestaetigt (LP-ONBOARD-004).
 *
 * Eine Nummer gehoert genau einem Konto. Sonst waere die Rufnummernanzeige des
 * Click-to-Call kein Nachweis mehr: Zwei Kaeufer wuerden mit derselben Nummer
 * anrufen, und der Lead koennte den Rueckruf nicht zuordnen.
 *
 * Die Meldung sagt bewusst nicht, welches Konto es ist -- das waere eine
 * Auskunft ueber einen anderen Kunden, und mit einer Liste von Nummern liesse
 * sich so eine Kundenliste zusammentragen.
 */
class PhoneNumberTakenException extends RuntimeException
{
    public static function make(): self
    {
        return new self(__('portal.phone_verification.errors.taken'), Response::HTTP_CONFLICT);
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
