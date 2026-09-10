<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CallerId;
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;
use Twilio\Rest\Client as TwilioClient;

/**
 * FB-082 bis FB-085 (Ticket #21): Prueft vor der Telefonprobe auf Staging, ob
 * alles steht, was ein echter Anruf braucht.
 *
 * Der Geraetetest selbst bleibt Handarbeit -- zwei Telefone und ein Postfach
 * kann kein Kommando ersetzen. Was ein Kommando aber kann: die stillen
 * Fehlschlaege vorher aufdecken. Eine fehlende Portal-Nummer, eine APP_URL,
 * die auf localhost zeigt, ein Auth Token, mit dem die Signaturpruefung jeden
 * Rueckruf abweist -- das alles zeigt sich sonst erst, wenn jemand mit dem
 * Hoerer am Ohr dasteht und nichts passiert.
 *
 * Nur lesend. Es wird nichts gewaehlt und nichts verschickt.
 */
class CheckCallSetup extends Command
{
    protected $signature = 'leads:check-call-setup
        {--user= : E-Mail des Mitarbeiters, dessen bestaetigte Rufnummer geprueft wird}
        {--remote : Zusaetzlich das Twilio-Konto abfragen (Zugangsdaten und Portal-Nummer)}
        {--production : Massstab Produktionsfreigabe -- Trial-Konto und nicht gekaufte Portal-Nummer gelten als Fehler (setzt --remote voraus und schaltet es zu)}';

    protected $description = 'Check every precondition for the click-to-call device test on staging.';

    /** @var list<array{0: string, 1: string, 2: string}> */
    private array $rows = [];

    private bool $hasFailure = false;

    /** Kontotyp aus der Fernabfrage; null, solange nicht abgefragt oder nicht erreichbar. */
    private ?bool $isTrialAccount = null;

    public function handle(): int
    {
        $this->checkCredentials();
        $this->checkCallbackUrls();
        $this->checkThresholds();
        $this->checkMail();

        // Das Konto vor der Rufnummer: Auf einem Trial-Konto nimmt Twilio gar
        // keine Bestaetigungsanrufe an, und dann ist der Kontotyp der Befund
        // und nicht der Stand der Rufnummer (Ticket #38).
        if ($this->option('remote') || $this->isProductionCheck()) {
            $this->checkTwilioAccount();
        }

        $this->checkCallerId();

        $this->table(['Pruefung', 'Stand', 'Befund'], $this->rows);

        if ($this->hasFailure) {
            $this->error($this->isProductionCheck()
                ? 'Die Produktionsfreigabe darf so nicht erteilt werden -- erst die als FEHLER markierten Punkte beheben.'
                : 'Der Geraetetest kann so nicht laufen -- erst die als FEHLER markierten Punkte beheben.');

            return self::FAILURE;
        }

        $this->info($this->isProductionCheck()
            ? 'Alle Voraussetzungen fuer den Regelbetrieb stehen.'
            : 'Alle Voraussetzungen stehen. Ablauf der Telefonprobe: Dokument "Geraetetest Erreichbarkeitspruefung — Ablaufplan Staging (Ticket #21)".');

        return self::SUCCESS;
    }

    private function checkCredentials(): void
    {
        $sid = $this->accountSid();
        $token = $this->authToken();
        $from = $this->platformNumber();

        $this->assert(
            'Account SID',
            str_starts_with($sid, 'AC') && strlen($sid) === 34,
            $sid === '' ? 'Account SID fehlt (TWILIO_SID oder Admin-Panel "Twilio").' : 'Die Account SID sieht nicht nach einer aus (AC + 32 Zeichen).',
            'gesetzt ('.substr($sid, 0, 6).'...)',
        );

        // Derselbe Token signiert die Rueckrufe. Fehlt er, weist
        // VerifyTwilioSignature jeden Rueckruf mit 403 ab und kein Versuch
        // bekommt je ein Ergebnis.
        $this->assert(
            'Auth Token / Signaturpruefung',
            $token !== '',
            'Auth Token fehlt (TWILIO_TOKEN oder Admin-Panel "Twilio") -- jeder Twilio-Rueckruf wird mit 403 abgewiesen.',
            'gesetzt, Rueckrufe werden geprueft',
        );

        $this->assert(
            'Portal-Nummer',
            preg_match('/^\+[1-9]\d{7,14}$/', $from) === 1,
            $from === '' ? 'Portal-Nummer fehlt (TWILIO_FROM oder Admin-Panel "Twilio") -- ohne sie beginnt kein Anruf.' : 'Die Portal-Nummer ist nicht E.164 ("'.$from.'"), Leerzeichen lehnt Twilio als Absender ab.',
            $from.' erscheint beim Lead als Rufnummernanzeige',
        );
    }

