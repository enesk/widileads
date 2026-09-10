<?php

declare(strict_types=1);

namespace App\Mail\Lead;

use App\Dto\LeadContact;
use App\Funnel\Snapshots\SnapshotLabels;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadAnswer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Meldung an den Betreiber, dass aus seinem Funnel ein Lead entstanden ist
 * (FB-091).
 *
 * Die Kontaktdaten stehen im Klartext, und zwar nicht, weil diese Mail es sich
 * herausnimmt: Empfaenger sind ausschliesslich Adressen, die der Betreiber an
 * seinem eigenen Funnel gepflegt hat, und dem Betreiber gehoert der Lead. Wer
 * Klartext sieht, entscheidet trotzdem der LeadContactResolver -- der Listener
 * fragt ihn aus Sicht des Eigentuemer-Workspaces und reicht das Ergebnis hier
 * herein. Diese Klasse kennt die Kontaktspalten nicht.
 */
class LeadReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string>  $answers  Antworten als Label => Wert, bereits lesbar aufbereitet.
     */
    public function __construct(
        public Lead $lead,
        public LeadContact $contact,
        public array $answers,
    ) {}

    public function envelope(): Envelope
    {
        // `leads.funnel_id` ist nullable (nullOnDelete): Ein geloeschter Funnel
        // darf den Versand nicht in einen Fehler laufen lassen.
        $funnel = $this->lead->funnel;

        return new Envelope(
            subject: __('leads.notification.mail.subject', [
                'funnel' => $funnel instanceof Funnel
                    ? $funnel->name
                    : __('leads.notification.mail.unknown_funnel'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lead.received');
    }

    /**
     * Antworten eines Leads als Label => Wert.
     *
     * Gelesen wird die Beschriftung aus der veroeffentlichten Fassung, unter
     * der der Lead entstanden ist -- nicht aus dem Entwurf. Eine spaeter
     * umformulierte Frage darf eine alte Meldung nicht nachtraeglich umdeuten.
     *
     * @return array<string, string>
     */
    public static function answersOf(Lead $lead): array
    {
        $labels = SnapshotLabels::questions($lead->funnelVersion?->snapshot);
        $optionLabels = SnapshotLabels::options($lead->funnelVersion?->snapshot);
        $out = [];

        foreach ($lead->answers as $answer) {
            /** @var LeadAnswer $answer */
            $value = $answer->value;

            $readable = static fn (mixed $single): string => $optionLabels[$answer->field_key][(string) $single]
                ?? (string) $single;

            $out[$labels[$answer->field_key] ?? $answer->field_key] = match (true) {
                is_array($value) => implode(', ', array_map($readable, $value)),
                is_bool($value) => $value ? __('leads.notification.mail.yes') : __('leads.notification.mail.no'),
                is_scalar($value) => $readable($value),
                default => '',
            };
        }

        return $out;
    }
}
