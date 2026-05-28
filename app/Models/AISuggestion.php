<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AISuggestion extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'ai_suggestions';

    public const TYPE_NO_ACTIVITY = 'no_activity';

    public const TYPE_PROPOSAL_SENT_NO_FOLLOWUP = 'proposal_sent_no_followup';

    public const TYPE_CLOSING_DATE_NEAR = 'closing_date_near';

    public const TYPE_HIGH_VALUE_STALLED = 'high_value_stalled';

    public const TYPE_CLIENT_REQUESTED_INFO = 'client_requested_info';

    public const TYPE_NEW_LEAD_NEEDS_FIRST_CONTACT = 'new_lead_needs_first_contact';

    public const TYPES = [
        self::TYPE_NO_ACTIVITY,
        self::TYPE_PROPOSAL_SENT_NO_FOLLOWUP,
        self::TYPE_CLOSING_DATE_NEAR,
        self::TYPE_HIGH_VALUE_STALLED,
        self::TYPE_CLIENT_REQUESTED_INFO,
        self::TYPE_NEW_LEAD_NEEDS_FIRST_CONTACT,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_POSTPONED = 'postponed';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_IGNORED = 'ignored';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACCEPTED,
        self::STATUS_POSTPONED,
        self::STATUS_ARCHIVED,
        self::STATUS_IGNORED,
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
        'user_id',
        'deal_id',
        'person_id',
        'entity_id',
        'calendar_event_id',
        'type',
        'title',
        'reason',
        'suggested_action',
        'suggested_due_at',
        'priority',
        'status',
        'source',
        'score',
        'metadata',
        'accepted_at',
        'accepted_by',
        'postponed_until',
        'archived_at',
        'archived_by',
        'ignored_at',
        'ignored_by',
        'converted_calendar_event_id',
    ];

    protected function casts(): array
    {
        return [
            'suggested_due_at' => 'datetime',
            'metadata' => 'array',
            'accepted_at' => 'datetime',
            'postponed_until' => 'datetime',
            'archived_at' => 'datetime',
            'ignored_at' => 'datetime',
            'score' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }

    public function convertedCalendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'converted_calendar_event_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function ignoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ignored_by');
    }
}
