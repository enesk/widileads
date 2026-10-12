<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Constants\OnboardingMailKey;
use App\Http\Controllers\Controller;
use App\Mail\Onboarding\BillingExplainedMail;
use App\Mail\Onboarding\LeadsWaitingMail;
use App\Mail\Onboarding\NudgeMail;
use App\Mail\Onboarding\OnboardingMailable;
use App\Mail\Onboarding\WelcomeMail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Vorschau der Onboarding-Mails (LP-ONBOARD-014).
 *
 * /mail-preview/onboarding-welcome und die drei anderen Schluessel. Gedacht
 * fuer die Abnahme: Wer eine Formulierung oder einen Knopf prueft, soll
 * dafuer keinen Wizard durchspielen und keine echte Mail ausloesen muessen.
 *
 * **Es wird nichts versendet und nichts geschrieben.** Die Seite rendert die
 * Mailable gegen einen echten Workspace -- mit seinen echten Zahlen, denn
 * eine Vorschau mit Platzhaltern beantwortet die eine Frage nicht, die man an
 * sie hat. Welcher Workspace, sagt `?tenant=<uuid>`; ohne Angabe der erste
 * Kaeufer mit einem Benutzer.
 *
 * Zugang: nur Plattform-Admins, ausser in der lokalen Entwicklung. Die Seite
 * zeigt die Kaufkriterien und die wartenden Anfragen eines fremden
 * Workspaces.
 */
class MailPreviewController extends Controller
{
    public function __invoke(Request $request, string $key): Response
    {
        $this->authorizeAccess();

        $mailKey = OnboardingMailKey::fromPreviewSlug('onboarding-'.$key);

        abort_if($mailKey === null, 404);

        $workspace = $this->workspace($request->query('tenant'));
        $recipient = $workspace->users()->first();

        abort_if(! $recipient instanceof User, 404, 'Dieser Workspace hat keinen Benutzer.');

        $mailable = $this->mailable($mailKey, $workspace, $recipient);

        // Die Nur-Text-Fassung ist Teil der Abnahme und darf nicht erst im
        // Postfach auffallen.
        if ($request->query('format') === 'text') {
            return response(
                $this->textBody($mailable),
                200,
                ['Content-Type' => 'text/plain; charset=utf-8'],
            );
        }

        return response($mailable->render());
    }

    /**
     * Nur Plattform-Admins -- lokal jeder, dort gibt es keine fremden Daten.
     */
    private function authorizeAccess(): void
    {
        if (app()->environment('local', 'testing')) {
            return;
        }

        $user = Auth::user();

        abort_unless($user instanceof User && $user->isAdmin(), 404);
    }

    private function workspace(mixed $uuid): Tenant
    {
        $query = Tenant::query()->buyers()->whereHas('users');

        if (is_string($uuid) && $uuid !== '') {
            $query->where('uuid', $uuid);
        }

        $workspace = $query->oldest('id')->first();

        abort_if(! $workspace instanceof Tenant, 404, 'Kein Kaeufer-Workspace fuer die Vorschau gefunden.');

        return $workspace;
    }

    /**
     * Die Nur-Text-Fassung derselben Mail.
     *
     * Gerendert aus demselben Content-Objekt wie der Versand -- die Vorschau
     * zeigt sonst eine Fassung, die es im Postfach nicht gibt.
     */
    private function textBody(OnboardingMailable $mailable): string
    {
        $content = $mailable->content();

        return (string) view(
            (string) $content->text,
            array_merge($mailable->buildViewData(), $content->with),
        )->render();
    }

    private function mailable(OnboardingMailKey $key, Tenant $workspace, User $recipient): OnboardingMailable
    {
        return match ($key) {
            OnboardingMailKey::WELCOME => new WelcomeMail($workspace, $recipient),
            OnboardingMailKey::BILLING_EXPLAINED => new BillingExplainedMail($workspace, $recipient),
            OnboardingMailKey::LEADS_WAITING => new LeadsWaitingMail($workspace, $recipient),
            OnboardingMailKey::NUDGE => new NudgeMail($workspace, $recipient),
        };
    }
}
