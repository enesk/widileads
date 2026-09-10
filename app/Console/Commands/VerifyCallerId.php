<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\CallerIdStatus;
use App\Constants\TenantType;
use App\Models\CallerId;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CallerIdService;
use App\Services\Twilio\CallerIdValidationFailed;
use Illuminate\Console\Command;
use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;
use Twilio\Rest\Client as TwilioClient;

/**
 * Ticket #32: Fuehrt Tunnel, Anforderung und Kontrolle der
 * Rufnummern-Bestaetigung in einem Lauf zusammen.
 *
 * Der Anruf selbst bleibt Handarbeit -- Twilio waehlt die Nummer und der
 * angesagte Code muss am Geraet eingetippt werden. Alles drumherum aber nicht:
 * oeffentliche Adresse besorgen, Anwendung darunter starten, Erreichbarkeit
 * belegen, anfordern, warten, wieder abraeumen. Von Hand sind das zwei
 * Terminals, ein .env-Edit hin und zurueck und zweimal config:clear -- und das
 * innerhalb der zehn Minuten, die der Code gilt.
 *
 * Die .env wird nicht angefasst. Die oeffentliche Adresse geht als
 * Umgebungsvariable in den Unterprozess; Laravels Dotenv ueberschreibt
 * gesetzte Umgebungsvariablen nicht.
 *
 * Kein dritter Weg in den Status: Das Kommando fordert nur an, `verified`
 * setzt weiterhin ausschliesslich der Rueckruf von Twilio.
 */
class VerifyCallerId extends Command
{
    protected $signature = 'leads:verify-caller-id
        {--user= : E-Mail des Mitarbeiters, dessen Rufnummer bestaetigt wird}
        {--phone= : Die zu bestaetigende Rufnummer (E.164 oder deutsche Schreibweise)}
        {--tenant= : Kennung oder Name des Kaeufer-Mandanten, falls der Benutzer in mehreren steht}
        {--port=8123 : Port, auf dem die Anwendung waehrend der Probe laeuft}';

    protected $description = 'Start a public tunnel, request the caller id validation call and wait for Twilio to report back.';

    private ?InvokedProcess $tunnel = null;

    private ?InvokedProcess $server = null;

    public function handle(): int
    {
        $this->trap([SIGINT, SIGTERM], function (): void {
            $this->newLine();
            $this->warn('Abbruch -- Tunnel und Server werden beendet.');
            $this->shutdown();
            exit(self::FAILURE);
        });

        try {
            return $this->runVerification();
        } finally {
            $this->shutdown();
        }
    }

