<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Constants\OnboardingStep;
use App\Events\Onboarding\OnboardingActivated;
use App\Events\Onboarding\OnboardingStepCompleted;
use App\Events\Onboarding\OnboardingWizardCompleted;
use App\Events\Onboarding\OnboardingWizardSkipped;
use App\Models\Tenant;
use App\Models\TenantOnboardingStep;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;

/**
 * Der Onboarding-Zustand eines Kaeufers -- die einzige Quelle der Wahrheit
 * (LP-ONBOARD-002).
 *
 * Wizard, Gate-Middleware, Dashboard-Checkliste, Tour und Mailstrecke lesen
 * hier und nirgends sonst. Der Grund ist nicht Sauberkeit: Sobald eine dieser
 * Stellen mitzaehlt, zeigt sie dem Kaeufer einen anderen Stand als die
 * naechste -- und der Admin-Trichter einen dritten. In der Sitzung steht
 * deshalb nichts ausser dem gerade angezeigten Wizard-Schritt.
 *
 * Alle Schreibwege sind idempotent: Ein zweites complete() fuer denselben
 * Schritt aendert nichts, wirft nicht und loest kein Ereignis aus. Dafuer
 * sorgt nicht eine Pruefung davor, sondern der Unique-Index
 * (tenant_id, step) -- das haelt auch zwei gleichzeitigen Anfragen stand.
 *
 * "Workspace" heisst in der Datenbank Tenant; die Methoden tragen trotzdem die
 * Sprache der Oberflaeche, weil das Onboarding durchgehend von Workspaces
 * redet.
 */
class OnboardingService
{
    /**
     * So lange gilt ein gemerkter Fortschritt. Kurz, weil der View-Composer
     * ihn in jedem Seitenaufbau braucht, aber ein frisch abgehakter Schritt
     * sofort sichtbar werden soll -- complete() wirft den Eintrag ohnehin weg.
     */
    private const CACHE_TTL_SECONDS = 30;

    /**
     * Ist die Rufnummer des Benutzers bestaetigt?
     *
     * Die Wahrheit steht in `users.phone_number_verified_at` und damit am
     * Benutzer, nicht am Workspace (LP-ONBOARD-003): Ein zweiter Kollege im
     * selben Workspace muss seine eigene Nummer bestaetigen. Der gleichnamige
     * Schritt in `tenant_onboarding_steps` ist nur die Spiegelung fuers
     * Abhaken in der Checkliste.
     */
    public function isPhoneVerified(User $user): bool
    {
        return $user->phone_number_verified_at !== null;
    }

