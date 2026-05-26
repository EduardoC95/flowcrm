<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\LogsActivity;
use Database\Factories\CalendarEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    /** @use HasFactory<CalendarEventFactory> */
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    public const TYPE_TASK = 'task';

    public const TYPE_CALL = 'call';

    public const TYPE_MEETING = 'meeting';

    public const TYPE_NOTE = 'note';

    public const TYPE_REMINDER = 'reminder';

    public const TYPES = [
        self::TYPE_TASK,
        self::TYPE_CALL,
        self::TYPE_MEETING,
        self::TYPE_NOTE,
        self::TYPE_REMINDER,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    protected $fillable = [
        'tenant_id',
        'eventable_type',
        'eventable_id',
        'entity_id',
        'person_id',
        'deal_id',
        'title',
        'description',
        'type',
        'start_at',
        'end_at',
        'starts_at',
        'ends_at',
        'location',
        'owner_id',
        'priority',
        'status',
        'reminder_at',
        'reminder_sent_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reminder_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function eventable(): MorphTo
    {
        return $this->morphTo();
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(CalendarEventAttendee::class);
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
