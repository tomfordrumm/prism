<?php

namespace App\Services\Entitlements\Contracts;

interface UsageMeterInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function meter(int $tenantId, string $meter, int $quantity, array $context = []): void;
}
