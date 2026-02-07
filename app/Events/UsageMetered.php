<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class UsageMetered
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly string $meter,
        public readonly int $quantity,
        public readonly array $context = [],
        public readonly string $eventId = '',
        public readonly string $occurredAt = '',
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public static function create(
        int $tenantId,
        string $meter,
        int $quantity,
        array $context = []
    ): self {
        return new self(
            tenantId: $tenantId,
            meter: $meter,
            quantity: $quantity,
            context: $context,
            eventId: (string) Str::uuid(),
            occurredAt: now()->toIso8601String(),
        );
    }
}
