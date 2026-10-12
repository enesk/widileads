<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\OnboardingMailKey;
use App\Constants\TenantType;
use App\Models\OnboardingMailSent;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * LP-ONBOARD-039: Nimmt Bestandskaeufern die Onboarding-Mailstrecke ab, bevor
 * sie das erste Mal laufen kann.
 *
 * `onboarding:backfill` hakt Schritte ereignislos nach und schickt deshalb
 * keine Mails los. Das deckt aber nicht jeden Weg aus dem Wizard: Ein
 * Bestandskunde, fuer den der Backfill nicht oder nur halb lief, durchlaeuft
 * den Wizard irgendwann doch -- oder ueberspringt ihn -- und loest damit
 * OnboardingWizardCompleted/Skipped aus. Drei der vier Mails fangen die
 * Abbruchregeln dann ab, die Begruessung "Du bist startklar" hat bewusst
 * keine. Sie ginge an einen Kaeufer, der seit Monaten kauft und anruft.
 *
 * Statt der Begruessung eine Altersregel zu geben, wird die Strecke hier fuer
 * jeden vorhandenen Kaeufer-Workspace als erledigt eingetragen: vier Zeilen
 * in `onboarding_mails_sent` mit `skipped_reason = 'existing_workspace'`,
 * `sent_at` leer. Danach ist nachweisbar, warum keine Mail kam, statt sich auf
 * eine Rechnung im Job zu verlassen.
 *
 * Idempotent ueber den Unique-Index (tenant_id, key): Zeilen, die es schon
 * gibt -- versendet, uebersprungen oder angemeldet --, bleiben unberuehrt.
 * Der Lauf nimmt also keinem neuen Kaeufer eine Mail weg, die bereits
 * unterwegs ist.
 *
 * Laeuft nicht automatisch und gehoert in kein Scheduler-Eintrag: Er wird beim
 * Ausrollen einmal von Hand gestartet, zusammen mit `onboarding:backfill`.
 * Wer ihn spaeter noch einmal startet, sollte `--created-before` setzen --
 * sonst trifft er auch Kaeufer, die sich nach dem Ausrollen angemeldet haben
 * und die Strecke zu Recht bekommen wuerden.
 */
class OnboardingMailsSuppress extends Command
{
    /** Der Grund, der in `skipped_reason` landet. */
    public const REASON = 'existing_workspace';

    protected $signature = 'onboarding:suppress-mails
        {--created-before= : Nur Kaeufer, die vor diesem Zeitpunkt angelegt wurden (z. B. 2026-10-12)}
        {--dry-run : Nur anzeigen, nichts schreiben}';

    protected $description = 'Traegt die Onboarding-Mailstrecke fuer vorhandene Kaeufer-Workspaces als erledigt ein.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $createdBefore = $this->createdBefore();

        if ($createdBefore === false) {
            $this->error('--created-before ist kein lesbarer Zeitpunkt.');

            return self::FAILURE;
        }

        $query = Tenant::query()
            ->where('type', TenantType::BUYER)
            ->orderBy('id');

        if ($createdBefore instanceof Carbon) {
            $query->where('created_at', '<', $createdBefore);
        }

        $touched = 0;
        $written = 0;

        $query->chunkById(200, function (Collection $workspaces) use ($dryRun, &$touched, &$written): void {
            /** @var Collection<int, Tenant> $workspaces */
            $touched += $workspaces->count();
            $written += $dryRun
                ? $this->countMissing($workspaces)
                : $this->suppress($workspaces);
        });

        if ($touched === 0) {
            $this->info('Kein Kaeufer-Workspace gefunden. Es gibt nichts zu unterdruecken.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d Kaeufer geprueft, %d Mails %s.',
            $touched,
            $written,
            $dryRun ? 'waeren zu unterdruecken' : 'als erledigt eingetragen',
        ));

        return self::SUCCESS;
    }

    /**
     * null = keine Grenze, false = unlesbare Eingabe.
     */
    private function createdBefore(): Carbon|null|false
    {
        $raw = $this->option('created-before');

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse(trim($raw));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Schreibt die fehlenden Zeilen und gibt zurueck, wie viele es waren.
     *
     * insertOrIgnore statt einer Abfrage davor: Der Unique-Index entscheidet,
     * was fehlt, und zwei gleichzeitige Laeufe koennen sich nicht in die Quere
     * kommen. Die Zeitstempel muessen dabei von Hand mit -- der Massenweg
     * geht am Modell vorbei.
     *
     * @param  Collection<int, Tenant>  $workspaces
     */
    private function suppress(Collection $workspaces): int
    {
        $now = now();
        $rows = [];

        foreach ($workspaces as $workspace) {
            foreach (OnboardingMailKey::cases() as $key) {
                $rows[] = [
                    'tenant_id' => $workspace->getKey(),
                    'key' => $key->value,
                    'sent_at' => null,
                    'skipped_reason' => self::REASON,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return OnboardingMailSent::query()->insertOrIgnore($rows);
    }

    /**
     * @param  Collection<int, Tenant>  $workspaces
     */
    private function countMissing(Collection $workspaces): int
    {
        $existing = OnboardingMailSent::query()
            ->whereIn('tenant_id', $workspaces->modelKeys())
            ->count();

        return $workspaces->count() * count(OnboardingMailKey::cases()) - $existing;
    }
}
