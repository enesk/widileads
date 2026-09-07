<?php

declare(strict_types=1);

namespace App\Listeners\Lead;

use App\Events\Lead\LeadPurchased;
use App\Mail\Lead\LeadPurchased as LeadPurchasedMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Schickt dem Kaeufer die Kaufbestaetigung mit den Kontaktdaten (FB-054).
 *
 * In der Queue, anders als die Guthabenbuchung: Eine Mail, die nicht
 * hinausgeht, ist aergerlich, aber sie macht keinen Kauf ungueltig. Der Kauf
 * selbst ist zu diesem Zeitpunkt bereits festgeschrieben.
 *
 * Empfaenger ist die im Kaeuferprofil hinterlegte Adresse; ohne Angabe der
 * Benutzer, der gekauft hat.
 */
class SendLeadPurchasedNotification implements ShouldQueue
{
    public function handle(LeadPurchased $event): void
    {
        // Beim Autokauf (FB-056) gibt es keinen handelnden Benutzer. Die Mail
        // geht trotzdem hinaus -- sonst erfuehre der Kaeufer von einem Lead,
        // den er bezahlt hat, ueberhaupt nichts. Als Betrachter der
        // Kontaktdaten dient dann ein Benutzer des kaufenden Mandanten; der
        // Kaufbeleg gilt fuer den Mandanten, nicht fuer eine Person.
        $recipient = $event->actor instanceof User
            ? $event->actor
            : $event->buyer->users()->first();

        if (! $recipient instanceof User) {
            return;
        }

        $address = $this->notifyAddressFor($event) ?? $recipient->email;

        if (! is_string($address) || $address === '') {
            return;
        }

        Mail::to($address)->send(new LeadPurchasedMail($event->lead, $event->purchase, $recipient));
    }

    private function notifyAddressFor(LeadPurchased $event): ?string
    {
        $profile = $event->buyer->buyerProfile;

        $address = $profile?->notify_email;

        return is_string($address) && $address !== '' ? $address : null;
    }
}
