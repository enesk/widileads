<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Funnel\QuestionTypes\PhoneType;
use App\Models\Lead;
use App\Models\PublicSession;

/**
 * Entscheidet, ob ein neu entstandener Lead kaufbar wird (FB-033).
 *
 * FB-023 sammelt Beobachtungen, FB-031 legt den Lead an -- beide bewerten
 * nichts (Architekturleitsatz 3). Hier wird zum ersten Mal geurteilt, mit den
 * Rohdaten vor sich statt mit einer bereits getroffenen Entscheidung.
 *
 * Geprueft wird in dieser Reihenfolge, weil der jeweils spezifischere Grund
 * gewinnen soll:
 *
 * 1. **Dublette** -- der Verweis aus FB-023 ist die konkreteste Aussage: Es gibt
 *    einen frueheren Lead mit denselben Kontaktdaten.
 * 2. **Spam** -- eine bewusste Taeuschung (verstecktes Feld ausgefuellt,
 *    unmenschlich schnell abgeschickt).
 * 3. **Kontakt nicht erreichbar** -- die schwaechste Aussage, weil sie aus dem
 *    Fehlen von Angaben schliesst.
 *
 * Greift nichts davon, wird der Lead `verfuegbar`. Jede Regel ist ueber
 * config/funnel.php einzeln abschaltbar: Ein zu scharfer Filter vernichtet
 * Leads, fuer die jemand bezahlt haette.
 *
 * Gewechselt wird ausschliesslich ueber LeadStateService::transition().
 */
class LeadScreeningService
{
    public function __construct(
        private readonly LeadStateService $leadStateService,
        private readonly LeadContactResolver $contacts,
        private readonly PhoneType $phoneType,
    ) {}

    /**
     * Prueft einen Lead und setzt seinen Zustand.
     *
     * Wiederholbar: Ein Lead, der nicht mehr im Zustand `neu` steht, wird
     * uebergangen. Ein zweiter Anlauf der Warteschlange aendert damit nichts.
     */
    public function screen(Lead $lead): void
    {
        if ($lead->lead_state !== LeadState::NEU) {
            return;
        }

        if (! (bool) config('funnel.screening.enabled')) {
            $this->release($lead, ['screening' => 'disabled']);

            return;
        }

        $rejection = $this->reasonForRejection($lead);

        if ($rejection === null) {
            $this->release($lead, $this->evidence($lead));

            return;
        }

        [$reason, $evidence] = $rejection;

        $this->leadStateService->transition(
            $lead,
            LeadState::UNGUELTIG,
            $reason,
            null,
            $evidence,
        );
    }

    /**
     * Der Grund, aus dem dieser Lead nicht kaufbar wird -- oder null.
     *
     * @return array{0: LeadTransitionReason, 1: array<string, mixed>}|null
     */
    public function reasonForRejection(Lead $lead): ?array
    {
        if ($this->isDuplicate($lead)) {
            return [
                LeadTransitionReason::DUPLICATE,
                ['duplicate_of_lead_id' => $lead->duplicate_of_lead_id],
            ];
        }

        $spamSignals = $this->trippedSpamSignals($lead);

        if ($this->isSpam($spamSignals)) {
            return [LeadTransitionReason::SPAM, ['spam_signals' => $spamSignals]];
        }

        if ($this->hasNoReachableContact($lead)) {
            return [
                LeadTransitionReason::IMPLAUSIBLE_CONTACT,
                ['phone_usable' => false, 'email_disposable' => true],
            ];
        }

        return null;
    }

    private function isDuplicate(Lead $lead): bool
    {
        return (bool) config('funnel.screening.reject_duplicates')
            && $lead->duplicate_of_lead_id !== null;
    }

    /**
     * Weder Telefon noch E-Mail sind brauchbar.
     *
     * Bewusst als Und-Bedingung: Eine Wegwerf-Adresse allein macht einen Lead
     * nicht wertlos, solange die Telefonnummer stimmt -- und umgekehrt. Erst
     * wenn beide Wege ausfallen, ist der Lead fuer einen Kaeufer wertlos.
     *
     * Eine fehlende Telefonnummer zaehlt als nicht brauchbar: Sie macht den
     * Lead genauso unerreichbar wie eine unlesbare.
     */
    private function hasNoReachableContact(Lead $lead): bool
    {
        if (! (bool) config('funnel.screening.reject_implausible_contact')) {
            return false;
        }

        return ! $this->hasUsablePhone($lead) && $this->hasDisposableEmail($lead);
    }

    private function hasUsablePhone(Lead $lead): bool
    {
        // Auch die Pruefung liest die Kontaktdaten ueber LeadContact -- die
        // Rohspalten kennt nur diese Klasse (FB-032).
        $phone = $this->contacts->internal($lead)->phone;

        if ($phone === null || trim($phone) === '') {
            return false;
        }

        return $this->phoneType->toE164($phone) !== null;
    }

    private function hasDisposableEmail(Lead $lead): bool
    {
        $email = $this->contacts->internal($lead)->email;

        if ($email === null) {
            return false;
        }

        $atPosition = mb_strrpos($email, '@');

        if ($atPosition === false) {
            return false;
        }

        $domain = mb_strtolower(mb_substr($email, $atPosition + 1));

        /** @var list<string> $disposable */
        $disposable = config('funnel.screening.disposable_email_domains', []);

        return in_array($domain, array_map('mb_strtolower', $disposable), true);
    }

    /**
     * Die Signale aus FB-023, die tatsaechlich ausgeloest haben.
     *
     * @return list<string>
     */
    public function trippedSpamSignals(Lead $lead): array
    {
        $session = $lead->publicSession;

        // Keine Sitzung, keine Signale -- etwa nachdem sie aufgeraeumt wurde.
        if (! $session instanceof PublicSession) {
            return [];
        }

        $signals = $session->spam_signals ?? [];
        $tripped = [];

        foreach ($signals as $signal => $value) {
            if ($value === true) {
                $tripped[] = (string) $signal;
            }
        }

        return $tripped;
    }

    /**
     * @param  list<string>  $trippedSignals
     */
    private function isSpam(array $trippedSignals): bool
    {
        if ($trippedSignals === []) {
            return false;
        }

        /** @var list<string> $decisive */
        $decisive = config('funnel.screening.decisive_spam_signals', []);

        if (array_intersect($trippedSignals, $decisive) !== []) {
            return true;
        }

        return count($trippedSignals) >= (int) config('funnel.screening.spam_signal_threshold');
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function release(Lead $lead, array $evidence): void
    {
        $this->leadStateService->transition(
            $lead,
            LeadState::VERFUEGBAR,
            LeadTransitionReason::SCREENING_PASSED,
            null,
            $evidence,
        );
    }

    /**
     * Was bei der Freigabe festgehalten wird -- damit spaeter nachvollziehbar
     * ist, auf welcher Grundlage der Lead kaufbar wurde.
     *
     * @return array<string, mixed>
     */
    private function evidence(Lead $lead): array
    {
        return [
            'phone_usable' => $this->hasUsablePhone($lead),
            'email_disposable' => $this->hasDisposableEmail($lead),
            'spam_signals' => $this->trippedSpamSignals($lead),
        ];
    }
}
