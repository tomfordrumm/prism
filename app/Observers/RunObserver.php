<?php

namespace App\Observers;

use App\Models\Run;
use App\Services\Entitlements\Contracts\UsageMeterInterface;

class RunObserver
{
    public function __construct(
        private UsageMeterInterface $usageMeter
    ) {}

    public function created(Run $run): void
    {
        $this->usageMeter->meter(
            tenantId: $run->tenant_id,
            meter: 'run_count',
            quantity: 1,
            context: [
                'run_id' => $run->id,
                'project_id' => $run->project_id,
                'chain_id' => $run->chain_id,
                'dataset_id' => $run->dataset_id,
                'test_case_id' => $run->test_case_id,
                'source' => 'run_created',
            ],
        );
    }
}
