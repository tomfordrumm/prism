<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::creating(function (Model $model): void {
            $tenantId = app(TenantManager::class)->currentTenantId();
            $hasTenantOnModel = (bool) $model->getAttribute('tenant_id');

            if ($tenantId) {
                $model->setAttribute('tenant_id', $hasTenantOnModel ? $model->getAttribute('tenant_id') : $tenantId);

                return;
            }

            if ($hasTenantOnModel) {
                return;
            }

            throw new RuntimeException('Cannot create tenant scoped model without current tenant.');
        });

        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantManager = app(TenantManager::class);
            $tenantId = $tenantManager->currentTenantId();

            if ($tenantId) {
                $builder->where($builder->qualifyColumn('tenant_id'), $tenantId);

                return;
            }

            logger()->warning('BelongsToTenant: no current tenant, query forced empty', [
                'model' => static::class,
                'url' => request()->fullUrl(),
            ]);

            $builder->whereRaw('1 = 0');
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant(Builder $builder, Tenant|int $tenant): Builder
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $builder
            ->withoutGlobalScope('tenant')
            ->where($builder->qualifyColumn('tenant_id'), $tenantId);
    }
}
