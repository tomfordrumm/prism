<?php

namespace App\Services\Entitlements;

final class QuotaDecision
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly ?int $limit = null,
        public readonly ?int $used = null,
        public readonly ?string $reason = null,
        public readonly array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function allowUnlimited(array $meta = []): self
    {
        return new self(allowed: true, meta: $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function allowWithinLimit(int $limit, int $used, array $meta = []): self
    {
        if ($used > $limit) {
            return self::deny(
                limit: $limit,
                used: $used,
                reason: 'quota_exceeded',
                meta: $meta,
            );
        }

        return new self(allowed: true, limit: $limit, used: $used, meta: $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function deny(int $limit, int $used, string $reason, array $meta = []): self
    {
        return new self(allowed: false, limit: $limit, used: $used, reason: $reason, meta: $meta);
    }

    public function remaining(): ?int
    {
        if ($this->limit === null || $this->used === null) {
            return null;
        }

        return max(0, $this->limit - $this->used);
    }
}
