<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ActivityLogger
{
    public function __construct(private readonly ?Request $request = null) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(
        string $action,
        string $module,
        ?int $tenantId = null,
        ?Model $subject = null,
        ?string $description = null,
        array $properties = [],
    ): ActivityLog {
        $user = Auth::user();
        $request = $this->request;

        return ActivityLog::create([
            'tenant_id' => $tenantId ?? $user?->current_tenant_id,
            'user_id' => $user?->id,
            'action' => $action,
            'module' => $module,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    public function forModel(Model $model, string $action): ActivityLog
    {
        $module = Str::of(class_basename($model))->kebab()->plural()->toString();
        $tenantId = $model->getAttribute('tenant_id');

        return $this->log(
            $action,
            $module,
            $tenantId,
            $model,
            sprintf('%s %s.', class_basename($model), $action),
            [
                'changes' => $action === 'updated' ? $model->getChanges() : null,
            ],
        );
    }
}
