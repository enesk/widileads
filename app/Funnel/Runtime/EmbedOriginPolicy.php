<?php

declare(strict_types=1);

namespace App\Funnel\Runtime;

use App\Constants\AuditAction;
use App\Models\Funnel;
use App\Services\AuditLogger;

/**
 * Entscheidet, ob ein Funnel auf einer fremden Seite eingebettet werden darf
 * (FB-024, durchgesetzt seit FB-025).
 *
 * Ohne Allowlist kann jede fremde Seite einen Funnel in ihre eigene einbetten
 * und Leads unter ihrem Namen sammeln. Erlaubt sind deshalb nur die je Funnel
 * gepflegten Herkuenfte (`funnel_origins`) und die eigene Domain.
 *
 * Geprueft wird gegen die Live-Tabelle, nicht gegen den Snapshot: Eine
 * Freigabe soll sofort wirken, ohne dass der Funnel neu veroeffentlicht werden
 * muss -- und ein Entzug erst recht. Der Snapshot friert die Fragen ein, nicht
 * die Zugriffsrechte.
 */
class EmbedOriginPolicy
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Ohne Herkunft ist es keine Einbettung, sondern ein direkter Aufruf der
     * Strecke -- der bleibt immer erlaubt.
     */
    public function allows(Funnel $funnel, ?string $origin): bool
    {
        $origin = $this->normalize($origin);

        if ($origin === null) {
            return true;
        }

        if ($origin === $this->ownOrigin()) {
            return true;
        }

        return $funnel->origins()
            ->where('origin', $origin)
            ->exists();
    }

    /**
     * Haelt einen abgewiesenen Einbettungsversuch im Audit-Log fest.
     *
     * Wichtig fuer den Betreiber: Wer versucht, seinen Funnel einzubetten, ohne
     * dass er es weiss? Ohne Eintrag bliebe der Versuch unsichtbar, denn der
     * Besucher sieht nur eine Fehlerseite.
     */
    public function recordRejection(Funnel $funnel, ?string $origin): void
    {
        $this->auditLogger->log(
            AuditAction::EMBED_ORIGIN_REJECTED,
            subject: $funnel,
            payload: [
                'funnel_public_token' => $funnel->public_token,
                'rejected_origin' => $this->normalize($origin),
            ],
            tenant: $funnel->tenant,
        );
    }

    /**
     * Normalisiert eine Herkunft auf "schema://host[:port]". Alles andere ist
     * keine Herkunft, sondern eine Behauptung.
     */
    public function normalize(?string $origin): ?string
    {
        if (! is_string($origin) || trim($origin) === '') {
            return null;
        }

        $parts = parse_url(trim($origin));

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $normalized = mb_strtolower($parts['scheme'].'://'.$parts['host']);

        return isset($parts['port']) ? $normalized.':'.$parts['port'] : $normalized;
    }

    /**
     * Die eigene Domain darf immer einbetten -- Vorschau und Testseite laufen
     * darueber.
     */
    private function ownOrigin(): ?string
    {
        return $this->normalize((string) config('app.url'));
    }
}
