<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tenants\StoreTenantRequest;
use App\Models\Tenant;
use App\Services\Entitlements\Contracts\UsageMeterInterface;
use App\Support\TenantManager;
use Illuminate\Http\RedirectResponse;

class TenantController extends Controller
{
    public function __construct(
        private UsageMeterInterface $usageMeter
    ) {}

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $tenant = Tenant::create([
            'name' => $request->string('name'),
        ]);

        $request->user()->tenants()->attach($tenant->id, ['role' => 'owner']);
        $this->usageMeter->meter(
            tenantId: $tenant->id,
            meter: 'active_members',
            quantity: 1,
            context: [
                'user_id' => $request->user()->id,
                'actor_user_id' => $request->user()->id,
                'source' => 'tenant_created',
            ],
        );

        $request->session()->put('tenant_id', $tenant->id);
        app(TenantManager::class)->setCurrentTenant($tenant);

        return redirect()->intended(route('projects.index'));
    }
}