    /**
     * Der Pflichtschritt, auf dem der Wizard gerade steht -- oder null, wenn
     * er nicht mehr zu zeigen ist.
     *
     * Null heisst zweierlei: abgeschlossen oder uebersprungen. Beides laeuft
     * fuer die Middleware auf dasselbe hinaus, naemlich durchlassen.
     */
    public function currentWizardStep(Tenant $workspace): ?OnboardingStep
    {
        if ($workspace->onboarding_completed_at !== null || $workspace->onboarding_skipped_at !== null) {
            return null;
        }

        $completed = $this->completedStepValues($workspace);

        foreach (OnboardingStep::wizardSteps() as $step) {
            if (! in_array($step->value, $completed, strict: true)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Hakt einen Schritt ab.
     *
     * Gibt zurueck, ob der Haken jetzt neu entstanden ist. Der zweite Aufruf
     * liefert false und tut nichts -- kein Ereignis, kein neuer Zeitstempel,
     * keine Ausnahme. Aufrufer duerfen den Rueckgabewert ignorieren; wer eine
     * Erfolgsmeldung anzeigt, sollte ihn lesen.
     *
     * @param  array<string, mixed>  $meta  Was beim Abhaken feststand (gewaehlte Branchen, Umkreis, Zahlungsweise)
     */
    public function complete(Tenant $workspace, OnboardingStep $step, ?User $user = null, array $meta = []): bool
    {
        if (! $this->recordStep($workspace, $step, $user, $meta)) {
            return false;
        }

        $this->forgetProgress($workspace);

        event(new OnboardingStepCompleted($workspace, $step, $user));

        if ($step->isWizardStep()) {
            $this->closeWizardIfComplete($workspace);
        }

        if ($step === OnboardingStep::FIRST_CALL) {
            // Einmalig, weil der Haken `first_call` nur einmal entstehen kann.
            event(new OnboardingActivated($workspace));
        }

        return true;
    }

    /**
     * Die Begleitangaben eines abgehakten Schritts -- null, wenn der Schritt
     * nicht abgehakt ist oder nichts dabei feststand.
     *
     * Gebraucht wird das vom Zahlungsschritt: Wer aus dem Checkout
     * zurueckkommt, soll sein Paket wieder ausgewaehlt sehen. Die Auswahl
     * steht in der Meta und nicht in der Sitzung -- sie ueberdauert damit auch
     * den Umweg ueber den Zahlungsanbieter.
     *
     * @return array<string, mixed>|null
     */
    public function meta(Tenant $workspace, OnboardingStep $step): ?array
    {
        $record = TenantOnboardingStep::query()
            ->where('tenant_id', $workspace->getKey())
            ->where('step', $step->value)
            ->first();

        return $record instanceof TenantOnboardingStep ? $record->meta : null;
    }

    /**
     * Schreibt die Begleitangaben eines bereits abgehakten Schritts neu.
     *
     * Der Haken selbst bleibt unberuehrt -- kein Ereignis, kein neuer
     * Zeitstempel, kein Wizard-Abschluss. Gebraucht wird das vom
     * Zahlungsschritt (LP-ONBOARD-010): Wer aus dem Checkout zurueckkommt und
     * ein anderes Paket waehlt, hat den Schritt laengst abgehakt --
     * complete() tut dann nichts, und in der Meta stuende weiter das alte
     * Paket. Der Admin-Trichter liest genau diese Meta.
     *
     * Gibt zurueck, ob eine Zeile veraendert wurde. False heisst: Der Schritt
     * ist gar nicht abgehakt -- dann ist complete() der richtige Weg.
     *
     * @param  array<string, mixed>  $meta
     */
    public function updateMeta(Tenant $workspace, OnboardingStep $step, array $meta): bool
    {
        $record = TenantOnboardingStep::query()
            ->where('tenant_id', $workspace->getKey())
            ->where('step', $step->value)
            ->first();

        if (! $record instanceof TenantOnboardingStep) {
            return false;
        }

        // Ueber das Modell und nicht als Massen-Update: `meta` ist ein
        // Array-Cast, und ein Massen-Update schiebt den Wert ungewandelt in
        // die Spalte.
        $record->meta = $meta === [] ? null : $meta;

        return $record->save();
    }

    /**
     * Der Kaeufer will den Wizard nicht ausfuellen.
     *
     * Danach sperrt der Wizard ihn nicht mehr, abgeschlossen ist er aber
     * nicht: Die Pflichtschritte bleiben in der Checkliste offen, und die
     * Mailstrecke erinnert daran. Zweimal aufgerufen bleibt der erste
     * Zeitstempel stehen.
     */
    public function skipWizard(Tenant $workspace): bool
    {
        if ($workspace->onboarding_skipped_at !== null) {
            return false;
        }

        $skippedAt = now();

        $affected = Tenant::query()
            ->whereKey($workspace->getKey())
            ->whereNull('onboarding_skipped_at')
            ->update(['onboarding_skipped_at' => $skippedAt]);

        if ($affected === 0) {
            return false;
        }

        $workspace->onboarding_skipped_at = $skippedAt;
        $this->forgetProgress($workspace);

        // Auch das Ueberspringen startet die Mailstrecke (LP-ONBOARD-014) --
        // gerade dann: Wer die Einrichtung wegklickt, ist der Kaeufer, der
        // sonst nie ankommt. Das Ereignis haengt unter der Bedingung oben und
        // feuert deshalb nur einmal.
        event(new OnboardingWizardSkipped($workspace));

        return true;
    }

    /**
     * Blendet die Aktivierungskarte im Dashboard fuer eine Weile aus
     * (LP-ONBOARD-012).
     *
     * Kein endgueltiges Wegklicken: Nach Ablauf erscheint die Karte wieder,
     * solange der Kaeufer nicht aktiviert ist. Ein Kaeufer, der nie anruft,
     * ist der Fall, um den es geht -- den duerfte ein einzelner Klick nicht
     * dauerhaft stumm schalten.
     *
     * Der Weg laeuft ueber diesen Dienst und nicht ueber die Komponente, weil
     * jeder Onboarding-Zustand hier liegt. Der Fortschritt selbst bleibt
     * unberuehrt; verworfen wird er trotzdem, weil die Karte ihn zusammen mit
     * dieser Spalte liest.
     */
    public function hideActivationCard(Tenant $workspace, int $days): void
    {
        $hiddenUntil = now()->addDays($days);

        Tenant::query()
            ->whereKey($workspace->getKey())
            ->update(['onboarding_card_hidden_until' => $hiddenUntil]);

        $workspace->onboarding_card_hidden_until = $hiddenUntil;

        $this->forgetProgress($workspace);
    }

    /**
     * Merkt sich, dass der Abschluss-Zustand der Aktivierungskarte gezeigt
     * wurde (LP-ONBOARD-012).
     *
     * "Du bist startklar." steht genau einen Besuch lang, danach ist die Karte
     * weg. Der Zeitstempel wird nur gesetzt, wenn er noch leer ist -- zwei
     * gleichzeitige Seitenaufbauten sollen nicht zwei verschiedene Zeiten
     * schreiben.
     *
     * Gibt zurueck, ob dieser Aufruf den Zustand verbraucht hat.
     */
    public function markActivationCardSeen(Tenant $workspace): bool
    {
        if ($workspace->onboarding_card_done_seen_at !== null) {
            return false;
        }

        $seenAt = now();

        $affected = Tenant::query()
            ->whereKey($workspace->getKey())
            ->whereNull('onboarding_card_done_seen_at')
            ->update(['onboarding_card_done_seen_at' => $seenAt]);

        if ($affected === 0) {
            return false;
        }

        $workspace->onboarding_card_done_seen_at = $seenAt;

        return true;
    }

    /**
     * Der ganze Stand auf einmal, fuer Checkliste, Tour und Mails.
     *
     * Eine Abfrage, 30 Sekunden gemerkt je Workspace. Der View-Composer haengt
     * das Ergebnis an das Portal-Layout, es wird also in jedem Seitenaufbau
     * gebraucht.
     */
    public function progress(Tenant $workspace): OnboardingProgress
    {
        return Cache::remember(
            $this->cacheKey($workspace),
            self::CACHE_TTL_SECONDS,
            fn (): OnboardingProgress => $this->buildProgress($workspace),
        );
    }

    /**
     * Gilt der Kaeufer als aktiviert?
     *
     * Genau dann, wenn er einen gekauften Lead angerufen hat. Nicht beim
     * ersten Kauf: Ein Kaeufer, der Leads sammelt und nicht anruft, ist der
     * Fall, den dieses Vorhaben verhindern soll.
     */
    public function isActivated(Tenant $workspace): bool
    {
        return in_array(
            OnboardingStep::FIRST_CALL->value,
            $this->completedStepValues($workspace),
            strict: true,
        );
    }

    /**
     * Wirft den gemerkten Fortschritt weg.
     *
     * Oeffentlich, weil auch andere am Stand ruehren koennen, ohne einen
     * Schritt abzuhaken -- etwa die Rufnummernbestaetigung, die am Benutzer
     * haengt.
     */
    public function forgetProgress(Tenant $workspace): void
    {
        Cache::forget($this->cacheKey($workspace));
    }

    /**
     * Traegt Schritte fuer einen Bestandskunden nach, ohne Ereignisse.
     *
     * Der Weg fuer `onboarding:backfill` und nur fuer ihn: Das Nachtragen ist
     * eine Datenkorrektur und kein Fortschritt. Liefe es ueber complete(),
     * bekaeme jeder Bestandskunde die Abschlussmails der Mailstrecke
     * (LP-ONBOARD-014) nachgeschickt -- Jahre nach dem ersten Kauf.
     *
     * Gibt zurueck, wie viele Haken neu entstanden sind. Beliebig oft
     * wiederholbar.
     *
     * @param  list<OnboardingStep>  $steps
     */
    public function backfill(Tenant $workspace, array $steps): int
    {
        $written = 0;

        foreach ($steps as $step) {
            $written += $this->recordStep($workspace, $step, null, []) ? 1 : 0;
        }

        if ($written === 0) {
            return 0;
        }

        $this->forgetProgress($workspace);

        if ($workspace->onboarding_completed_at === null && $this->hasAllWizardSteps($workspace)) {
            $completedAt = now();

            Tenant::query()
                ->whereKey($workspace->getKey())
                ->whereNull('onboarding_completed_at')
                ->update(['onboarding_completed_at' => $completedAt]);

            $workspace->onboarding_completed_at = $completedAt;
        }

        return $written;
    }

    /**
     * Die erledigten Schritte eines Workspaces, ungecacht.
     *
     * @return list<OnboardingStep>
     */
    public function completedSteps(Tenant $workspace): array
    {
        return array_map(
            static fn (string $value): OnboardingStep => OnboardingStep::from($value),
            $this->completedStepValues($workspace),
        );
    }

    /**
     * Legt den Haken an. False heisst: stand schon.
     *
     * Die Idempotenz haengt am Unique-Index und nicht an einer Pruefung davor
     * -- nur so halten auch zwei gleichzeitige Anfragen.
     *
     * @param  array<string, mixed>  $meta
     */
    private function recordStep(Tenant $workspace, OnboardingStep $step, ?User $user, array $meta): bool
    {
        try {
            TenantOnboardingStep::query()->create([
                'tenant_id' => $workspace->getKey(),
                'step' => $step,
                'completed_at' => now(),
                'completed_by_user_id' => $user?->getKey(),
                'meta' => $meta === [] ? null : $meta,
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    /**
     * Stehen alle Pflichtschritte des Wizards?
     */
    private function hasAllWizardSteps(Tenant $workspace): bool
    {
        $completed = $this->completedStepValues($workspace);

        foreach (OnboardingStep::wizardSteps() as $step) {
            if (! in_array($step->value, $completed, strict: true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Schreibt den Wizard-Abschluss fest, sobald alle Pflichtschritte stehen.
     *
     * Der Zeitstempel wird unter der Bedingung gesetzt, dass er noch leer ist.
     * Nur der Aufruf, der die Zeile wirklich veraendert hat, loest das
     * Ereignis aus -- sonst bekaeme ein Kaeufer bei zwei gleichzeitigen
     * Abschluessen zwei Abschlussmails.
     */
    private function closeWizardIfComplete(Tenant $workspace): void
    {
        if ($workspace->onboarding_completed_at !== null) {
            return;
        }

        if (! $this->hasAllWizardSteps($workspace)) {
            return;
        }

        $completedAt = now();

        $affected = Tenant::query()
            ->whereKey($workspace->getKey())
            ->whereNull('onboarding_completed_at')
            ->update(['onboarding_completed_at' => $completedAt]);

        if ($affected === 0) {
            return;
        }

        $workspace->onboarding_completed_at = $completedAt;
        $this->forgetProgress($workspace);

        event(new OnboardingWizardCompleted($workspace));
    }

    private function buildProgress(Tenant $workspace): OnboardingProgress
    {
        $completed = $this->completedStepValues($workspace);

        $steps = [];

        foreach (OnboardingStep::cases() as $step) {
            $steps[$step->value] = in_array($step->value, $completed, strict: true);
        }

        $done = count(array_filter($steps));
        $total = count($steps);

        return new OnboardingProgress(
            steps: $steps,
            percent: $total === 0 ? 0 : (int) round($done / $total * 100),
            nextAction: $this->nextAction($steps),
            wizardCompleted: $workspace->onboarding_completed_at !== null,
            wizardSkipped: $workspace->onboarding_skipped_at !== null,
            activated: $steps[OnboardingStep::FIRST_CALL->value] ?? false,
        );
    }

    /**
     * Der eine Schritt, zu dem die Oberflaeche auffordert.
     *
     * Reihenfolge: erst die Rufnummer (ohne sie kommt der Kaeufer an keinen
     * Screen), dann die Pflichtschritte des Wizards, dann die Aktivierung. Die
     * Touren stehen am Ende -- sie sind Begleitung und nicht Aufgabe.
     *
     * @param  array<string, bool>  $steps
     */
    private function nextAction(array $steps): ?OnboardingStep
    {
        $order = [
            OnboardingStep::PHONE_VERIFIED,
            ...OnboardingStep::wizardSteps(),
            ...OnboardingStep::activationSteps(),
        ];

        foreach ($order as $step) {
            if (! ($steps[$step->value] ?? false)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Die erledigten Schritte eines Workspaces als Zeichenketten.
     *
     * Bewusst ungecacht: currentWizardStep() und isActivated() entscheiden
     * ueber Sperren, und eine 30 Sekunden alte Antwort wuerde einen Kaeufer
     * nach dem Absenden erneut in denselben Wizard-Schritt schicken.
     *
     * @return list<string>
     */
    private function completedStepValues(Tenant $workspace): array
    {
        return TenantOnboardingStep::query()
            ->where('tenant_id', $workspace->getKey())
            ->pluck('step')
            ->map(static fn (mixed $step): string => $step instanceof OnboardingStep ? $step->value : (string) $step)
            ->all();
    }

    private function cacheKey(Tenant $workspace): string
    {
        return 'onboarding.progress.'.$workspace->getKey();
    }
}
