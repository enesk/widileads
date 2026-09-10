<?php

declare(strict_types=1);

namespace App\Listeners\Lead;

use App\Constants\LeadState;
use App\Events\Lead\LeadStateChanged;
use App\Mail\Lead\LeadReceived;
use App\Models\Funnel;
use App\Services\LeadContactResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Meldet dem Betreiber per Mail, dass aus seinem Funnel ein Lead entstanden ist
 * (FB-091).
 *
 * Ausgeloest wird beim Wechsel nach `verfuegbar`, nicht schon bei LeadCreated.
 * Ein frisch angelegter Lead steht in `neu` und hat die Pruefung aus FB-033
 * noch vor sich: Dublette, Wegwerfadresse, unwaehlbare Nummer. Wer bei
 * LeadCreated meldet, verschickt genau diese Ausschussware als Mail und
 * entwertet die Meldung. Zwischen beiden Zeitpunkten liegen Sekunden, weil die
 * Pruefung ebenfalls in der Warteschlange laeuft.
 *
 * Gemeldet wird nur der Uebergang aus `neu`. Ein Lead, der spaeter aus einer
 * Reservierung nach `verfuegbar` zurueckfaellt, ist nicht neu -- eine zweite
 * Mail dazu waere schlicht falsch.
 *
 * In der Warteschlange: Eine Mail, die nicht hinausgeht, darf keinen
 * Zustandswechsel scheitern lassen.
 */
class SendNewLeadNotification implements ShouldQueue
{
    public function __construct(private readonly LeadContactResolver $contacts) {}

    public function handle(LeadStateChanged $event): void
    {
        if ($event->to !== LeadState::VERFUEGBAR || $event->from !== LeadState::NEU) {
            return;
        }

        $funnel = $event->lead->funnel;

        if (! $funnel instanceof Funnel) {
            return;
        }

        $recipients = $funnel->notificationEmails();

        if ($recipients === []) {
            return;
        }

        $lead = $event->lead->loadMissing(['answers', 'funnel', 'funnelVersion']);

        // Wer den Klartext sieht, entscheidet der Resolver -- nicht diese
        // Stelle. Gefragt wird aus Sicht des Workspaces, dem der Funnel
        // gehoert: Der Empfaenger ist eine Adresse, kein angemeldeter Benutzer,
        // und die Adressen hat der Eigentuemer selbst gepflegt.
        $contact = $this->contacts->forTenant($lead, $funnel->tenant);

        $answers = LeadReceived::answersOf($lead);

        // Je Empfaenger ein eigener Versand statt einer Mail an alle: Die
        // Adressen eines Betreibers gehoeren nicht zwingend zusammen, und
        // niemand soll aus dem An-Feld die uebrigen ablesen.
        //
        // Je Versand eine FRISCHE Mailable: Mail::to() traegt den Empfaenger in
        // die Instanz ein. Wird dieselbe Instanz zweimal verschickt, sammelt sie
        // die Adressen an -- der zweite Empfaenger saehe dann auch den ersten.
        foreach ($recipients as $recipient) {
            // Ein stummer Mailserver darf den Zustandswechsel nicht mitreissen:
            // Mit QUEUE_CONNECTION=sync laeuft dieser Listener im Request mit,
            // und der Lead ist zu diesem Zeitpunkt bereits verfuegbar.
            try {
                Mail::to($recipient)->send(new LeadReceived($lead, $contact, $answers));
            } catch (Throwable $exception) {
                Log::warning('Lead-Benachrichtigung konnte nicht verschickt werden.', [
                    'lead_id' => $lead->getKey(),
                    'recipient' => $recipient,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }
}