    private function runVerification(): int
    {
        $email = trim((string) $this->option('user'));
        $phone = trim((string) $this->option('phone'));

        if ($email === '' || $phone === '') {
            $this->error('--user=<E-Mail> und --phone=<Rufnummer> werden beide gebraucht.');

            return self::FAILURE;
        }

        $user = $this->resolveUser($email);

        if ($user === null) {
            return self::FAILURE;
        }

        $tenant = $this->resolveTenant($user);

        if ($tenant === null) {
            return self::FAILURE;
        }

        if (! $this->guardEnvironment()) {
            return self::FAILURE;
        }

        $tunnelUrl = $this->startTunnel();

        if ($tunnelUrl === null) {
            return self::FAILURE;
        }

        $this->startServer($tunnelUrl);

        if (! $this->proveReachable($tunnelUrl)) {
            return self::FAILURE;
        }

        // Der eigene Prozess muss dieselbe Adresse tragen wie der Unterprozess:
        // route() baut daraus die statusCallbackUrl, die Twilio zurueckruft,
        // und ueber die volle URL wird die Signatur gerechnet.
        config(['app.url' => $tunnelUrl]);
        URL::forceRootUrl($tunnelUrl);

        try {
            $callerId = app(CallerIdService::class)->requestValidation(
                $tenant,
                $user,
                $phone,
                route('twilio.caller-id.validation-status'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error('Rufnummer abgelehnt: '.$exception->getMessage());

            return self::FAILURE;
        } catch (CallerIdValidationFailed $exception) {
            $this->error('Twilio hat die Anforderung abgelehnt: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->announceCode($callerId);

        return $this->awaitCallback($callerId, $user);
    }

    private function resolveUser(string $email): ?User
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error('Kein Benutzer mit der E-Mail "'.$email.'".');
        }

        return $user;
    }

    /**
     * Der Kaeufer-Mandant des Benutzers. Steht er in mehreren, wird nicht
     * geraten -- die Nummer haengt am Mandanten, eine falsche Wahl waere still
     * falsch.
     */
    private function resolveTenant(User $user): ?Tenant
    {
        /** @var Collection<int, Tenant> $tenants */
        $tenants = $user->tenants()->where('type', TenantType::BUYER)->get();
        $wanted = trim((string) $this->option('tenant'));

        if ($wanted !== '') {
            /** @var Tenant|null $tenant */
            $tenant = $tenants->first(
                fn (Tenant $tenant): bool => (string) $tenant->getKey() === $wanted
                    || $tenant->uuid === $wanted
                    || $tenant->name === $wanted
            );

            if ($tenant === null) {
                $this->error('Kein Kaeufer-Mandant "'.$wanted.'" fuer diesen Benutzer. Vorhanden: '.$this->tenantList($tenants));
            }

            return $tenant;
        }

        if ($tenants->isEmpty()) {
            $this->error('Der Benutzer gehoert zu keinem Kaeufer-Mandanten.');

            return null;
        }

        if ($tenants->count() > 1) {
            $this->error('Mehrere Kaeufer-Mandanten -- bitte mit --tenant= waehlen: '.$this->tenantList($tenants));

            return null;
        }

        /** @var Tenant $tenant */
        $tenant = $tenants->first();

        return $tenant;
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     */
    private function tenantList($tenants): string
    {
        return $tenants
            ->map(fn (Tenant $tenant): string => $tenant->getKey().' ('.$tenant->name.')')
            ->implode(', ');
    }

    /**
     * Vorbedingungen, die das Kommando sonst still ins Leere laufen lassen:
     * cloudflared muss da sein, ein liegender config:cache wuerde die
     * uebergebene APP_URL im Unterprozess wirkungslos machen, der Port muss
     * frei sein -- und das Twilio-Konto darf kein Trial sein.
     */
    private function guardEnvironment(): bool
    {
        if (! $this->guardAgainstTrialAccount()) {
            return false;
        }

        if ((new ExecutableFinder)->find('cloudflared') === null) {
            $this->error('cloudflared liegt nicht im Pfad. Installieren mit: brew install cloudflared');

            return false;
        }

        if (file_exists($this->laravel->getCachedConfigPath())) {
            $this->error('Es liegt ein config:cache. Der Unterprozess wuerde die zwischengespeicherte APP_URL lesen. Erst `php artisan config:clear` ausfuehren.');

            return false;
        }

        // Ist der Port belegt, weicht `artisan serve` still auf den naechsten
        // aus -- der Tunnel zeigte dann ins Leere.
        $socket = @fsockopen('127.0.0.1', $this->port(), $code, $message, 1);

        if (is_resource($socket)) {
            fclose($socket);
            $this->error('Auf Port '.$this->port().' laeuft bereits etwas. Beenden oder mit --port= einen freien Port waehlen.');

            return false;
        }

        return true;
    }

    /**
     * Ticket #38: Twilio nimmt auf einem Trial-Konto ueberhaupt keine
     * Bestaetigungsanrufe an ("Placing verification calls is not supported on
     * trial accounts"). Der Kontotyp steht mit einem lesenden Aufruf fest, also
     * wird er abgefragt, bevor Tunnel und Server hochgezogen werden -- sonst
     * laeuft der ganze Aufbau in eine Ablehnung.
     *
     * Ist das Konto nicht abfragbar (keine Zugangsdaten, Netz weg), bricht das
     * Kommando ebenfalls ab: ohne Zugangsdaten gibt es auch keinen Anruf.
     */
    private function guardAgainstTrialAccount(): bool
    {
        $sid = (string) (config('twilio.account_sid') ?: config('services.twilio.sid'));
        $token = (string) (config('twilio.auth_token') ?: config('services.twilio.token'));

        if ($sid === '' || $token === '') {
            $this->error('Twilio-Zugangsdaten fehlen (TWILIO_SID/TWILIO_TOKEN oder Admin-Panel "Twilio").');

            return false;
        }

        try {
            // Bewusst nicht die Bindung aus dem Container: die liest nur
            // config('twilio.*') und wuerde bei Zugangsdaten aus dem
            // Admin-Panel werfen.
            $account = (new TwilioClient($sid, $token))->api->v2010->accounts($sid)->fetch();
        } catch (Throwable $exception) {
            $this->error('Das Twilio-Konto laesst sich nicht abfragen: '.$exception->getMessage());

            return false;
        }

        if ($account->type === 'Trial') {
            $this->error('Das Twilio-Konto ist ein Trial-Konto. Twilio lehnt Bestaetigungsanrufe darauf grundsaetzlich ab -- es wird nichts gestartet und nichts angefordert.');
            $this->line('Erst Ticket #27 abarbeiten: Konto auf Full aufwerten, deutsche Adresse und Regulatory Bundle anlegen, dann DE-Voice-Nummer kaufen.');
            $this->line('Stand pruefen mit: php artisan leads:check-call-setup --remote');

            return false;
        }

        $this->line('Twilio-Konto: <info>'.$account->type.'</info>');

        return true;
    }

    /**
     * Tunnel zuerst: Die Adresse steht erst fest, wenn er laeuft, der Server
     * braucht sie aber schon beim Start in seiner Umgebung.
     */
    private function startTunnel(): ?string
    {
        $this->info('Tunnel wird gestartet ...');

        $this->tunnel = Process::timeout(0)->start(
            'cloudflared tunnel --url http://127.0.0.1:'.$this->port()
        );

        $deadline = microtime(true) + 45;

        while (microtime(true) < $deadline) {
            $output = $this->tunnel->output().$this->tunnel->errorOutput();

            if (preg_match('#https://[a-z0-9-]+\.trycloudflare\.com#i', $output, $matches) === 1) {
                $this->line('Oeffentliche Adresse: <info>'.$matches[0].'</info>');

                return $matches[0];
            }

            if (! $this->tunnel->running()) {
                $this->error('cloudflared hat sich beendet: '.trim($output));

                return null;
            }

            usleep(500_000);
        }

        $this->error('cloudflared hat binnen 45 Sekunden keine Adresse gemeldet.');

        return null;
    }

    private function startServer(string $tunnelUrl): void
    {
        $this->info('Anwendung wird unter der oeffentlichen Adresse gestartet ...');

        $this->server = Process::timeout(0)
            ->env([
                'APP_URL' => $tunnelUrl,
                // Twilio holt die Bridge ab, waehrend der Rueckruf noch laeuft:
                // ein einzelner Worker blockiert sich dabei selbst.
                'PHP_CLI_SERVER_WORKERS' => '5',
            ])
            ->start('php artisan serve --port='.$this->port());
    }

    /**
     * Belegen, bevor gewaehlt wird -- ein Anruf, dessen Rueckruf nirgends
     * ankommt, kostet zehn Minuten Wartezeit und muss ganz von vorn beginnen.
     */
    private function proveReachable(string $tunnelUrl): bool
    {
        $host = (string) parse_url($tunnelUrl, PHP_URL_HOST);
        $ip = $this->resolve($host);

        if ($ip === null) {
            $this->error('Der Name '.$host.' laesst sich nicht aufloesen -- weder ueber den Resolver dieses Rechners noch ueber 1.1.1.1.');

            return false;
        }

        $this->line('Warte auf die oeffentliche Adresse ('.$host.' -> '.$ip.') ...');

        $login = null;
        $deadline = microtime(true) + 120;

        while (microtime(true) < $deadline) {
            try {
                $login = $this->request($host, $ip)->get($tunnelUrl.'/login');

                if ($login->status() === 200) {
                    break;
                }
            } catch (Throwable) {
                // Tunnel oder Server noch nicht so weit.
            }

            if ($this->server !== null && ! $this->server->running()) {
                $this->error('Der Server hat sich beendet: '.trim($this->server->output().$this->server->errorOutput()));

                return false;
            }

            // Die Ausgabe von cloudflared abholen, damit sein Ausgabepuffer
            // nicht volllaeuft.
            $this->tunnel?->latestErrorOutput();

            usleep(2_000_000);
        }

        if ($login === null || $login->status() !== 200) {
            $this->error('GET '.$tunnelUrl.'/login liefert '.($login?->status() ?? 'keine Antwort').' statt 200. Es wird nichts angerufen.');

            return false;
        }

        $this->line('GET /login: <info>200</info>');

        try {
            $callback = $this->request($host, $ip)
                ->asForm()
                ->post($tunnelUrl.'/api/twilio/caller-id/validation-status', []);
        } catch (Throwable $exception) {
            $this->error('Der Rueckruf-Endpunkt ist nicht erreichbar: '.$exception->getMessage());

            return false;
        }

        // 403 ist hier der Erfolg: erreichbar, und die Signaturpruefung weist
        // den unsignierten Aufruf ab. Alles andere heisst, dass entweder nichts
        // ankommt oder ungeprueft durchgelassen wird.
        if ($callback->status() !== 403) {
            $this->error('POST /api/twilio/caller-id/validation-status liefert '.$callback->status().' statt 403. Es wird nichts angerufen.');

            return false;
        }

        $this->line('POST /api/twilio/caller-id/validation-status: <info>403</info> (erreichbar, Signaturpruefung greift)');

        return true;
    }

    /**
     * Anfrage mit fest verdrahteter Aufloesung.
     *
     * Der Name eines Quick Tunnels ist frisch, und mancher Resolver im
     * Heimnetz gibt frische Namen unter *.trycloudflare.com minutenlang gar
     * nicht heraus. Twilio erreicht die Adresse trotzdem -- es fragt seinen
     * eigenen Resolver. Damit die Vorabkontrolle nicht am Resolver dieses
     * Rechners scheitert, waehlt sie die Adresse selbst an.
     */
    private function request(string $host, string $ip): PendingRequest
    {
        return Http::timeout(15)->withOptions([
            'curl' => [CURLOPT_RESOLVE => [$host.':443:'.$ip]],
        ]);
    }

    /**
     * Erst der Resolver dieses Rechners, dann 1.1.1.1 ueber DNS-over-HTTPS.
     * Der zweite Weg braucht selbst keine Namensaufloesung, weil er die
     * IP-Adresse direkt anspricht.
     */
    private function resolve(string $host): ?string
    {
        $deadline = microtime(true) + 60;

        do {
            $ip = $this->resolveOnce($host);

            if ($ip !== null) {
                return $ip;
            }

            usleep(2_000_000);
        } while (microtime(true) < $deadline);

        return null;
    }

    private function resolveOnce(string $host): ?string
    {
        $local = gethostbyname($host);

        if ($local !== $host && filter_var($local, FILTER_VALIDATE_IP) !== false) {
            return $local;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/dns-json'])
                ->get('https://1.1.1.1/dns-query', ['name' => $host, 'type' => 'A']);

            /** @var array<int, array{data?: string, type?: int}> $answers */
            $answers = $response->json('Answer') ?? [];

            foreach ($answers as $answer) {
                if (filter_var($answer['data'] ?? '', FILTER_VALIDATE_IP) !== false) {
                    return (string) $answer['data'];
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function announceCode(CallerId $callerId): void
    {
        $this->newLine();
        $this->info(str_repeat('=', 46));
        $this->info('  Twilio ruft jetzt '.$callerId->phone_number.' an.');
        $this->info('  Am klingelnden Telefon eintippen:  '.($callerId->validation_code ?? '?'));
        $this->info(str_repeat('=', 46));
        $this->newLine();
    }

    /**
     * Nachladen, bis Twilio zurueckgemeldet hat -- laenger als bis zum Verfall
     * des Codes hat das Warten keinen Sinn.
     */
    private function awaitCallback(CallerId $callerId, User $user): int
    {
        $service = app(CallerIdService::class);
        $expiresAt = $callerId->expires_at;

        $this->line('Warte auf den Rueckruf von Twilio (bis '.($expiresAt?->format('H:i:s') ?? '?').') ...');

        while ($expiresAt === null || $expiresAt->isFuture()) {
            sleep(2);

            $current = $service->forUser($user);

            if ($current !== null && $current->status !== CallerIdStatus::PENDING) {
                return $this->reportResult($current);
            }
        }

        $service->expireIfOverdue($callerId->refresh());

        $this->error('Der Code ist verfallen, ohne dass Twilio zurueckgemeldet hat. Kommando erneut starten.');

        return self::FAILURE;
    }

    private function reportResult(CallerId $callerId): int
    {
        if ($callerId->status === CallerIdStatus::VERIFIED) {
            $this->newLine();
            $this->info('Bestaetigt: '.$callerId->phone_number.' steht auf "verified".');
            $this->line('Naechster Schritt: php artisan leads:check-call-setup --user='.$callerId->user?->email.' --production');

            return self::SUCCESS;
        }

        $this->error('Twilio meldet den Stand "'.$callerId->status->value.'". Die Nummer ist nicht bestaetigt.');

        return self::FAILURE;
    }

    private function port(): int
    {
        return (int) $this->option('port');
    }

    private function shutdown(): void
    {
        foreach ([$this->server, $this->tunnel] as $process) {
            if (! $process instanceof InvokedProcess || ! $process->running()) {
                continue;
            }

            $process->signal(SIGTERM);

            $deadline = microtime(true) + 3;

            while ($process->running() && microtime(true) < $deadline) {
                usleep(200_000);
            }

            if ($process->running()) {
                $process->signal(SIGKILL);
            }
        }

        $this->server = null;
        $this->tunnel = null;
    }
}
