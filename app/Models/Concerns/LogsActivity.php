<?php

namespace App\Models\Concerns;

use App\Services\ActivityLogger;

trait LogsActivity
{
    protected static function bootLogsActivity(): void
    {
        static::created(fn ($model) => app(ActivityLogger::class)->forModel($model, 'created'));
        static::updated(fn ($model) => app(ActivityLogger::class)->forModel($model, 'updated'));
        static::deleted(fn ($model) => app(ActivityLogger::class)->forModel($model, 'deleted'));
    }
}
