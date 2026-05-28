<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIChatMessage extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'ai_chat_messages';

    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public const ROLE_SYSTEM = 'system';

    public const ROLES = [
        self::ROLE_USER,
        self::ROLE_ASSISTANT,
        self::ROLE_SYSTEM,
    ];

    protected $fillable = [
        'tenant_id',
        'ai_chat_conversation_id',
        'user_id',
        'role',
        'content',
        'intent',
        'metadata',
        'created_records',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_records' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AIChatConversation::class, 'ai_chat_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
