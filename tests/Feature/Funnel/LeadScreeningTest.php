<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\PublishFunnel;
use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Constants\QuestionType;
use App\Events\Lead\LeadCreated;
use App\Livewire\Funnel\FunnelRunner;
use App\Models\Funnel;
use App\Models\FunnelQuestion;
use App\Models\FunnelResult;
use App\Models\FunnelStep;
use App\Models\Lead;
use App\Models\LeadStateLog;
use App\Models\PublicSession;
use App\Services\LeadScreeningService;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * FB-033: Die Pruefung, die aus einem neuen Lead einen kaufbaren macht.
 *
 * Hier wird zum ersten Mal geurteilt -- FB-023 hat nur beobachtet, FB-031 nur
 * angelegt. Entsprechend heikel ist die Balance: Ein zu scharfer Filter
 * vernichtet Leads, fuer die jemand bezahlt haette, ein zu weicher verkauft
 * Muell.
 */
class LeadScreeningTest extends FeatureTest
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $spamSignals
     */
    private function newLead(array $attributes = [], ?array $spamSignals = null): Lead
    {
        $session = $spamSignals === null
            ? null
            : PublicSession::factory()->completed()->create(['spam_signals' => $spamSignals]);

        return Lead::factory()->inState(LeadState::NEU)->create([
            'tenant_id' => $this->createTenant()->id,
            'phone_e164' => '+493012345678',
            'email_normalized' => 'mara@example.com',
            'public_session_id' => $session?->id,
            ...$attributes,
        ]);
    }

    private function screen(Lead $lead): Lead
    {
        app(LeadScreeningService::class)->screen($lead);

        return $lead->fresh();
    }

    private function lastReason(Lead $lead): LeadTransitionReason
    {
        return LeadStateLog::query()
            ->where('lead_id', $lead->id)
            ->latest('id')
            ->firstOrFail()
            ->reason;
    }

    public function test_a_clean_lead_becomes_available(): void
    {
        $lead = $this->newLead();

        $screened = $this->screen($lead);

        $this->assertSame(LeadState::VERFUEGBAR, $screened->lead_state);
        $this->assertSame(LeadTransitionReason::SCREENING_PASSED, $this->lastReason($lead));
    }

    public function test_a_duplicate_becomes_invalid(): void
    {
        $earlier = $this->newLead();
        $lead = $this->newLead(['duplicate_of_lead_id' => $earlier->id]);

        $screened = $this->screen($lead);

        $this->assertSame(LeadState::UNGUELTIG, $screened->lead_state);
        $this->assertSame(LeadTransitionReason::DUPLICATE, $this->lastReason($lead));

        // Der Verweis bleibt am Lead stehen -- er ist die Begruendung.
        $this->assertSame($earlier->id, $screened->duplicate_of_lead_id);
    }

    public function test_unusable_phone_and_disposable_email_only_reject_together(): void
    {
        // Nur die Telefonnummer unbrauchbar: die E-Mail traegt den Lead.
        $onlyPhoneBroken = $this->screen($this->newLead(['phone_e164' => 'keine-nummer']));
        $this->assertSame(LeadState::VERFUEGBAR, $onlyPhoneBroken->lead_state);

        // Nur die Adresse eine Wegwerf-Adresse: die Telefonnummer traegt ihn.
        $onlyEmailDisposable = $this->screen($this->newLead(['email_normalized' => 'bot@mailinator.com']));
        $this->assertSame(LeadState::VERFUEGBAR, $onlyEmailDisposable->lead_state);

        // Erst beides zusammen macht ihn unerreichbar.
        $both = $this->newLead([
            'phone_e164' => 'keine-nummer',
            'email_normalized' => 'bot@mailinator.com',
        ]);

        $this->assertSame(LeadState::UNGUELTIG, $this->screen($both)->lead_state);
        $this->assertSame(LeadTransitionReason::IMPLAUSIBLE_CONTACT, $this->lastReason($both));

        // Und eine fehlende Nummer zaehlt wie eine unlesbare.
        $missingPhone = $this->newLead([
            'phone_e164' => null,
            'email_normalized' => 'bot@mailinator.com',
        ]);

        $this->assertSame(LeadState::UNGUELTIG, $this->screen($missingPhone)->lead_state);
    }

    public function test_a_decisive_spam_signal_rejects_alone_a_weak_one_does_not(): void
    {
        // Das versteckte Feld fuellt kein Mensch aus -- das genuegt allein.
        $honeypot = $this->newLead(spamSignals: [
            'honeypot_tripped' => true,
            'submitted_too_fast' => false,
        ]);

        $this->assertSame(LeadState::UNGUELTIG, $this->screen($honeypot)->lead_state);
        $this->assertSame(LeadTransitionReason::SPAM, $this->lastReason($honeypot));

        // Die Zeitfalle allein nicht: Autofill und ein schneller Leser loesen
        // sie ebenfalls aus.
        $tooFast = $this->newLead(spamSignals: [
            'honeypot_tripped' => false,
            'submitted_too_fast' => true,
        ]);

        $this->assertSame(LeadState::VERFUEGBAR, $this->screen($tooFast)->lead_state);

        // Zwei schwache Signale zusammen erreichen die Schwelle.
        $twoWeak = $this->newLead(spamSignals: [
            'submitted_too_fast' => true,
            'rate_limited' => true,
        ]);

        $this->assertSame(LeadState::UNGUELTIG, $this->screen($twoWeak)->lead_state);
    }

    /**
     * Die ganze Kette ueber drei Tickets hinweg.
     *
     * Genau an den Uebergaengen ist sie gerissen: FB-023 erkennt die Dublette
     * und legt den Verweis ins DTO, FB-031 muss ihn speichern, FB-033 liest ihn.
     * Faellt eines der drei Glieder aus, laeuft jede Dublette als kaufbarer Lead
     * in den Marktplatz -- und ein Kaeufer bezahlt denselben Menschen zweimal.
     * Deshalb steht hier ein durchgehender Test und nicht drei Einzelteile.
     */
    public function test_a_second_submission_with_the_same_email_ends_up_invalid(): void
    {
        // Die Zeitfalle stoert hier nicht: geprueft wird die Dublette.
        config()->set('funnel.public.min_seconds_before_submit', 0);

        $funnel = $this->publishedFunnelWithEmail();

        $this->submitEmail($funnel, 'anna@example.com');

        $first = Lead::query()->withoutGlobalScopes()->sole();

        $this->assertNull($first->duplicate_of_lead_id, 'Die erste Anfrage hat keinen Vorgaenger.');
        $this->assertSame(LeadState::VERFUEGBAR, $first->lead_state);

        // Dieselbe Adresse, derselbe Funnel -- nur eine neue Sitzung.
        $this->submitEmail($funnel, 'Anna@Example.com');

        $second = Lead::query()->withoutGlobalScopes()->where('id', '!=', $first->id)->sole();

        // FB-023 hat gefunden, FB-031 hat gespeichert ...
        $this->assertSame($first->id, $second->duplicate_of_lead_id);

        // ... und FB-033 hat entschieden.
        $this->assertSame(LeadState::UNGUELTIG, $second->lead_state);
        $this->assertSame(LeadTransitionReason::DUPLICATE, $this->lastReason($second));

        // Der erste Lead bleibt kaufbar -- verworfen wird die Wiederholung.
        $this->assertSame(LeadState::VERFUEGBAR, $first->fresh()->lead_state);
    }

    private function publishedFunnelWithEmail(): Funnel
    {
        $funnel = Funnel::factory()->create(['name' => 'Pfotencheck']);

        $step = FunnelStep::factory()->create(['funnel_id' => $funnel->id, 'position' => 1]);
        FunnelQuestion::factory()->create([
            'step_id' => $step->id,
            'field_key' => 'email',
            'type' => QuestionType::EMAIL,
            'label' => 'E-Mail-Adresse',
            'position' => 1,
        ]);
        FunnelResult::factory()->forScoreRange(0, 0)->create(['funnel_id' => $funnel->id]);

        app(PublishFunnel::class)->handle($funnel);

        return $funnel->refresh();
    }

    private function submitEmail(Funnel $funnel, string $email): void
    {
        Livewire::test(FunnelRunner::class, ['token' => $funnel->public_token])
            ->set('answers.email', $email)
            ->call('submitStep')
            ->call('continueAfterResult');
    }

    public function test_the_check_runs_by_itself_after_a_lead_was_created(): void
    {
        $lead = $this->newLead();

        LeadCreated::dispatch($lead);

        $this->assertSame(LeadState::VERFUEGBAR, $lead->fresh()->lead_state);

        // Wiederholbar: ein zweiter Anlauf der Warteschlange aendert nichts.
        LeadCreated::dispatch($lead->fresh());

        $this->assertSame(1, LeadStateLog::query()->where('lead_id', $lead->id)->count());
    }
}