    /**
     * Twilio ruft die Adressen von aussen auf. Sie entstehen aus APP_URL --
     * zeigt die auf localhost oder auf http, kommt kein einziger Rueckruf an
     * und jeder Versuch bleibt bis zum Verfall offen stehen.
     */
    private function checkCallbackUrls(): void
    {
        $appUrl = (string) config('app.url');
        $host = (string) parse_url($appUrl, PHP_URL_HOST);
        $isLocal = $host === '' || $host === 'localhost' || str_ends_with($host, '.test') || str_starts_with($host, '127.');

        $this->assert(
            'APP_URL',
            ! $isLocal,
            'APP_URL zeigt auf "'.$appUrl.'" -- von dort holt Twilio keine Anweisung ab.',
            $appUrl,
        );

        $this->assert(
            'Rueckrufe ueber HTTPS',
            str_starts_with($appUrl, 'https://'),
            'APP_URL ist kein https -- Twilio stellt Rueckrufe sonst nicht zu.',
            'https',
            warnOnly: $isLocal,
        );

        $sample = route('twilio.calls.bridge', ['attempt' => 'PRUEFUNG']);

        $this->rows[] = ['Bridge-Adresse', 'HINWEIS', $sample];
    }

    private function checkThresholds(): void
    {
        $retry = (int) config('lead_calls.retry_min_hours');

        // Fuer die Probe steht der Mindestabstand auf 0, damit die Versuche
        // hintereinander laufen koennen. Danach muss er zurueck auf 2 --
        // deshalb hier als Hinweis und nicht als Fehler.
        $this->rows[] = [
            'Mindestabstand der Versuche',
            $retry === 0 ? 'HINWEIS' : 'OK',
            $retry === 0
                ? 'LEAD_CALLS_RETRY_MIN_HOURS=0 -- Probebetrieb. Nach dem Test zurueck auf 2.'
                : $retry.' Stunden (Regelbetrieb).',
        ];

        $detection = (string) config('lead_calls.machine_detection');

        $this->assert(
            'Anrufbeantworter-Erkennung',
            in_array($detection, ['Enable', 'DetectMessageEnd'], true),
            'LEAD_CALLS_MACHINE_DETECTION ist "'.$detection.'" -- Twilio kennt nur "Enable" oder "DetectMessageEnd".',
            $detection,
        );

        $this->rows[] = [
            'Klingelzeiten',
            'HINWEIS',
            'Mitarbeiter '.(int) config('lead_calls.buyer_ring_timeout').' s, Lead '.(int) config('lead_calls.lead_ring_timeout').' s, ab '.(int) config('lead_calls.answered_min_seconds').' s gilt ein Versuch als erreicht.',
        ];
    }

    /**
     * Die Erinnerungsmail soll durch Provider und Spamfilter kommen. Ueber
     * einen Log-Mailer oder eine synchrone Queue kommt sie gar nicht erst los.
     */
    private function checkMail(): void
    {
        $mailer = (string) config('mail.default');

        $this->assert(
            'Mail-Versand',
            ! in_array($mailer, ['log', 'array', 'null'], true),
            'MAIL_MAILER ist "'.$mailer.'" -- die Erinnerung landet nicht in einem Postfach.',
            $mailer,
        );

        $from = (string) config('mail.from.address');

        $this->assert(
            'Absenderadresse',
            $from !== '',
            'mail.from.address ist leer.',
            $from,
        );

        $queue = (string) config('queue.default');

        $this->rows[] = [
            'Queue',
            $queue === 'sync' ? 'HINWEIS' : 'OK',
            $queue === 'sync'
                ? 'sync -- die Mail geht direkt beim Kommandolauf raus, kein Worker noetig.'
                : $queue.' -- ein Worker muss laufen, sonst bleibt die Erinnerung in der Warteschlange liegen.',
        ];
    }

    /**
     * Ohne bestaetigte eigene Nummer verweigert CallService den Anruf, bevor
     * er beginnt. Das ist der haeufigste Grund, warum die Probe nicht startet.
     */
    private function checkCallerId(): void
    {
        $email = (string) $this->option('user');

        if ($email === '') {
            $this->rows[] = ['Rufnummer des Mitarbeiters', 'HINWEIS', 'Nicht geprueft -- mit --user=<E-Mail> pruefbar.'];

            return;
        }

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->assert('Mitarbeiter', false, 'Kein Benutzer mit der E-Mail "'.$email.'".', '');

            return;
        }

