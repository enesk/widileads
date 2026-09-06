<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\CreateLeadFromSession;
use App\Actions\PublishFunnel;
use App\Constants\LeadState;
use App\Constants\TenantType;
use App\Dto\FunnelSubmissionData;
use App\Events\Lead\LeadCreated;
use App\Funnel\Runtime\SubmissionReceiver;
use App\Marketplace\MatchableLead;
use App\Models\Funnel;
use App\Models\FunnelVersion;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\PublicSession;
use App\Models\Tenant;
use App\Services\FunnelTemplateImporter;
use Illuminate\Support\Facades\Event;
use Tests\Feature\FeatureTest;

/**
 * FB-031: Der Eingang jedes Leads.
 *
 * Gearbeitet wird gegen den Pfotencheck aus FB-019 und eine echte Sitzung aus
 * FB-021 -- an einem kuenstlich kleinen Aufbau wuerde nicht auffallen, wenn
 * Antworten oder Herkunft unterwegs verloren gehen.
 */
class CreateLeadFromSessionTest extends FeatureTest
{
    /**
     * @var array<string, mixed>
     */
    private const ANSWERS = [
        'tierart' => 'hund',
        'alter' => 'ab_8',
        'rasse_groesse' => 'gross',
        'vorerkrankungen' => ['gelenke', 'herz_niere'],
        'verhalten' => 'sehr_aktiv',
        'versicherungsstatus' => 'keine',
        'vorname' => 'Mara',
        'nachname' => 'Lindqvist',
        'email' => 'mara.lindqvist@example.com',
        'telefon' => '+493012345678',
        'plz' => '76131',
        'einwilligung' => true,
    ];

    private function publishedFunnel(?float $leadPrice = null): FunnelVersion
    {
        $tenant = Tenant::factory()->create(['type' => TenantType::OPERATOR]);
        $funnel = app(FunnelTemplateImporter::class)->import('pfotencheck', $tenant);

        // Die Vorlage bringt 15,00 EUR mit; der Test setzt den Preis immer
        // ausdruecklich -- null heisst: der Funnel hat keinen eigenen Preis.
        $funnel->forceFill(['lead_price' => $leadPrice])->save();

        return app(PublishFunnel::class)->handle($funnel);
    }

    private function completedSession(FunnelVersion $version): PublicSession
    {
        return PublicSession::factory()->completed()->create([
            'funnel_version_id' => $version->id,
            'answers' => self::ANSWERS,
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'pfotencheck-herbst',
            'referrer' => 'https://www.tierarztportal.com/ratgeber',
            'embed_origin' => 'https://pfotencheck.tierarztportal.com',
            'ip_hash' => str_repeat('a', 64),
            'user_agent' => 'Mozilla/5.0',
        ]);
    }

    private function submission(FunnelVersion $version, PublicSession $session): FunnelSubmissionData
    {
        return new FunnelSubmissionData(
            publicToken: (string) $version->funnel->public_token,
            funnelVersionId: (int) $version->id,
            publicSessionId: (int) $session->id,
            answers: self::ANSWERS,
            score: 13,
            resultKey: '8-20',
        );
    }

    public function test_a_completed_session_becomes_a_complete_lead(): void
    {
        Event::fake([LeadCreated::class]);

        $version = $this->publishedFunnel();
        $session = $this->completedSession($version);

        $lead = app(CreateLeadFromSession::class)->handle($this->submission($version, $session));

        // Herkunft der Anfrage.
        $this->assertSame($version->funnel_id, $lead->funnel_id);
        $this->assertSame($version->id, $lead->funnel_version_id);
        $this->assertSame($session->id, $lead->public_session_id);
        $this->assertSame($version->funnel->tenant_id, $lead->tenant_id);

        // Bewertung und Ergebnis -- der Schluessel aus dem Snapshot, keine ID.
        $this->assertSame(13, $lead->score);
        $this->assertSame('8-20', $lead->result_key);

        // Zustand: neu, nicht bewertet. Das entscheidet FB-033.
        $this->assertSame(LeadState::NEU, $lead->lead_state);

        // Kontakt in normalisierter Form.
        $this->assertSame('+493012345678', $lead->phone_e164);
        $this->assertSame('mara.lindqvist@example.com', $lead->email_normalized);

        // Herkunft, von der Sitzung uebernommen (FB-022) -- nie eine Roh-IP.
        $this->assertSame('google', $lead->utm_source);
        $this->assertSame('pfotencheck-herbst', $lead->utm_campaign);
        $this->assertSame('https://pfotencheck.tierarztportal.com', $lead->embed_origin);
        $this->assertSame(str_repeat('a', 64), $lead->ip_hash);

        // Antworten eins zu eins, Mehrfachauswahl als Liste.
        $answers = $lead->answers()->pluck('value', 'field_key')->all();
        $this->assertSame(count(self::ANSWERS), count($answers));
        $this->assertSame('hund', $answers['tierart']);
        $this->assertSame(['gelenke', 'herz_niere'], $answers['vorerkrankungen']);
        $this->assertSame('76131', $answers['plz']);

        // Verlauf und Zeitstempel bleiben an der Sitzung -- eine Quelle.
        $this->assertNotNull($lead->publicSession->completed_at);

        // Uebergabe an den Marktplatz (FB-051): Postleitzahl im Klartext.
        $matchable = MatchableLead::fromLead($lead->fresh(['answers']));
        $this->assertSame($lead->funnel_id, $matchable->funnelId);
        $this->assertSame(13, $matchable->score);
        $this->assertSame('76131', $matchable->postalCode);
        $this->assertSame(['gelenke', 'herz_niere'], $matchable->answersFor('vorerkrankungen'));

        Event::assertDispatched(LeadCreated::class, fn (LeadCreated $event): bool => $event->lead->is($lead));
    }

    public function test_the_same_session_never_creates_a_second_lead(): void
    {
        $version = $this->publishedFunnel();
        $session = $this->completedSession($version);
        $submission = $this->submission($version, $session);

        $first = app(CreateLeadFromSession::class)->handle($submission);
        $second = app(CreateLeadFromSession::class)->handle($submission);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Lead::query()->withoutGlobalScopes()->count());
        $this->assertSame(
            count(self::ANSWERS),
            LeadAnswer::query()->count(),
            'Ein zweiter Aufruf darf auch die Antworten nicht verdoppeln.',
        );
    }

    public function test_price_at_creation_takes_the_funnel_price_and_otherwise_the_configured_one(): void
    {
        config()->set('funnel.lead.default_price', 15.00);

        $withOwnPrice = $this->publishedFunnel(leadPrice: 24.50);
        $lead = app(CreateLeadFromSession::class)->handle(
            $this->submission($withOwnPrice, $this->completedSession($withOwnPrice)),
        );

        $this->assertSame('24.50', $lead->price_at_creation);

        $withoutOwnPrice = $this->publishedFunnel();
        $this->assertNull(Funnel::query()->withoutGlobalScopes()->find($withoutOwnPrice->funnel_id)?->lead_price);

        $fallbackLead = app(CreateLeadFromSession::class)->handle(
            $this->submission($withoutOwnPrice, $this->completedSession($withoutOwnPrice)),
        );

        $this->assertSame('15.00', $fallbackLead->price_at_creation);
    }

    public function test_the_runtime_reaches_this_action_through_the_container(): void
    {
        $this->assertInstanceOf(CreateLeadFromSession::class, app(SubmissionReceiver::class));
    }
}
