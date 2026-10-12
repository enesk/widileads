<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal\Onboarding;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Der Verifizierungs-Screen der Telefonsperre (LP-ONBOARD-005).
 *
 * Das Ziel der Middleware EnsurePhoneVerified und damit die einzige Seite, die
 * ein angemeldeter Nutzer ohne bestaetigte Rufnummer erreicht. Loest die
 * Closure ab, die bis LP-ONBOARD-003 in routes/web.php stand: Die Seite trifft
 * eine Entscheidung (wer schon bestaetigt hat, wird weitergeschickt), und eine
 * Entscheidung in einer Routendatei findet beim Lesen niemand.
 *
 * Mehr steht hier nicht: Die drei Screens sind Zustaende EINER Livewire-
 * Komponente (App\Livewire\Portal\Onboarding\PhoneVerification) und wechseln
 * ohne Seitenaufruf. Dieser Controller hat deshalb genau eine Methode.
 */
class PhoneController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        /*
         | Schon bestaetigt: Die Seite fuehrt die gemerkte Adresse aus. Das ist
         | die Rueckkehr nach der Sperre -- und der Weg, den auch der Knopf des
         | Erfolgs-Screens nimmt. Ohne diesen Zweig koennte ein Nutzer seine
         | bestaetigte Nummer ueber ein Lesezeichen noch einmal bestaetigen und
         | dabei Codes verschicken, die nichts mehr aendern.
         */
        if ($request->user()?->phone_number_verified_at !== null) {
            return redirect()->intended(route('portal.home'));
        }

        return view('onboarding.phone');
    }
}
