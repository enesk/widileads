<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\AuditAction;
use App\Constants\LeadExportStatus;
use App\Constants\LeadState;
use App\Constants\TenantType;
use App\Jobs\BuildLeadExport;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\LeadExport;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LeadExportBuilder;
use App\Services\LeadPurchaseLookup;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Feature\FeatureTest;

/**
 * FB-073: Export der Leads.
 *
 * Ein Export ist der Vorgang, bei dem die meisten Daten auf einmal das Haus
 * verlassen. Geprueft wird deshalb, dass er die Maskierung nicht umgeht und
 * dass die fertige Datei nicht in fremde Haende faellt.
 */
class LeadExportTest extends FeatureTest
{
    private const EMAIL = 'mara.lindqvist@example.com';

    private const PHONE = '+493012345678';

    private function operator(): Tenant
    {
        return Tenant::factory()->create(['type' => TenantType::OPERATOR]);
    }

    private function lead(Tenant $tenant): Lead
    {
        $lead = Lead::factory()->inState(LeadState::VERFUEGBAR)->create([
            'tenant_id' => $tenant->id,
            'postal_code' => '76131',
            'email_normalized' => self::EMAIL,
            'phone_e164' => self::PHONE,
        ]);

        foreach (['vorname' => 'Mara', 'nachname' => 'Lindqvist', 'tierart' => 'hund', 'email' => self::EMAIL] as $key => $value) {
            LeadAnswer::query()->create(['lead_id' => $lead->id, 'field_key' => $key, 'value' => $value]);
        }

        return $lead->fresh();
    }

    /**
     * @param  list<string>  $columns
     */
    private function runExport(Tenant $tenant, User $requester, array $columns): LeadExport
    {
        $export = LeadExport::query()->create([
            'tenant_id' => $tenant->id,
            'requested_by' => $requester->id,
            'columns' => $columns,
            'filters' => [],
        ]);

        (new BuildLeadExport((int) $export->id))->handle(app(LeadExportBuilder::class));

        return $export->fresh();
    }

    private function contentOf(LeadExport $export): string
    {
        return (string) Storage::disk((string) $export->disk)->get((string) $export->path);
    }

    public function test_the_exported_file_carries_no_contact_data_the_requester_may_not_see(): void
    {
        Storage::fake('local');

        $operator = $this->operator();
        $this->lead($operator);

        $columns = ['id', 'name', 'email', 'phone', 'postal_code', 'answers'];

        // Der Betreiber hat die Daten selbst erhoben -- er bekommt Klartext.
        $forOperator = $this->runExport($operator, $this->createUser($operator), $columns);

        $this->assertSame(LeadExportStatus::READY, $forOperator->status);
        $this->assertSame(1, $forOperator->row_count);

        $csv = $this->contentOf($forOperator);

        $this->assertStringContainsString(self::EMAIL, $csv);
        $this->assertStringContainsString(self::PHONE, $csv);
        $this->assertStringContainsString('tierart: hund', $csv);

        // Ein Betrachter ohne Anspruch bekommt dieselbe Datei verdeckt --
        // derselbe Ausschnitt sieht fuer verschiedene Leute verschieden aus.
        $outsider = $this->createUser(Tenant::factory()->create(['type' => TenantType::BUYER]));

        $this->app->bind(LeadPurchaseLookup::class, fn () => new class implements LeadPurchaseLookup
        {
            public function hasPurchased(Tenant $tenant, Lead $lead): bool
            {
                return false;
            }
        });

        $forOutsider = $this->runExport($operator, $outsider, $columns);
        $maskedCsv = $this->contentOf($forOutsider);

        $this->assertStringNotContainsString(self::EMAIL, $maskedCsv);
        $this->assertStringNotContainsString(self::PHONE, $maskedCsv);
        $this->assertStringNotContainsString('3012345678', $maskedCsv);
        $this->assertStringNotContainsString('76131', $maskedCsv);
        $this->assertStringContainsString('m…@example.com', $maskedCsv);

        // Die Rohantwort auf ein Kontaktfeld steht nie in der Antwortspalte --
        // sonst liefe sie an der Maskierung vorbei.
        $this->assertStringNotContainsString('email: '.self::EMAIL, $maskedCsv);

        // Ein Export ist eine Offenlegung und wird protokolliert (FB-005).
        $this->assertSame(2, AuditLog::query()->where('action', AuditAction::DATA_EXPORTED)->count());
    }

    public function test_a_finished_export_is_not_downloadable_by_another_workspace(): void
    {
        Storage::fake('local');

        $operator = $this->operator();
        $this->lead($operator);

        $export = $this->runExport($operator, $this->createUser($operator), ['id', 'email']);

        $link = URL::temporarySignedRoute(
            'lead-export.download',
            now()->addMinutes(60),
            ['export' => $export->uuid],
        );

        $this->withExceptionHandling();

        // Ohne Signatur gar nicht.
        $this->actingAs($this->createUser($operator))
            ->get(route('lead-export.download', ['export' => $export->uuid], absolute: false))
            ->assertForbidden();

        // Mit Signatur, aber aus einem fremden Workspace: ebenfalls nicht --
        // ein weitergereichter Link gibt keine Kontaktdaten heraus.
        $this->actingAs($this->createUser($this->operator()))->get($link)->assertForbidden();

        // Und fuer den eigenen Workspace mit Signatur: ja.
        $this->actingAs($this->createUser($operator))->get($link)->assertOk();
    }
}
