<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\CallAttemptOutcome;
use App\Constants\LeadContactStatus;
use App\Mail\Lead\LeadDeadlineReminderMail;
use App\Models\CallAttempt;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Scopes\TenantScopes;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * FB-084 (Ticket #14): Erinnert Kaeufer, bevor die Frist zur
 * Erreichbarkeitspruefung ablaeuft.
 *
 * Erinnert wird nur, wo die Erinnerung noch etwas aendern kann: Der Lead ist
 * offen, die Frist laeuft innerhalb der naechsten 24 Stunden ab und die
 * Versuche sind noch nicht ausgeschoepft. Wer bereits genug dokumentierte
 * Versuche hat, bekommt beim Fristablauf ohnehin eine Gutschrift (FB-083) --
 * ihn zu draengen waere Laerm.
 *
 * Der Merker `leads.reminder_sent_at` haengt am Lead und nicht am Kaufbeleg,
 * weil die Frist dem Lead gehoert (FB-055): Bei einem geteilten Lead gehen die
 * Mails an alle Kaeufer, aber nur ein einziges Mal. Er wird VOR dem Versand
 * gesetzt, und zwar bedingt -- laufen zwei Laeufe gleichzeitig, gewinnt genau
 * einer den Lead, der andere sieht null betroffene Zeilen und geht weiter.
 */
class SendLeadDeadlineReminders extends Command
{
    protected $signature = 'leads:send-deadline-reminders';

    protected $description = 'Remind buyers about leads whose contact deadline elapses within 24 hours.';

    public function handle(): int
    {
        $sent = 0;

        // chunkById wie im Fristablauf: Eine Fristwelle kann tausende Leads
        // umfassen. Da der Lauf `reminder_sent_at` setzt, fallen bearbeitete
        // Zeilen aus der Menge -- Offset-Paging wuerde dabei Zeilen
        // ueberspringen, die Sortierung nach id nicht.
        $this->dueLeads()->chunkById(200, function ($leads) use (&$sent): void {
            foreach ($leads as $lead) {
                $sent += $this->remind($lead);
            }
        });

        Log::info('Fristerinnerungen verschickt.', ['sent' => $sent]);

        $this->info(sprintf('%d Erinnerung(en) verschickt.', $sent));

        return self::SUCCESS;
    }

    /**
     * Die faelligen Leads: offen, Frist laeuft in weniger als 24 Stunden ab,
     * noch keine Erinnerung raus.
     *
     * Ohne Mandanten-Scopes, weil der Lauf ohne Kaeuferkontext auf der Konsole
     * stattfindet -- mit Scope faende er nichts.
     *
     * Die untere Grenze ist `now()` und nicht `now()+23h`: Faellt ein
     * stuendlicher Lauf aus, ginge die Erinnerung sonst ersatzlos verloren.
     * Der Merker verhindert die Doppelversendung ohnehin, eine etwas
     * spaetere Erinnerung ist besser als gar keine.
     *
     * Der Cutover aus config('lead_calls.enforce_from') schuetzt Bestandsleads
     * wie im LeadResolver: Wer vor der Einfuehrung ausgeliefert wurde,
     * unterliegt dem Regelwerk nicht und braucht keine Fristmeldung.
     *
     * @return Builder<Lead>
     */
    private function dueLeads(): Builder
    {
        $query = Lead::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->where('contact_status', LeadContactStatus::OPEN)
            ->whereNull('reminder_sent_at')
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '>', now())
            ->where('deadline_at', '<=', now()->addDay());

        $enforceFrom = config('lead_calls.enforce_from');

        if ($enforceFrom !== null && $enforceFrom !== '') {
            $query->whereNotNull('delivered_at')
                ->where('delivered_at', '>=', Carbon::parse((string) $enforceFrom));
        }

        return $query;
    }

    /**
     * Erinnert alle Kaeufer eines Leads.
     *
     * @return int Zahl der verschickten Mails.
     */
    private function remind(Lead $lead): int
    {
        $attempts = $this->validFailedAttempts($lead);

        // Sind die Versuche bereits ausgeschoepft, entscheidet das Regelwerk
        // zugunsten des Kaeufers -- eine Mahnung waere schlicht falsch.
        if ($attempts >= (int) config('lead_calls.unreachable_attempts')) {
            return 0;
        }

        if (! $this->claim($lead)) {
            return 0;
        }

        $sent = 0;

        foreach ($this->purchasesOf($lead) as $purchase) {
            if ($this->send($lead, $purchase, $attempts)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Die gueltigen erfolglosen Versuche des Leads -- ueber alle Kaeufer
     * hinweg, wie im LeadResolver: Bei einem geteilten Lead zaehlt die Regel
     * den Lead als Ganzes.
     */
    private function validFailedAttempts(Lead $lead): int
    {
        return CallAttempt::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->where('lead_id', $lead->getKey())
            ->where('outcome', CallAttemptOutcome::FAILED_VALID)
            ->count();
    }

    /**
     * Setzt den Merker -- und sagt, ob dieser Lauf ihn gesetzt hat.
     *
     * Bedingtes UPDATE statt save(): Zwei gleichzeitige Laeufe wuerden sonst
     * beide erinnern, und der Kaeufer bekaeme dieselbe Mail doppelt.
     */
    private function claim(Lead $lead): bool
    {
        $now = now();

        $claimed = Lead::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->whereKey($lead->getKey())
            ->whereNull('reminder_sent_at')
            ->update(['reminder_sent_at' => $now]) > 0;

        if ($claimed) {
            $lead->setAttribute('reminder_sent_at', $now);
            $lead->syncOriginalAttribute('reminder_sent_at');
        }

        return $claimed;
    }

    /**
     * Alle Kaufbelege des Leads, samt Kaeufer und dessen Benutzern.
     *
     * @return Collection<int, LeadPurchase>
     */
    private function purchasesOf(Lead $lead): Collection
    {
        return LeadPurchase::query()
            ->where('lead_id', $lead->getKey())
            ->with(['buyer.buyerProfile', 'buyer.users'])
            ->get();
    }

    /**
     * Verschickt eine Erinnerung an einen Kaeufer.
     *
     * Der Mailversand haengt in der Queue; eine Ausnahme beim Einreihen -- ein
     * fehlender Redis, eine kaputte Adresse -- darf den Lauf nicht abbrechen,
     * die uebrigen Kaeufer sollen ihre Erinnerung trotzdem bekommen.
     */
    private function send(Lead $lead, LeadPurchase $purchase, int $attempts): bool
    {
        $address = $this->addressFor($purchase);

        if ($address === null) {
            return false;
        }

        try {
            Mail::to($address)->send(new LeadDeadlineReminderMail($lead, $purchase, $attempts));
        } catch (Throwable $exception) {
            Log::warning('Fristerinnerung konnte nicht verschickt werden.', [
                'lead_id' => (int) $lead->getKey(),
                'purchase_id' => (int) $purchase->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Empfaenger ist die im Kaeuferprofil hinterlegte Adresse; ohne Angabe der
     * erste Benutzer des Workspaces -- dieselbe Reihenfolge wie bei der
     * Kaufbestaetigung (FB-054).
     */
    private function addressFor(LeadPurchase $purchase): ?string
    {
        $buyer = $purchase->buyer;

        if (! $buyer instanceof Tenant) {
            return null;
        }

        $address = $buyer->buyerProfile?->notify_email;

        if (is_string($address) && $address !== '') {
            return $address;
        }

        $user = $buyer->users()->first();

        return $user instanceof User && $user->email !== '' ? $user->email : null;
    }
}
