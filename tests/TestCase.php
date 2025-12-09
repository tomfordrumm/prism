<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\CreatesApplication;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected bool $skipTenantBootstrap = false;

    public function actingAs($user, $driver = null)
    {
        if (! $this->skipTenantBootstrap) {
            $this->ensureTenant($user);
        }

        return parent::actingAs($user, $driver);
    }

    public function withoutTenantBootstrap(): static
    {
        $this->skipTenantBootstrap = true;

        return $this;
    }

    protected function ensureTenant(User $user): Tenant
    {
        /** @var Tenant|null $tenant */
        $tenant = $user->tenants()->first();

        if (! $tenant) {
            $tenant = Tenant::create(['name' => 'Test Tenant']);
            $user->tenants()->attach($tenant->id, ['role' => 'owner']);
        }

        $this->app['session.store']->start();
        $this->app['session.store']->put('tenant_id', $tenant->id);
        $this->app->make(TenantManager::class)->setCurrentTenant($tenant);

        return $tenant;
    }
}
