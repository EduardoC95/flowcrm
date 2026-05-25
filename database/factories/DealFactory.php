<?php

namespace Database\Factories;

use App\Models\Deal;
use App\Models\Entity;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    protected $model = Deal::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'entity_id' => null,
            'title' => fake()->sentence(3),
            'stage' => 'new',
            'value' => fake()->randomFloat(2, 1000, 50000),
            'expected_close_date' => now()->addMonth(),
        ];
    }

    public function forEntity(Entity $entity): static
    {
        return $this->state(fn () => [
            'tenant_id' => $entity->tenant_id,
            'entity_id' => $entity->id,
        ]);
    }
}
