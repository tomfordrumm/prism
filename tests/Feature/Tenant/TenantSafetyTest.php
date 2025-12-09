<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_without_tenant_aborts_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $this->withoutTenantBootstrap()->actingAs($user)->withSession(['tenant_id' => null]);

        $this->get('/projects')->assertNotFound();
    }

    public function test_request_sets_first_tenant_when_available(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'Acme']);
        $user->tenants()->attach($tenant->id, ['role' => 'owner']);

        $this->actingAs($user);

        $this->get('/projects')->assertOk();
    }
}
