<?php

namespace Tests\Unit\Services\Entitlements;

use App\Events\UsageMetered;
use App\Services\Entitlements\Contracts\UsageCapabilityResolverInterface;
use App\Services\Entitlements\EventUsageMeter;
use App\Services\Entitlements\UsageCapabilities;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EventUsageMeterTest extends TestCase
{
    public function test_meter_emits_event_for_supported_meter(): void
    {
        Event::fake([UsageMetered::class]);

        $meter = new EventUsageMeter(new class implements UsageCapabilityResolverInterface
        {
            public function forTenant(int $tenantId): UsageCapabilities
            {
                return new UsageCapabilities([
                    'run_count' => true,
                ]);
            }
        });

        $meter->meter(
            tenantId: 10,
            meter: 'run_count',
            quantity: 2,
            context: ['run_id' => 123],
        );

        Event::assertDispatched(UsageMetered::class, function (UsageMetered $event): bool {
            return $event->tenantId === 10
                && $event->meter === 'run_count'
                && $event->quantity === 2
                && $event->context['run_id'] === 123
                && $event->eventId !== ''
                && $event->occurredAt !== '';
        });
    }

    public function test_meter_does_not_emit_event_when_meter_is_unsupported(): void
    {
        Event::fake([UsageMetered::class]);

        $meter = new EventUsageMeter(new class implements UsageCapabilityResolverInterface
        {
            public function forTenant(int $tenantId): UsageCapabilities
            {
                return new UsageCapabilities([
                    'run_count' => false,
                ]);
            }
        });

        $meter->meter(
            tenantId: 10,
            meter: 'run_count',
            quantity: 1,
        );

        Event::assertNotDispatched(UsageMetered::class);
    }

    public function test_meter_does_not_emit_event_for_invalid_tenant_or_zero_quantity(): void
    {
        Event::fake([UsageMetered::class]);

        $meter = new EventUsageMeter(new class implements UsageCapabilityResolverInterface
        {
            public function forTenant(int $tenantId): UsageCapabilities
            {
                return new UsageCapabilities([
                    'run_count' => true,
                ]);
            }
        });

        $meter->meter(tenantId: 0, meter: 'run_count', quantity: 1);
        $meter->meter(tenantId: 10, meter: 'run_count', quantity: 0);
        $meter->meter(tenantId: 10, meter: 'run_count', quantity: -5);

        Event::assertNotDispatched(UsageMetered::class);
    }
}
