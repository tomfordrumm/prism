<?php

namespace App\Services\ProviderCredentials;

use App\Models\ProviderCredential;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class ProviderCredentialViewService
{
    public function indexData(): array
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

        return [
            'credentials' => $credentials,
            'providers' => $this->providerOptions(),
        ];
    }

    public function createData(): array
    {
        return [
            'providers' => $this->providerOptions(),
        ];
    }

    public function editData(ProviderCredential $credential): array
    {
        return [
            'credential' => [
                'id' => $credential->id,
                'name' => $credential->name,
                'provider' => $credential->provider,
                'metadata' => $credential->metadata,
                'masked_api_key' => $this->maskApiKey($credential->encrypted_api_key),
            ],
            'providers' => $this->providerOptions(),
        ];
    }

    public function maskApiKey(string $encryptedApiKey): string
    {
        try {
            $apiKey = Crypt::decryptString($encryptedApiKey);
        } catch (DecryptException) {
            return '****';
        }

        $suffix = substr($apiKey, -4);

        return '****'.$suffix;
    }

    public function providerOptions(): array
    {
        return [
            ['value' => 'openai', 'label' => 'OpenAI'],
            ['value' => 'anthropic', 'label' => 'Anthropic'],
            ['value' => 'google', 'label' => 'Google'],
        ];
    }
}
