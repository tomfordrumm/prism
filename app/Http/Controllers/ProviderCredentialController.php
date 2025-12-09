<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProviderCredentials\StoreProviderCredentialRequest;
use App\Http\Requests\ProviderCredentials\UpdateProviderCredentialRequest;
use App\Models\ProviderCredential;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

class ProviderCredentialController extends Controller
{
    public function index(): Response
    {
        $credentials = ProviderCredential::query()
            ->latest()
            ->get()
            ->map(fn (ProviderCredential $credential) => [
                'id' => $credential->id,
                'name' => $credential->name,
                'provider' => $credential->provider,
                'masked_api_key' => $this->maskApiKey($credential->encrypted_api_key),
                'created_at' => $credential->created_at,
            ]);

        return Inertia::render('providers/credentials/Index', [
            'credentials' => $credentials,
            'providers' => $this->providerOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('providers/credentials/Create', [
            'providers' => $this->providerOptions(),
        ]);
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
        return Inertia::render('providers/credentials/Edit', [
            'credential' => [
                'id' => $providerCredential->id,
                'name' => $providerCredential->name,
                'provider' => $providerCredential->provider,
                'metadata' => $providerCredential->metadata,
                'masked_api_key' => $this->maskApiKey($providerCredential->encrypted_api_key),
            ],
            'providers' => $this->providerOptions(),
        ]);
    }

    public function update(
        UpdateProviderCredentialRequest $request,
        ProviderCredential $providerCredential
    ): RedirectResponse {
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
        $providerCredential->delete();

        return redirect()->route('provider-credentials.index');
    }

    private function maskApiKey(string $encryptedApiKey): string
    {
        try {
            $apiKey = Crypt::decryptString($encryptedApiKey);
        } catch (DecryptException) {
            return '****';
        }

        $suffix = substr($apiKey, -4);

        return '****'.$suffix;
    }

    private function providerOptions(): array
    {
        return [
            ['value' => 'openai', 'label' => 'OpenAI'],
            ['value' => 'anthropic', 'label' => 'Anthropic'],
            ['value' => 'google', 'label' => 'Google'],
        ];
    }
}
