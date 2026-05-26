<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'sku',
        'description',
        'unit_price',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'unit_price' => 'decimal:2',
        ];
    }

    public function dealProducts(): HasMany
    {
        return $this->hasMany(DealProduct::class);
    }

    public function deals(): BelongsToMany
    {
        return $this->belongsToMany(Deal::class, 'deal_products')
            ->withPivot(['tenant_id', 'quantity', 'unit_price', 'total'])
            ->withTimestamps();
    }
}
