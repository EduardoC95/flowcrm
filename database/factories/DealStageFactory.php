<?php

namespace Database\Factories;

use App\Models\DealStage;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DealStage>
 */
class DealStageFactory extends Factory
{
    protected $model = DealStage::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'color' => fake()->hexColor(),
            'position' => fake()->numberBetween(1, 10),
            'is_won' => false,
            'is_lost' => false,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn () => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
