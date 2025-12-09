<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tenants\StoreTenantRequest;
use App\Models\Tenant;
use App\Support\TenantManager;
use Illuminate\Http\RedirectResponse;

class TenantController extends Controller
{
    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $tenant = Tenant::create([
            'name' => $request->string('name'),
        ]);

        $request->user()->tenants()->attach($tenant->id, ['role' => 'owner']);

        $request->session()->put('tenant_id', $tenant->id);
        app(TenantManager::class)->setCurrentTenant($tenant);

        return redirect()->intended(route('projects.index'));
    }
}
