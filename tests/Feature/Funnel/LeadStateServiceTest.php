<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Exceptions\IllegalLeadTransition;
use App\Exceptions\LeadStateLogIsImmutableException;
use App\Models\Lead;
use App\Models\LeadStateLog;
use App\Services\LeadStateService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\FeatureTest;

/**
 * FB-030: Die Zustandsmaschine des Leads.
 *
 * Geprueft wird ausschliesslich, was teuer waere, wenn es falsch ist: welche
 * Zustandswechsel moeglich sind, dass das Protokoll unveraenderlich ist und
 * dass ein festgeschriebener Preis festgeschrieben bleibt. Schema, Casts und
 * Factories pruefen sich beim ersten Zugriff selbst und bekommen keine eigenen
 * Tests.
 */
class LeadStateServiceTest extends FeatureTest
{
    private function service(): LeadStateService
    {
        return app(LeadStateService::class);
    }

    private function leadInState(LeadState $state): Lead
    {
        return Lead::factory()->inState($state)->create([
            'tenant_id' => $this->createTenant()->id,
        ]);
    }

    /**
     * Alle zwoelf erlaubten Uebergaenge aus LeadTransitions::TABLE.
     *
     * @return array<string, array{LeadState, LeadState}>
     */
    public static function allowedTransitionProvider(): array
    {
        return [
            'neu -> verfuegbar' => [LeadState::NEU, LeadState::VERFUEGBAR],
            'neu -> ungueltig' => [LeadState::NEU, LeadState::UNGUELTIG],
            'neu -> abgelaufen' => [LeadState::NEU, LeadState::ABGELAUFEN],
            'verfuegbar -> reserviert' => [LeadState::VERFUEGBAR, LeadState::RESERVIERT],
            'verfuegbar -> ungueltig' => [LeadState::VERFUEGBAR, LeadState::UNGUELTIG],
            'verfuegbar -> abgelaufen' => [LeadState::VERFUEGBAR, LeadState::ABGELAUFEN],
            'reserviert -> verfuegbar' => [LeadState::RESERVIERT, LeadState::VERFUEGBAR],
            'reserviert -> verkauft' => [LeadState::RESERVIERT, LeadState::VERKAUFT],
            'reserviert -> ungueltig' => [LeadState::RESERVIERT, LeadState::UNGUELTIG],
            'verkauft -> erreicht' => [LeadState::VERKAUFT, LeadState::ERREICHT],
            'verkauft -> unerreichbar' => [LeadState::VERKAUFT, LeadState::UNERREICHBAR],
            'verkauft -> ungueltig' => [LeadState::VERKAUFT, LeadState::UNGUELTIG],
        ];
    }

    /**
     * Verbotene Uebergaenge: uebersprungene Zwischenzustaende, Rueckwege,
     * Wechsel aus Endzustaenden heraus und der Wechsel eines Zustands auf sich
     * selbst -- der ist ausdruecklich keine leere Operation, sondern ein Fehler.
     *
     * @return array<string, array{LeadState, LeadState}>
     */
    public static function forbiddenTransitionProvider(): array
    {
        return [
            'neu -> reserviert (Marktplatz uebersprungen)' => [LeadState::NEU, LeadState::RESERVIERT],
            'neu -> verkauft (Kauf ohne Freigabe)' => [LeadState::NEU, LeadState::VERKAUFT],
            'neu -> erreicht (Abrechnung ohne Verkauf)' => [LeadState::NEU, LeadState::ERREICHT],
            'neu -> neu (kein Wechsel)' => [LeadState::NEU, LeadState::NEU],
            'verfuegbar -> verkauft (ohne Reservierung)' => [LeadState::VERFUEGBAR, LeadState::VERKAUFT],
            'verfuegbar -> neu (Rueckweg)' => [LeadState::VERFUEGBAR, LeadState::NEU],
            'reserviert -> erreicht (ohne Kauf)' => [LeadState::RESERVIERT, LeadState::ERREICHT],
            'reserviert -> abgelaufen' => [LeadState::RESERVIERT, LeadState::ABGELAUFEN],
            'verkauft -> verfuegbar (Wiederverkauf)' => [LeadState::VERKAUFT, LeadState::VERFUEGBAR],
            'erreicht -> unerreichbar (Endzustand)' => [LeadState::ERREICHT, LeadState::UNERREICHBAR],
            'ungueltig -> verfuegbar (Endzustand)' => [LeadState::UNGUELTIG, LeadState::VERFUEGBAR],
            'abgelaufen -> verkauft (Endzustand)' => [LeadState::ABGELAUFEN, LeadState::VERKAUFT],
        ];
    }