        /** @var CallerId|null $callerId */
        $callerId = CallerId::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->latest('id')
            ->first();

        $this->assert(
            'Bestaetigte Rufnummer',
            $callerId !== null && $callerId->isUsableAsCallerId(),
            $this->callerIdProblem($email, $callerId),
            $callerId?->phone_number.' bestaetigt',
        );
    }

    /**
     * Ticket #38: Auf einem Trial-Konto lehnt Twilio jeden Bestaetigungsanruf
     * ab. Ein Stand "pending" liest sich dann wie eine laufende Bestaetigung,
     * obwohl nie ein Telefon geklingelt hat -- also wird der Grund benannt und
     * nicht der Stand wiederholt.
     */
    private function callerIdProblem(string $email, ?CallerId $callerId): string
    {
        if ($this->isTrialAccount === true) {
            return 'Trial-Konto -- Twilio nimmt darauf keine Bestaetigungsanrufe an. Erst Ticket #27 (Full-Konto, Adresse, Bundle, DE-Nummer), dann bestaetigen.'
                .($callerId === null ? '' : ' Hinterlegt: '.$callerId->phone_number.' im Stand "'.$callerId->status->value.'".');
        }

        if ($callerId === null) {
            return 'Fuer "'.$email.'" ist keine Rufnummer hinterlegt -- im Portal unter "Eigene Rufnummer" bestaetigen.';
        }

        return 'Rufnummer '.$callerId->phone_number.' hat den Stand "'.$callerId->status->value.'".';
    }

    /**
     * Fragt das Konto ab: Stimmen die Zugangsdaten, und darf die
     * Portal-Nummer als Absender dienen? Eine fremde Nummer als `from` lehnt
     * Twilio erst im Anruf ab.
     *
     * Zulaessig sind zwei Herkuenfte: eine dem Konto gehoerende Nummer
     * (`incomingPhoneNumbers`) oder eine bei Twilio verifizierte fremde
     * Nummer (`outgoingCallerIds`). Die zweite ist der uebliche Fall, solange
     * keine eigene Nummer gekauft ist -- wer nur die erste prueft, meldet
     * FEHLER, wo der Anruf laufen wuerde.
     */
    private function checkTwilioAccount(): void
    {
        try {
            // Bewusst nicht die Bindung aus dem Container: die liest nur
            // config('twilio.*') und wuerde bei Zugangsdaten aus dem
            // Admin-Panel werfen, obwohl der Anruf selbst laufen wuerde.
            $client = new TwilioClient($this->accountSid(), $this->authToken());
            $account = $client->api->v2010->accounts($this->accountSid())->fetch();
            $this->isTrialAccount = $account->type === 'Trial';

            $this->assert(
                'Twilio-Konto',
                $account->status === 'active',
                'Das Konto hat den Stand "'.$account->status.'".',
                $account->friendlyName.' ('.$account->status.')',
            );

            // Ein Trial-Konto erreicht nur bei Twilio verifizierte Nummern und
            // schiebt vor jedes Gespraech eine Ansage. Fuer die Probe mit
            // verifizierten Testgeraeten reicht das, fuer echte Leads nicht --
            // deshalb Hinweis hier und Sperre bei der Produktionsfreigabe.
            $this->assert(
                'Kontotyp',
                $account->type !== 'Trial',
                'Trial -- erreicht nur verifizierte Nummern und spielt eine Trial-Ansage vor. Vor Produktion aufwerten (Zahlungsmittel hinterlegen).',
                'Full -- beliebige Zielrufnummern erreichbar.',
                warnOnly: ! $this->isProductionCheck(),
            );

            $owned = $client->incomingPhoneNumbers->read(['phoneNumber' => $this->platformNumber()], 1);
            $verified = array_filter(
                $client->outgoingCallerIds->read([], 50),
                fn ($callerId): bool => $callerId->phoneNumber === $this->platformNumber(),
            );

            $this->assert(
                'Portal-Nummer als Absender',
                $owned !== [] || $verified !== [],
                'Die Portal-Nummer gehoert diesem Twilio-Konto nicht und ist dort auch nicht verifiziert -- Twilio lehnt sie als Absender ab.',
                $owned !== [] ? 'eigene Nummer des Kontos' : 'verifizierte Absendernummer (nicht gekauft)',
            );

            // Fuer die Probe genuegt eine verifizierte fremde Nummer. Fuer den
            // Regelbetrieb nicht: Rueckrufe des Leads auf eine nur verifizierte
            // Nummer landen nicht im Portal.
            $this->assert(
                'Portal-Nummer gekauft',
                $owned !== [],
                'Die Portal-Nummer ist nur eine verifizierte Absendernummer -- Rueckrufe des Leads landen nicht im Portal. Deutsche Voice-Nummer kaufen und im Admin-Panel "Twilio" eintragen.',
                'eigene Nummer des Kontos',
                warnOnly: ! $this->isProductionCheck(),
            );

            $this->checkRegulatoryBundle($client, $owned !== []);
        } catch (Throwable $exception) {
            $this->assert('Twilio-Konto', false, 'Abfrage gescheitert: '.$exception->getMessage(), '');
        }
    }

    /**
     * Ticket #39: Eine deutsche Festnetznummer laesst Twilio nur mit einem
     * freigegebenen Regulatory Bundle kaufen -- ohne kommt der Kauf mit
     * Fehler 21649 zurueck, auch auf einem Full-Konto mit Guthaben und
     * passender Ortsvorwahl. Der Weg von "draft" ueber "pending-review" zu
     * "twilio-approved" dauert bei Twilio Tage, deshalb gehoert der Stand in
     * den Preflight und nicht in eine Handabfrage.
     *
     * Ist die Portal-Nummer bereits gekauft, ist die Frage erledigt.
     */
    private function checkRegulatoryBundle(TwilioClient $client, bool $numberOwned): void
    {
        $bundles = $client->numbers->v2->regulatoryCompliance->bundles->read([
            'isoCountry' => 'DE',
            'numberType' => 'local',
        ], 20);

        $approved = array_values(array_filter(
            $bundles,
            fn ($bundle): bool => $bundle->status === 'twilio-approved',
        ));

        $states = array_map(fn ($bundle): string => (string) $bundle->status, $bundles);

        $this->assert(
            'Regulatory Bundle DE (local)',
            $numberOwned || $approved !== [],
            $bundles === []
                ? 'Kein deutsches Bundle im Konto -- ohne freigegebenes Bundle lehnt Twilio den Kauf einer DE-Festnetznummer mit Fehler 21649 ab (Ticket #39, Regulation RNfd11dde5d4b7252abf96139766f68034).'
                : 'Bundle vorhanden, Stand "'.implode('", "', $states).'" -- erst "twilio-approved" erlaubt den Nummernkauf.',
            $approved !== []
                ? 'freigegeben ('.$approved[0]->sid.')'
                : 'nicht mehr noetig -- die Portal-Nummer ist gekauft',
            warnOnly: ! $this->isProductionCheck(),
        );
    }

    /**
     * Ticket #24: Ein Trial-Konto und eine nur verifizierte Portal-Nummer
     * tragen die Probe, aber nicht den Regelbetrieb. Mit --production wird aus
     * dem Hinweis ein Fehler, damit die Freigabe nicht versehentlich auf
     * dieser Kontobasis erteilt wird.
     */
    private function isProductionCheck(): bool
    {
        return (bool) $this->option('production');
    }

    /**
     * Kanonisch config('twilio.from_number'), mit dem alten SaaSykit-Ort als
     * Rueckfall. Derselbe Rueckfall wie in CallService::platformNumber() --
     * das Kommando muss lesen, was der Anruf liest.
     */
    private function platformNumber(): string
    {
        $number = (string) config('twilio.from_number');

        return $number !== '' ? $number : (string) config('services.twilio.from');
    }

    /**
     * Zugangsdaten stehen entweder in der Env (config('twilio.*')) oder, ueber
     * das Admin-Panel gepflegt, im SaaSykit-Block services.twilio.*. Der echte
     * Anruf (TwilioOutboundCallClient) und die Signaturpruefung nehmen beide
     * Orte, also nimmt der Preflight sie auch -- sonst meldet er FEHLER, wo
     * gar keiner ist.
     */
    private function accountSid(): string
    {
        return (string) (config('twilio.account_sid') ?: config('services.twilio.sid'));
    }

    private function authToken(): string
    {
        return (string) (config('twilio.auth_token') ?: config('services.twilio.token'));
    }

    private function assert(string $label, bool $passed, string $problem, string $detail, bool $warnOnly = false): void
    {
        if ($passed) {
            $this->rows[] = [$label, 'OK', $detail];

            return;
        }

        $this->hasFailure = $this->hasFailure || ! $warnOnly;
        $this->rows[] = [$label, $warnOnly ? 'HINWEIS' : 'FEHLER', $problem];
    }
}
