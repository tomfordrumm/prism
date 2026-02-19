<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Routing\Contracts\HomeRouteHandlerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Tests\TestCase;

class HomeRouteDelegationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_route_uses_core_fallback_for_guests(): void
    {
        $response = $this->get(route('home'));

        $response->assertRedirect(route('login'));
    }

    public function test_home_route_uses_core_fallback_for_authenticated_users(): void
    {
        $user = User::factory()->create();
        $tenant = \App\Models\Tenant::create(['name' => 'Acme']);
        $user->tenants()->attach($tenant->id, ['role' => 'owner']);
        $this->actingAs($user);

        $response = $this->get(route('home'));

        $response->assertRedirect(route('projects.index'));
    }

    public function test_home_route_can_be_overridden_by_cloud_package_binding(): void
    {
        $this->app->bind(HomeRouteHandlerInterface::class, CloudHomeRouteHandlerFake::class);

        $response = $this->get(route('home'));

        $response->assertRedirect('/cloud-landing');
    }
}

final class CloudHomeRouteHandlerFake implements HomeRouteHandlerInterface
{
    public function handle(): RedirectResponse
    {
        return redirect('/cloud-landing');
    }
}
