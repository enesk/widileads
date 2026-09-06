<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\CreateLeadFromSession;
use App\Actions\PublishFunnel;
use App\Constants\LeadState;
use App\Constants\TenantType;
use App\Dto\FunnelSubmissionData;
use App\Models\Lead;
use App\Models\PublicSession;
use App\Models\Tenant;
use App\Services\FunnelTemplateImporter;
use App\Services\LeadAnonymizer;
use App\Services\LeadDataRequestService;
use Tests\Feature\FeatureTest;

/**
 * FB-031: Der Nachweis, dass die Vorarbeiten jetzt greifen.
 *
 * FB-037 (Anonymisierung) und FB-038 (DSGVO-Auskunft und Loeschersuchen) wurden
 * gebaut, bevor es Kontaktspalten und Antworten gab. Beide waren darauf
 * ausgelegt, mit dem Anlegen dieser Spalten von selbst scharf zu werden. Hier
 * steht der Beleg -- ohne ihn waere es eine unbelegte Behauptung.
 */
class LeadPersonalDataTest extends FeatureTest
{
    /**
     * @var array<string, mixed>
     */
    private const ANSWERS = [
        'tierart' => 'katze',
        'alter' => '4_bis_7',
        'rasse_groesse' => 'klein',
        'vorerkrankungen' => ['zaehne'],
        'verhalten' => 'normal',
        'versicherungsstatus' => 'keine',
        'vorname' => 'Jonas',
        'nachname' => 'Weber',
        'email' => 'jonas.weber@example.com',
        'telefon' => '+493087654321',
        'plz' => '10115',
        'einwilligung' => true,
    ];

    private function leadFromSubmission(): Lead
    {
        $tenant = Tenant::factory()->create(['type' => TenantType::OPERATOR]);
        $funnel = app(FunnelTemplateImporter::class)->import('pfotencheck', $tenant);
        $version = app(PublishFunnel::class)->handle($funnel);

        $session = PublicSession::factory()->completed()->create([
            'funnel_version_id' => $version->id,
            'answers' => self::ANSWERS,
        ]);

        return app(CreateLeadFromSession::class)->handle(new FunnelSubmissionData(
            publicToken: (string) $funnel->public_token,
            funnelVersionId: (int) $version->id,
            publicSessionId: (int) $session->id,
            answers: self::ANSWERS,
            score: 5,
            resultKey: '4-7',
        ));
    }

    public function test_anonymisation_now_clears_contact_columns_and_personal_answers(): void
    {
        $lead = $this->leadFromSubmission();

        $this->assertSame('jonas.weber@example.com', $lead->email_normalized);
        $this->assertSame('+493087654321', $lead->phone_e164);

        $this->assertTrue(app(LeadAnonymizer::class)->anonymize($lead));

        $lead->refresh();
        $this->assertNotNull($lead->anonymized_at);
        $this->assertNull($lead->email_normalized, 'FB-037 hatte email_normalized bereits vorgesehen.');
        $this->assertNull($lead->phone_e164);

        $answers = $lead->answers()->pluck('value', 'field_key')->all();

        // Personenbezogene Antworten sind leer -- die Zeilen bleiben, damit
        // weiterhin zaehlbar ist, welche Felder beantwortet wurden.
        foreach (['vorname', 'nachname', 'email', 'telefon', 'plz', 'einwilligung'] as $personalFieldKey) {
            $this->assertArrayHasKey($personalFieldKey, $answers);
            $this->assertNull($answers[$personalFieldKey], "Die Antwort auf {$personalFieldKey} traegt noch einen Personenbezug.");
        }

        // Fachliche Antworten bleiben: Sie sind niemandem mehr zuzuordnen und
        // tragen die Auswertung des Funnels.
        $this->assertSame('katze', $answers['tierart']);
        $this->assertSame(['zaehne'], $answers['vorerkrankungen']);

        // Und die Zaehl- und Preisdaten stehen weiterhin (FB-037).
        $this->assertSame(LeadState::NEU, $lead->lead_state);
        $this->assertSame('15.00', $lead->price_at_creation);
    }

    public function test_the_gdpr_request_now_finds_the_lead_by_email(): void
    {
        $lead = $this->leadFromSubmission();
        $admin = $this->createAdminUser();

        // FB-038 sucht ueber leads.email_normalized -- bis FB-031 gab es die
        // Spalte nicht und die Suche kam leer zurueck.
        $export = app(LeadDataRequestService::class)->exportForEmail('Jonas.Weber@example.com', $admin);

        $this->assertSame(1, $export['lead_count']);
        $this->assertSame($lead->id, $export['leads'][0]['lead']['id']);
        $this->assertNotSame([], $export['leads'][0]['answers'], 'Die Auskunft muss die Antworten enthalten.');

        $erased = app(LeadDataRequestService::class)->eraseForEmail('jonas.weber@example.com', $admin);

        $this->assertSame(1, $erased);
        $this->assertNotNull($lead->fresh()->anonymized_at);
    }
}
