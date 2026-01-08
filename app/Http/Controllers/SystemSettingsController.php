<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\UpdateSystemSettingsRequest;
use App\Models\ProviderCredential;
use App\Models\Tenant;
use App\Services\Llm\ModelCatalog;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    public function edit(ModelCatalog $modelCatalog): Response
    {
        $tenant = Tenant::query()->findOrFail(currentTenantId());
        $providerCredentials = ProviderCredential::query()
            ->where('tenant_id', currentTenantId())
            ->orderBy('name')
            ->get(['id', 'name', 'provider', 'encrypted_api_key', 'metadata']);

        return Inertia::render('settings/System', [
            'settings' => [
                'improvement_provider_credential_id' => $tenant->improvement_provider_credential_id,
                'improvement_model_name' => $tenant->improvement_model_name,
            ],
            'providerCredentials' => $providerCredentials
                ->map(fn (ProviderCredential $credential) => [
                    'value' => $credential->id,
                    'label' => sprintf('%s (%s)', $credential->name, $credential->provider),
                    'provider' => $credential->provider,
                ])
                ->all(),
            'providerCredentialModels' => $providerCredentials
                ->mapWithKeys(fn (ProviderCredential $credential) => [
                    $credential->id => $modelCatalog->getModelsFor($credential),
                ])
                ->all(),
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $tenant = Tenant::query()->findOrFail(currentTenantId());

        $providerCredentialId = $request->integer('improvement_provider_credential_id');
        $modelName = $request->input('improvement_model_name');

        if (! $providerCredentialId) {
            $modelName = null;
        }

        $tenant->update([
            'improvement_provider_credential_id' => $providerCredentialId ?: null,
            'improvement_model_name' => $modelName ?: null,
        ]);

        return redirect()
            ->route('settings.system.edit')
            ->with('status', 'System settings updated.');
    }
}
