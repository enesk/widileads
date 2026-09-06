<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use Tests\Feature\FeatureTest;

/**
 * FB-005: Das Audit-Log ist im Admin-Panel nur lesbar.
 */
class AuditLogResourceTest extends FeatureTest
{
    public function test_list(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        AuditLog::factory()->create();

        $response = $this->get(AuditLogResource::getUrl('index', [], true, 'admin'))->assertSuccessful();

        $response->assertStatus(200);
    }

    public function test_resource_offers_no_create_edit_or_delete(): void
    {
        $entry = AuditLog::factory()->create();

        $this->assertFalse(AuditLogResource::canCreate());
        $this->assertFalse(AuditLogResource::canEdit($entry));
        $this->assertFalse(AuditLogResource::canDelete($entry));
        $this->assertFalse(AuditLogResource::canDeleteAny());
        $this->assertSame(['index'], array_keys(AuditLogResource::getPages()));
    }
}
