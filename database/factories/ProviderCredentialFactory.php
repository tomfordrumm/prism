<?php

namespace Database\Factories;

use App\Models\ProviderCredential;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderCredential>
 */
class ProviderCredentialFactory extends Factory
{
    protected $model = ProviderCredential::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'provider' => fake()->randomElement(['openai', 'anthropic', 'google']),
            'name' => fake()->words(2, true),
            'encrypted_api_key' => encrypt('test-api-key'),
            'metadata' => null,
        ];
    }
}
