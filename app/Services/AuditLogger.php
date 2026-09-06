<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\AuditAction;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Schreibt sicherheitsrelevante Vorgaenge in das Audit-Log (FB-005).
 *
 * Der Dienst ist die einzige Stelle, an der Audit-Eintraege entstehen. Er
 * ermittelt fehlende Angaben (handelnder Benutzer, Mandant, IP-Hash) selbst aus
 * dem laufenden Request, damit Aufrufer sich darum nicht kuemmern muessen.
 *
 * Die IP-Adresse verlaesst diesen Dienst nie im Klartext: Sie wird sofort mit
 * dem Salt aus config('funnel.audit.ip_salt') per SHA-256 gehasht. Payloads
 * werden vorher gegen config('funnel.audit.redacted_payload_keys') geprueft,
 * damit weder Passwoerter noch Tokens noch Roh-IPs gespeichert werden.
 */
class AuditLogger
{
    /**
     * Platzhalter, der anstelle eines geschuetzten Payload-Werts gespeichert wird.
     */
    public const REDACTED = '[redaktiert]';

    public function __construct(
        private readonly Request $request,
        private readonly Application $app,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  Zusatzangaben zum Vorgang; geschuetzte Schluessel werden ersetzt.
     * @param  Tenant|null  $tenant  Mandant; ohne Angabe der aktive Mandant aus dem Filament-Kontext.
     * @param  User|null  $user  Handelnder Benutzer; ohne Angabe der angemeldete Benutzer.
     * @param  string|null  $ipAddress  Roh-IP; wird ausschliesslich gehasht gespeichert.
     */
    public function log(
        AuditAction $action,
        ?Model $subject = null,
        array $payload = [],
        ?Tenant $tenant = null,
        ?User $user = null,
        ?string $ipAddress = null,
    ): AuditLog {
        $tenant ??= $this->resolveTenant();
        $user ??= $this->resolveUser();
        $ipAddress ??= $this->resolveIpAddress();

        return AuditLog::query()->create([
            'tenant_id' => $tenant?->getKey(),
            'user_id' => $user?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject === null ? null : (string) $subject->getKey(),
            'payload' => $payload === [] ? null : $this->redact($payload),
            'ip_hash' => $this->hashIpAddress($ipAddress),
        ]);
    }

    /**
     * Gesalzener SHA-256-Hash einer IP-Adresse. Gleiche IP und gleicher Salt
     * ergeben denselben Hash, sodass Vorgaenge derselben Herkunft vergleichbar
     * bleiben, ohne die IP zu speichern.
     */
    public function hashIpAddress(?string $ipAddress): ?string
    {
        if ($ipAddress === null || trim($ipAddress) === '') {
            return null;
        }

        return hash('sha256', $this->ipSalt().'|'.$ipAddress);
    }

    /**
     * Ersetzt die Werte geschuetzter Schluessel rekursiv durch einen Platzhalter.
     *
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function redact(array $payload): array
    {
        /** @var list<string> $protectedKeys */
        $protectedKeys = array_map('strtolower', (array) config('funnel.audit.redacted_payload_keys', []));

        $redacted = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $protectedKeys, true)) {
                $redacted[$key] = self::REDACTED;

                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $redacted;
    }

    private function ipSalt(): string
    {
        $salt = config('funnel.audit.ip_salt');

        if (is_string($salt) && $salt !== '') {
            return $salt;
        }

        return (string) config('app.key');
    }

    private function resolveTenant(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    private function resolveUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private function resolveIpAddress(): ?string
    {
        if ($this->app->runningInConsole()) {
            return null;
        }

        return $this->request->ip();
    }
}
