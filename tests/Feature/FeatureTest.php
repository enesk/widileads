<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\Testing\TestingDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class FeatureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seeds the shared fixtures once per process, immediately after the database is
     * migrated, so the seeded rows live outside of the per-test transaction.
     */
    protected string $seeder = TestingDatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureDefaultCurrency();
        $this->withoutExceptionHandling();
        $this->withoutVite();
    }

    protected function createUser(?Tenant $tenant = null, array $tenantPermissions = [], array $attributes = [])
    {
        $user = User::factory()->create($attributes);

        if ($tenant !== null) {
            $tenant->users()->attach($user);

            foreach ($tenantPermissions as $permission) {
                $user->tenants()->where('tenant_id', $tenant->id)->first()->pivot->givePermissionTo($permission);
            }
        }

        return $user;
    }

    protected function createTenant()
    {
        return Tenant::factory()->create();
    }

    protected function createAdminUser()
    {
        $user = User::factory()->create([
            'is_admin' => true,
        ]);

        $user->each(function ($user) {
            $user->assignRole('admin');
        });

        return $user;
    }

    protected function configureDefaultCurrency(): void
    {
        config()->set('app.default_currency', 'USD');
    }
}
