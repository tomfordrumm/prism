<?php

namespace App\Services\Entitlements;

final class EntitlementDecision
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason = null,
        public readonly array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function allow(array $meta = []): self
    {
        return new self(allowed: true, meta: $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function deny(string $reason, array $meta = []): self
    {
        return new self(allowed: false, reason: $reason, meta: $meta);
    }
}
