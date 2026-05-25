<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\LogsActivity;
use Database\Factories\EntityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Entity extends Model
{
    /** @use HasFactory<EntityFactory> */
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_LEAD = 'lead';

    public const STATUS_CLIENT = 'client';

    public const STATUS_PROSPECT = 'prospect';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_LEAD,
        self::STATUS_CLIENT,
        self::STATUS_PROSPECT,
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'vat',
        'email',
        'phone',
        'address',
        'status',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
