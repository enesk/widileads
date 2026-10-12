<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

/**
 * Abmeldung von der Onboarding-Strecke (LP-ONBOARD-014).
 *
 * Nur von dieser Strecke. Bestellbestaetigungen, Rechnungen und Hinweise zu
 * gekauften Leads sind Belege und haengen nicht an diesem Schalter -- das
 * steht auch so auf der Seite, damit niemand glaubt, er habe gerade seine
 * Kaufbestaetigungen abbestellt.
 *
 * Die Adresse ist signiert und braucht keine Anmeldung: Wer sich abmelden
 * will, soll sich dafuer nicht erst einloggen. Ohne Ablauf, weil ein toter
 * Abmeldelink in einer alten Mail genau den aergert, der ohnehin genervt ist.
 *
 * Ein zweiter Aufruf aendert nichts und zeigt dieselbe Seite.
 */
class MailUnsubscribeController extends Controller
{
    public function __invoke(string $user): View
    {
        $recipient = User::query()->where('uuid', $user)->first();

        abort_if(! $recipient instanceof User, 404);

        if (! $recipient->onboarding_mails_opt_out) {
            $recipient->onboarding_mails_opt_out = true;
            $recipient->save();
        }

        return view('pages.onboarding-mails-unsubscribed');
    }
}
