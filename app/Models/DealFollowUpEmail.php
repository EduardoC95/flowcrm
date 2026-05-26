<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealFollowUpEmail extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'deal_id',
        'deal_follow_up_id',
        'follow_up_template_id',
        'sent_by',
        'recipient_email',
        'subject',
        'body',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function followUp(): BelongsTo
    {
        return $this->belongsTo(DealFollowUp::class, 'deal_follow_up_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FollowUpTemplate::class, 'follow_up_template_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
