<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRun extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_SUCCESS,
        self::STATUS_SKIPPED,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'tenant_id',
        'automation_rule_id',
        'deal_id',
        'calendar_event_id',
        'status',
        'result',
        'metadata',
        'ran_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'ran_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }
}
