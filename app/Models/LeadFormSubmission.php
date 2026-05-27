<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadFormSubmission extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'lead_form_id',
        'payload',
        'source_url',
        'ip_address',
        'user_agent',
        'created_person_id',
        'created_deal_id',
        'captcha_passed',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'captcha_passed' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function leadForm(): BelongsTo
    {
        return $this->belongsTo(LeadForm::class);
    }

    public function createdPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_person_id');
    }

    public function createdDeal(): BelongsTo
    {
        return $this->belongsTo(Deal::class, 'created_deal_id');
    }
}
