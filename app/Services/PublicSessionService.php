<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\SessionEventType;
use App\Models\FunnelVersion;
use App\Models\PublicSession;
use App\Models\SessionEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Fuehrt Sitzungen und Verlauf der oeffentlichen Funnel-Strecke (FB-021).
 *
 * Der Teilfortschritt wird nach jedem Schritt gespeichert, damit ein Reload,
 * ein Netzabbruch oder ein Geraetewechsel den Endkunden nicht von vorn anfangen
 * laesst -- jede abgebrochene Strecke ist eine verlorene Anfrage.
 *
 * Der Verlauf ist append-only und die einzige Quelle fuer den tatsaechlich
 * gegangenen Weg.
 */
class PublicSessionService
{
    /**
     * Bestehende Sitzung fortsetzen oder eine neue beginnen.
     *
     * Fortgesetzt wird nur, was zur selben Funnel-Fassung gehoert und noch offen
     * ist: Eine abgeschlossene Anfrage darf nicht versehentlich weitergefuellt
     * werden, und nach einer Neuveroeffentlichung passt der alte Fortschritt
     * nicht mehr zu den Fragen.
     */
    public function startOrResume(FunnelVersion $version, ?string $token): PublicSession
    {
        $session = $token === null ? null : PublicSession::query()
            ->where('token', $token)
            ->where('funnel_version_id', $version->getKey())
            ->whereNull('completed_at')
            ->first();

        if ($session !== null) {
            // Eine abgebrochene, aber wiederaufgenommene Sitzung laeuft weiter.
            if ($session->abandoned_at !== null) {
                $session->forceFill(['abandoned_at' => null])->save();
            }

            $this->touch($session);

            return $session;
        }

        $session = PublicSession::query()->create([
            'funnel_version_id' => $version->getKey(),
            'token' => (string) Str::ulid(),
            'answers' => [],
            'current_step' => null,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        $this->record($session, SessionEventType::VIEW);

        return $session;
    }

    /**
     * Teilfortschritt sichern.
     *
     * @param  array<string, mixed>  $answers
     */
    public function saveProgress(PublicSession $session, array $answers, ?int $currentStep): void
    {
        $session->forceFill([
            'answers' => $answers,
            'current_step' => $currentStep,
            'last_activity_at' => now(),
        ])->save();
    }

    public function record(PublicSession $session, SessionEventType $type, ?int $stepPosition = null): SessionEvent
    {
        $this->touch($session);

        return SessionEvent::query()->create([
            'session_id' => $session->getKey(),
            'type' => $type,
            'step_position' => $stepPosition,
            'created_at' => now(),
        ]);
    }

    public function complete(PublicSession $session): void
    {
        $this->record($session, SessionEventType::SUBMIT, $session->current_step);

        $session->forceFill([
            'completed_at' => now(),
            'last_activity_at' => now(),
        ])->save();
    }

    /**
     * Markiert Sitzungen als abgebrochen, die laenger als die konfigurierte
     * Frist nichts mehr getan haben.
     *
     * @return int Anzahl der markierten Sitzungen
     */
    public function abandonStale(?Carbon $now = null): int
    {
        $now ??= now();
        $inactiveSince = $now->copy()->subMinutes((int) config('funnel.public.abandon_after_minutes'));

        $abandoned = 0;

        PublicSession::query()
            ->stale($inactiveSince)
            ->chunkById(100, function ($sessions) use (&$abandoned): void {
                foreach ($sessions as $session) {
                    $session->forceFill(['abandoned_at' => now()])->save();

                    SessionEvent::query()->create([
                        'session_id' => $session->getKey(),
                        'type' => SessionEventType::ABANDON,
                        'step_position' => $session->current_step,
                        'created_at' => now(),
                    ]);

                    $abandoned++;
                }
            });

        return $abandoned;
    }

    private function touch(PublicSession $session): void
    {
        $session->forceFill(['last_activity_at' => now()])->save();
    }
}
