<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeadForm extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const FIELD_TEXT = 'text';

    public const FIELD_EMAIL = 'email';

    public const FIELD_PHONE = 'phone';

    public const FIELD_TEXTAREA = 'textarea';

    public const FIELD_SELECT = 'select';

    public const FIELD_TYPES = [
        self::FIELD_TEXT,
        self::FIELD_EMAIL,
        self::FIELD_PHONE,
        self::FIELD_TEXTAREA,
        self::FIELD_SELECT,
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'fields',
        'confirmation_message',
        'active',
        'require_captcha',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'active' => 'boolean',
            'require_captcha' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(LeadFormSubmission::class);
    }
}
