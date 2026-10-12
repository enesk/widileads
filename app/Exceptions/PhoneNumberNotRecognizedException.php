<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Die Eingabe ist keine waehlbare Rufnummer (LP-ONBOARD-004).
 *
 * Geworfen bei allem, was libphonenumber nicht zu einer E.164-Nummer machen
 * kann: Tippfehler, zu kurze Nummern, Nummern aus einem Land, in dem wir keine
 * Kaeufer erwarten. Die Meldung ist bewusst dieselbe fuer alle Faelle -- der
 * Nutzer soll seine Schreibweise pruefen und nicht raten, welche Laender wir
 * zulassen.
 */
class PhoneNumberNotRecognizedException extends RuntimeException
{
    public static function make(): self
    {
        return new self(__('portal.phone_verification.errors.unrecognized'), Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
