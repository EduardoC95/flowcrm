<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DealFollowUp extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_REPLIED = 'replied';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_CANCELLED,
        self::STATUS_COMPLETED,
        self::STATUS_PAUSED,
        self::STATUS_REPLIED,
    ];

    protected $fillable = [
        'tenant_id',
        'deal_id',
        'status',
        'next_send_at',
        'last_sent_at',
        'sent_count',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'replied_at',
        'replied_by',
    ];

    protected function casts(): array
    {
        return [
            'next_send_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'sent_count' => 'integer',
            'cancelled_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(DealFollowUpEmail::class);
    }
}
