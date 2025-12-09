<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProviderCredentials\StoreProviderCredentialRequest;
use App\Http\Requests\ProviderCredentials\UpdateProviderCredentialRequest;
use App\Models\ProviderCredential;
use App\Services\ProviderCredentials\ProviderCredentialViewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

class ProviderCredentialController extends Controller
{
    public function __construct(
        private ProviderCredentialViewService $viewService
    ) {
    }

    public function index(): Response
    {
        $viewData = $this->viewService->indexData();

        return Inertia::render('providers/credentials/Index', $viewData);
    }

    public function create(): Response
    {
        $viewData = $this->viewService->createData();

        return Inertia::render('providers/credentials/Create', $viewData);
    }

    public function store(StoreProviderCredentialRequest $request): RedirectResponse
    {
        ProviderCredential::create([
            'tenant_id' => currentTenantId(),
            'provider' => $request->string('provider'),
            'name' => $request->string('name'),
            'encrypted_api_key' => Crypt::encryptString($request->string('api_key')),
            'metadata' => $request->input('metadata'),
        ]);

        return redirect()->route('provider-credentials.index');
    }

    public function edit(ProviderCredential $providerCredential): Response
    {
        $this->assertCredentialTenant($providerCredential);

        $viewData = $this->viewService->editData($providerCredential);

        return Inertia::render('providers/credentials/Edit', $viewData);
    }

    public function update(
        UpdateProviderCredentialRequest $request,
        ProviderCredential $providerCredential
    ): RedirectResponse {
        $this->assertCredentialTenant($providerCredential);

        $providerCredential->fill([
            'provider' => $request->string('provider'),
            'name' => $request->string('name'),
            'metadata' => $request->input('metadata'),
        ]);

        if ($request->filled('api_key')) {
            $providerCredential->encrypted_api_key = Crypt::encryptString($request->string('api_key'));
        }

        $providerCredential->save();

        return redirect()->route('provider-credentials.index');
    }

    public function destroy(ProviderCredential $providerCredential): RedirectResponse
    {
        $this->assertCredentialTenant($providerCredential);

        $providerCredential->delete();

        return redirect()->route('provider-credentials.index');
    }

    private function assertCredentialTenant(ProviderCredential $providerCredential): void
    {
        if ($providerCredential->tenant_id !== currentTenantId()) {
            abort(404);
        }
    }
}