    #[DataProvider('allowedTransitionProvider')]
    public function test_allowed_transition_writes_state_and_log_entry(LeadState $from, LeadState $to): void
    {
        $lead = $this->leadInState($from);
        $actor = $this->createUser();

        $this->service()->transition($lead, $to, LeadTransitionReason::MANUAL_OVERRIDE, $actor, ['note' => 'Test']);

        $this->assertSame($to, $lead->fresh()->lead_state);

        $entry = LeadStateLog::query()->where('lead_id', $lead->id)->sole();
        $this->assertSame($from, $entry->from_state);
        $this->assertSame($to, $entry->to_state);
        $this->assertSame(LeadTransitionReason::MANUAL_OVERRIDE, $entry->reason);
        $this->assertSame($actor->id, $entry->actor_id);
        $this->assertSame(['note' => 'Test'], $entry->meta);
    }

    #[DataProvider('forbiddenTransitionProvider')]
    public function test_forbidden_transition_throws_and_changes_nothing(LeadState $from, LeadState $to): void
    {
        $lead = $this->leadInState($from);

        try {
            $this->service()->transition($lead, $to, LeadTransitionReason::MANUAL_OVERRIDE);
            $this->fail("Der Uebergang {$from->value} -> {$to->value} haette abgewiesen werden muessen.");
        } catch (IllegalLeadTransition $exception) {
            $this->assertSame($from, $exception->from);
            $this->assertSame($to, $exception->to);
        }

        $this->assertSame($from, $lead->fresh()->lead_state);
        $this->assertSame(0, LeadStateLog::query()->where('lead_id', $lead->id)->count());
    }

    public function test_state_log_entries_can_neither_be_changed_nor_deleted(): void
    {
        $lead = $this->leadInState(LeadState::NEU);
        $this->service()->transition($lead, LeadState::VERFUEGBAR, LeadTransitionReason::SCREENING_PASSED);

        $entry = LeadStateLog::query()->where('lead_id', $lead->id)->sole();

        $attempts = [
            'update' => fn () => $entry->update(['reason' => LeadTransitionReason::MANUAL_OVERRIDE]),
            'save nach Aenderung' => function () use ($entry): void {
                $entry->to_state = LeadState::VERKAUFT;
                $entry->save();
            },
            'delete' => fn () => $entry->delete(),
            // Massenzugriffe loesen keine Model-Events aus und werden deshalb
            // zusaetzlich im Query-Builder abgefangen.
            'Massen-Update' => fn () => LeadStateLog::query()->update(['reason' => LeadTransitionReason::SPAM->value]),
            'Massen-Delete' => fn () => LeadStateLog::query()->delete(),
            'truncate' => fn () => LeadStateLog::query()->truncate(),
        ];

        foreach ($attempts as $label => $attempt) {
            try {
                $attempt();
                $this->fail("Der Zugriff \"{$label}\" haette abgewiesen werden muessen.");
            } catch (LeadStateLogIsImmutableException) {
                // erwartet
            }
        }

        $stored = DB::table('lead_state_log')->where('id', $entry->id)->first();
        $this->assertNotNull($stored, 'Der Eintrag muss unveraendert vorhanden sein.');
        $this->assertSame(LeadState::VERFUEGBAR->value, $stored->to_state);
        $this->assertSame(LeadTransitionReason::SCREENING_PASSED->value, $stored->reason);
    }

    public function test_settled_price_is_written_once_and_never_changed(): void
    {
        config()->set('funnel.lead.default_price', 42.5);

        $lead = $this->leadInState(LeadState::VERKAUFT);
        $this->assertNull($lead->settled_price);

        $this->service()->transition($lead, LeadState::ERREICHT, LeadTransitionReason::CALL_ANSWERED);

        $lead->refresh();
        $this->assertSame('42.50', $lead->settled_price, 'Der Preis kommt aus config(funnel.lead.default_price).');
        $this->assertNotNull($lead->settled_at);

        $settledAt = $lead->settled_at;

        // Ein zweites Festschreiben wuerde bei geaendertem Konfigurationswert auffallen.
        config()->set('funnel.lead.default_price', 99.00);

        try {
            $this->service()->transition($lead, LeadState::UNERREICHBAR, LeadTransitionReason::CALL_ATTEMPTS_EXHAUSTED);
            $this->fail('Aus einem Endzustand darf kein weiterer Wechsel moeglich sein.');
        } catch (IllegalLeadTransition) {
            // erwartet
        }

        $lead->refresh();
        $this->assertSame(LeadState::ERREICHT, $lead->lead_state);
        $this->assertSame('42.50', $lead->settled_price);
        $this->assertEquals($settledAt, $lead->settled_at);
        $this->assertSame(1, LeadStateLog::query()->where('lead_id', $lead->id)->count());
    }
}
