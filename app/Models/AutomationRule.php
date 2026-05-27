<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutomationRule extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const TRIGGER_DEAL_INACTIVITY = 'deal_inactivity';

    public const ACTION_CREATE_CALENDAR_ACTIVITY = 'create_calendar_activity';

    public const TRIGGERS = [
        self::TRIGGER_DEAL_INACTIVITY,
    ];

    public const ACTIONS = [
        self::ACTION_CREATE_CALENDAR_ACTIVITY,
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'trigger_type',
        'inactivity_days',
        'action_type',
        'action_payload',
        'notify_owner',
        'active',
        'created_by',
        'paused_at',
        'paused_by',
    ];

    protected function casts(): array
    {
        return [
            'action_payload' => 'array',
            'notify_owner' => 'boolean',
            'active' => 'boolean',
            'paused_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pausedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paused_by');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }
}
