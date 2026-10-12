<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Onboarding\OnboardingService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Die harte Sperre bis zur bestaetigten Rufnummer (LP-ONBOARD-003).
 *
 * Wer angemeldet ist und keine bestaetigte Rufnummer hat, sieht nichts vom
 * System: keinen Marktplatz, kein Dashboard, keine Navigation, keine
 * Lead-Daten. Er landet ausschliesslich auf dem Verifizierungs-Screen.
 *
 * Das ist eine Geschaeftsregel und kein Komfort: Ohne bestaetigte Nummer kann
 * ein Kaeufer einen gekauften Lead nicht anrufen, und der Anruf ist der
 * einzige Weg, auf dem ein Lead abgerechnet oder freigegeben wird. Ein Kauf
 * davor waere Geld ohne Gegenwert. Verkaeufer-Workspaces sind aus demselben
 * Grund nicht ausgenommen -- eine Regel fuer alle ist eine Regel, die man beim
 * Lesen des Codes nicht uebersieht. Die Wizard-Schritte (LP-ONBOARD-006 ff.)
 * gelten dagegen nur fuer Kaeufer-Workspaces; die stehen nicht hier.
 *
 * **Middleware und nicht Gate/Policy.** Es geht nicht um ein einzelnes
 * Modell, sondern um den Zugriff auf einen ganzen Bereich. Ein Gate muesste in
 * jeder Komponente und jeder Seite einzeln gefragt werden -- eine vergessene
 * Stelle faellt dort nicht auf, weil sie weiter Daten zeigt.
 *
 * **Reihenfolge** (festgelegt in bootstrap/app.php): direkt nach `auth` und
 * `verified` und VOR `portal.tenant`. Die Prioritaetsliste zieht sie per
 * prependToPriorityList vor ResolvePortalTenant, damit sie nicht an der
 * Reihenfolge im Routen-Array haengt. Der Grund: Steht der Mandant erst, haben
 * schon Global Scope und Policies gearbeitet, und ein gesperrter Nutzer haette
 * den Workspace-Kontext bekommen, den er nicht haben soll.
 *
 * **Livewire und JSON.** Folgeanfragen von Livewire gehen an /livewire/update
 * und bekommen keinen HTML-Redirect -- der wuerde als Antwort auf einen
 * fetch() stillschweigend verpuffen. Sie bekommen 403 mit
 * `{reason: 'phone_unverified', redirect: url}`; resources/js/modules/auth-guard.js
 * faengt das ab und leitet den Browser um. Damit die Sperre bei
 * /livewire/update ueberhaupt laeuft, ist sie in AppServiceProvider als
 * persistente Livewire-Middleware eingetragen.
 *
 * **Zuruecksetzen wirkt sofort.** Gelesen wird in jeder Anfrage am Benutzer
 * (`users.phone_number_verified_at`, ueber OnboardingService). Wer dort auf
 * null gesetzt wird -- im Admin oder ueber UserService::anonymize --, ist beim
 * naechsten Request wieder gesperrt: ohne Abmelden, ohne Cache. Der Stand
 * liegt bewusst in keiner Sitzung.
 */
class EnsurePhoneVerified
{
    /**
     * Die Seite, auf der ein gesperrter Nutzer landet.
     */
    public const ALLOWED_ROUTE = 'onboarding.phone';

    /**
     * Routennamen, die trotz Sperre erreichbar bleiben (Str::is-Muster).
     *
     * Kurz gehalten und mit Grund je Eintrag: Jeder zusaetzliche Eintrag ist
     * ein Loch in der Sperre.
     */
    public const ALLOWED_ROUTE_NAMES = [
        // Der Verifizierungs-Screen selbst samt Unterschritten -- sonst
        // leitete er auf sich selbst um.
        'onboarding.phone',
        'onboarding.phone.*',

        // Abmelden muss immer gehen. Eine Sperre, aus der man nicht
        // herauskommt, ist eine Falle.
        'logout',

        // Konto loeschen bleibt moeglich: Wer seine Nummer nicht bestaetigen
        // will, muss sein Konto wieder los werden koennen (DSGVO). Die Route
        // existiert noch nicht; der Eintrag steht hier, damit sie nicht spaeter
        // versehentlich hinter der Sperre landet.
        'account.delete',

        // Pflichtangaben und Rechtstexte. Sie stehen im Fuss jedes Rahmens,
        // auch im Fokus-Rahmen des Verifizierungs-Screens.
        'imprint',
        'privacy-policy',
        'terms-of-service',

        // Die E-Mail-Bestaetigung laeuft vor dieser Sperre ab. Ihre Seiten
        // duerfen nicht in die Telefonsperre laufen, sonst haengt ein Nutzer
        // zwischen zwei Gates.
        'verification.*',
    ];

    public function __construct(private readonly OnboardingService $onboarding) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::isEnabled()) {
            return $next($request);
        }

        $user = $request->user();

        // Ohne Benutzer ist nichts zu entscheiden: Dann hat `auth` entweder
        // schon abgewiesen oder die Route ist oeffentlich.
        if ($user === null) {
            return $next($request);
        }

        if ($this->onboarding->isPhoneVerified($user)) {
            return $next($request);
        }

        if (self::allowsRoute($request->route()?->getName())) {
            return $next($request);
        }

        $target = route(self::ALLOWED_ROUTE);

        $this->rememberIntendedUrl($request);

        if (self::wantsJson($request)) {
            return new JsonResponse([
                'reason' => 'phone_unverified',
                'redirect' => $target,
            ], Response::HTTP_FORBIDDEN);
        }

        return redirect()->to($target);
    }

    /**
     * Ist die Sperre eingeschaltet?
     */
    public static function isEnabled(): bool
    {
        return (bool) config('funnel.onboarding.phone_gate', true);
    }

    /**
     * Darf dieser Routenname trotz Sperre durch?
     */
    public static function allowsRoute(?string $routeName): bool
    {
        if ($routeName === null) {
            // Eine Route ohne Namen laesst sich nicht freischalten -- im
            // Zweifel sperren.
            return false;
        }

        return Str::is(self::ALLOWED_ROUTE_NAMES, $routeName);
    }

    /**
     * Will der Aufrufer eine maschinenlesbare Antwort statt eines Redirects?
     *
     * Livewire schickt bei Folgeanfragen `X-Livewire` mit und wertet einen
     * 302 nicht als Navigation -- der Nutzer bliebe auf der gesperrten Seite
     * stehen, waehrend im Hintergrund die Startseite geladen wuerde.
     */
    private static function wantsJson(Request $request): bool
    {
        return $request->hasHeader('X-Livewire')
            || $request->expectsJson()
            || $request->is('api/*');
    }

    /**
     * Merkt die angefragte Adresse, damit es nach der Bestaetigung dort
     * weitergeht.
     *
     * Nur GET-Anfragen: Eine gemerkte POST-Adresse wuerde nach der
     * Bestaetigung als GET aufgerufen und endete in 405. Der Weg ueber
     * `url.intended` ist derselbe, den Laravels Authenticate benutzt -- der
     * Verifizierungs-Screen schliesst mit `redirect()->intended()` ab, und
     * damit greift die gemerkte Adresse auch nach Magic-Link-Login,
     * Passwort-Reset, E-Mail-Bestaetigung und Workspace-Einladung.
     */
    private function rememberIntendedUrl(Request $request): void
    {
        if (! $request->isMethod('GET') || ! $request->hasSession()) {
            return;
        }

        $request->session()->put('url.intended', $request->fullUrl());
    }
}
