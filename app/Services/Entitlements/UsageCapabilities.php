<?php

namespace App\Services\Entitlements;

final class UsageCapabilities
{
    /**
     * @param  array<string, bool>  $meters
     */
    public function __construct(
        public readonly array $meters = [],
    ) {}

    public function supports(string $meter): bool
    {
        return $this->meters[$meter] ?? false;
    }

    /**
     * @return array<string, bool>
     */
    public function all(): array
    {
        return $this->meters;
    }
}
