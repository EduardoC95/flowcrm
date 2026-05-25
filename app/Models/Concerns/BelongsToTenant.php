<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::creating(function ($model) {
            $user = Auth::user();

            if (! $model->tenant_id && $user?->current_tenant_id) {
                $model->tenant_id = $user->current_tenant_id;
            }

            if ($user?->current_tenant_id && (int) $model->tenant_id !== (int) $user->current_tenant_id) {
                throw new AuthorizationException('Cannot create records for another tenant.');
            }
        });

        static::updating(function ($model) {
            $user = Auth::user();

            if ($user?->current_tenant_id && (int) $model->tenant_id !== (int) $user->current_tenant_id) {
                throw new AuthorizationException('Cannot update records for another tenant.');
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder) {
            $user = Auth::user();

            if ($user?->current_tenant_id) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $user->current_tenant_id);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
