<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DealStageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DealStage extends Model
{
    /** @use HasFactory<DealStageFactory> */
    use BelongsToTenant, HasFactory;

    public const SLUG_LEAD = 'lead';

    public const SLUG_PROPOSAL = 'proposal';

    public const SLUG_NEGOTIATION = 'negotiation';

    public const SLUG_FOLLOW_UP = 'follow-up';

    public const SLUG_WON = 'won';

    public const SLUG_LOST = 'lost';

    public const DEFAULT_STAGES = [
        ['name' => 'Lead', 'slug' => self::SLUG_LEAD, 'color' => '#0ea5e9', 'position' => 1, 'is_won' => false, 'is_lost' => false],
        ['name' => 'Proposta', 'slug' => self::SLUG_PROPOSAL, 'color' => '#8b5cf6', 'position' => 2, 'is_won' => false, 'is_lost' => false],
        ['name' => 'Negociação', 'slug' => self::SLUG_NEGOTIATION, 'color' => '#f59e0b', 'position' => 3, 'is_won' => false, 'is_lost' => false],
        ['name' => 'Follow Up', 'slug' => self::SLUG_FOLLOW_UP, 'color' => '#14b8a6', 'position' => 4, 'is_won' => false, 'is_lost' => false],
        ['name' => 'Ganho', 'slug' => self::SLUG_WON, 'color' => '#22c55e', 'position' => 5, 'is_won' => true, 'is_lost' => false],
        ['name' => 'Perdido', 'slug' => self::SLUG_LOST, 'color' => '#ef4444', 'position' => 6, 'is_won' => false, 'is_lost' => true],
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'color',
        'position',
        'is_won',
        'is_lost',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_won' => 'boolean',
            'is_lost' => 'boolean',
        ];
    }

    public static function ensureDefaultStages(Tenant $tenant): void
    {
        foreach (self::DEFAULT_STAGES as $stage) {
            self::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'slug' => $stage['slug'],
                ],
                $stage,
            );
        }
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
