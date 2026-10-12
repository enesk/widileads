<?php

declare(strict_types=1);

namespace App\Jobs\Onboarding;

use App\Constants\OnboardingMailKey;
use App\Constants\OnboardingStep;
use App\Mail\Onboarding\BillingExplainedMail;
use App\Mail\Onboarding\LeadsWaitingMail;
use App\Mail\Onboarding\NudgeMail;
use App\Mail\Onboarding\OnboardingMailable;
use App\Mail\Onboarding\WelcomeMail;
use App\Models\OnboardingMailSent;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Onboarding\OnboardingMailContent;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Verschickt eine Mail der Onboarding-Strecke -- oder stellt fest, dass sie
 * sich erledigt hat (LP-ONBOARD-014).
 *
 * Vier Jobs werden beim Verlassen des Wizards auf einmal eingereiht (Tag 0,
 * 1, 3, 7). Jeder entscheidet erst beim Laufen, ob er noch gebraucht wird:
 * Wer am zweiten Tag kauft, bekommt die Erklaerung zur Abrechnung nicht mehr,
 * und wer am sechsten anruft, nicht den Anstupser. Die Pruefung gehoert
 * hierher und nicht in die Planung -- beim Planen steht noch nichts fest.
 *
 * **Die Reihenfolge im handle() ist die Fachlogik:**
 *
 *  1. Workspace weg -> nichts, nur ins Protokoll. Ohne Zeile, denn der
 *     Fremdschluessel haette nichts mehr, woran er haengt.
 *  2. Nachtruhe (21-8 Uhr Europe/Berlin) -> derselbe Job noch einmal, auf
 *     8:30 verschoben. Keine Mail um drei Uhr nachts.
 *  3. Kein Empfaenger, abgemeldet oder Abbruchregel greift -> Zeile mit
 *     Grund, keine Mail.
 *  4. Zeile anlegen, Mail zustellen, danach `sent_at` setzen.
 *
 * Schritt 4 in genau dieser Reihenfolge ist die Idempotenz des Tickets: Die
 * Zeile entsteht vor der Zustellung und laeuft beim zweiten Lauf in den
 * Unique-Index (tenant_id, key) -- es gibt kein Duplikat, auch nicht, wenn
 * der Mailprovider die Verbindung nach der Annahme abreissen laesst.
 * Umgekehrt darf ein Wiederholungsversuch die Mail nachreichen, solange
 * `sent_at` noch leer ist: Dann ist die Zustellung nachweislich nicht
 * durchgekommen.
 */
class SendOnboardingMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Die Queue dieser Strecke. */
    public const QUEUE = 'mail';

    /** Ab dieser Stunde wird nichts mehr zugestellt. */
    private const QUIET_FROM_HOUR = 21;

    /** Vor dieser Uhrzeit auch nicht -- verschoben wird auf 8:30. */
    private const QUIET_UNTIL_HOUR = 8;

    private const QUIET_UNTIL_MINUTE = 30;

    /** Die Zeitzone, in der der Kaeufer lebt. */
    private const TIMEZONE = 'Europe/Berlin';

    public int $tries = 3;

    /**
     * Kurz, dann lange: eine Stoerung beim Mailprovider ist entweder in einer
     * Minute vorbei oder dauert.
     *
     * @var list<int>
     */
    public array $backoff = [60, 600];

    /**
     * Der Workspace wandert als Kennung durch die Queue und nicht als Modell:
     * Zwischen Planung und Versand liegen bis zu sieben Tage, ein
     * serialisierter Zustand waere dann Geschichte.
     */
    public function __construct(
        public int $workspaceId,
        public OnboardingMailKey $key,
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function handle(OnboardingService $onboarding, OnboardingMailContent $content): void
    {
        $workspace = Tenant::query()->find($this->workspaceId);

        if (! $workspace instanceof Tenant) {
            // Geloescht. Es gibt keine Zeile zu schreiben -- der
            // Fremdschluessel haette kein Ziel, und die alten Zeilen sind mit
            // dem Workspace weggefallen.
            Log::info('Onboarding-Mail entfaellt, Workspace geloescht.', [
                'tenant_id' => $this->workspaceId,
                'key' => $this->key->value,
            ]);

            return;
        }

        $sendAt = self::sendableAt(now());

        if ($sendAt !== null && $this->canPostpone()) {
            // Nachtruhe. Der Job plant sich selbst neu statt zu schlafen --
            // ein wartender Worker blockiert die ganze Queue.
            self::dispatch($this->workspaceId, $this->key)->delay($sendAt);

            return;
        }

        $recipient = $workspace->users()->first();

        if (! $recipient instanceof User || ! is_string($recipient->email) || $recipient->email === '') {
            $this->claim($workspace, 'no_recipient');

            return;
        }

        $reason = $this->skipReason($workspace, $recipient, $onboarding, $content);

        if ($reason !== null) {
            $this->claim($workspace, $reason);

            return;
        }

        $record = $this->claim($workspace);

        if (! $record instanceof OnboardingMailSent) {
            // Schon erledigt -- versendet oder uebersprungen.
            return;
        }

        Mail::to($recipient->email)->send($this->mailable($workspace, $recipient));

        $record->sent_at = now();
        $record->save();
    }

    /**
     * Wann diese Mail fruehestens rausgehen darf -- null heisst "jetzt".
     *
     * Oeffentlich und statisch, weil zwei Stellen dieselbe Regel brauchen:
     * der Zuhoerer beim Planen und dieser Job beim Laufen. Eine Mail, die
     * planmaessig um 19 Uhr faellig war und wegen eines Staus in der Queue um
     * 23 Uhr drankommt, darf nicht trotzdem raus.
     */
    public static function sendableAt(Carbon $at): ?Carbon
    {
        $local = $at->copy()->setTimezone(self::TIMEZONE);

        if ($local->hour >= self::QUIET_UNTIL_HOUR && $local->hour < self::QUIET_FROM_HOUR) {
            return null;
        }

        $target = $local->copy()
            ->setTime(self::QUIET_UNTIL_HOUR, self::QUIET_UNTIL_MINUTE);

        if ($local->hour >= self::QUIET_FROM_HOUR) {
            // Nach 21 Uhr: am naechsten Morgen.
            $target->addDay();
        }

        return $target->setTimezone($at->getTimezone());
    }

    /**
     * Darf dieser Job sich selbst vertagen?
     *
     * Auf der Verbindung `sync` nicht: Dort fuehrt dispatch() den Job sofort
     * aus und ignoriert die Verzoegerung -- aus dem Verschieben auf 8:30
     * wuerde eine Endlosschleife. Sync heisst ohnehin Konsole oder Test, und
     * dort ist die Nachtruhe nicht der Punkt.
     */
    private function canPostpone(): bool
    {
        return config('queue.default') !== 'sync';
    }

    /**
     * Warum diese Mail nicht rausgeht -- null heisst: sie geht raus.
     */
    private function skipReason(
        Tenant $workspace,
        User $recipient,
        OnboardingService $onboarding,
        OnboardingMailContent $content,
    ): ?string {
        if ($recipient->onboarding_mails_opt_out) {
            return 'opted_out';
        }

        $progress = $onboarding->progress($workspace);

        return match ($this->key) {
            // Die Begruessung geht immer raus.
            OnboardingMailKey::WELCOME => null,

            OnboardingMailKey::BILLING_EXPLAINED => $progress->isCompleted(OnboardingStep::FIRST_PURCHASE)
                ? 'already_purchased'
                : null,

            OnboardingMailKey::LEADS_WAITING => match (true) {
                $progress->isCompleted(OnboardingStep::FIRST_PURCHASE) => 'already_purchased',
                $content->matchingLeadCount($workspace) < OnboardingMailContent::LEADS_WAITING_MINIMUM => 'not_enough_leads',
                default => null,
            },

            OnboardingMailKey::NUDGE => $progress->activated
                ? 'already_activated'
                : null,
        };
    }

    /**
     * Meldet den Versand an.
     *
     * Gibt die Zeile zurueck, auf der weitergearbeitet werden darf -- oder
     * null, wenn diese Mail fuer diesen Workspace schon erledigt ist. Der
     * Unique-Index entscheidet das und nicht eine Abfrage davor: Zwei
     * gleichzeitige Laeufe sollen nicht beide "ist noch frei" lesen.
     */
    private function claim(Tenant $workspace, ?string $skippedReason = null): ?OnboardingMailSent
    {
        try {
            return OnboardingMailSent::query()->create([
                'tenant_id' => $workspace->getKey(),
                'key' => $this->key,
                'sent_at' => null,
                'skipped_reason' => $skippedReason,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Schon da. Nur ein angemeldeter, aber nicht zugestellter Versand
            // darf weiterlaufen -- das ist der Wiederholungsversuch nach einem
            // Fehler des Mailproviders.
            $existing = OnboardingMailSent::query()
                ->where('tenant_id', $workspace->getKey())
                ->where('key', $this->key->value)
                ->first();

            if ($skippedReason !== null) {
                return null;
            }

            if (! $existing instanceof OnboardingMailSent) {
                return null;
            }

            return $existing->sent_at === null && $existing->skipped_reason === null
                ? $existing
                : null;
        }
    }

    private function mailable(Tenant $workspace, User $recipient): OnboardingMailable
    {
        return match ($this->key) {
            OnboardingMailKey::WELCOME => new WelcomeMail($workspace, $recipient),
            OnboardingMailKey::BILLING_EXPLAINED => new BillingExplainedMail($workspace, $recipient),
            OnboardingMailKey::LEADS_WAITING => new LeadsWaitingMail($workspace, $recipient),
            OnboardingMailKey::NUDGE => new NudgeMail($workspace, $recipient),
        };
    }
}
