<?php

namespace App\Jobs;

use App\Actions\Runs\RunChainAction;
use App\Models\Run;
use App\Models\Tenant;
use App\Support\TenantManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteRunJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $runId)
    {
    }

    public function handle(RunChainAction $action, TenantManager $tenantManager): void
    {
        $run = Run::withoutGlobalScopes()->find($this->runId);

        if (! $run) {
            return;
        }

        $tenant = Tenant::find($run->tenant_id);
        if (! $tenant) {
            $run->update([
                'status' => 'failed',
                'error_message' => 'Tenant not found for run '.$run->id,
            ]);

            return;
        }

        $tenantManager->setCurrentTenant($tenant);

        $action->execute($run);
    }
}
