<?php

namespace Database\Factories;

use App\Models\Entity;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entity>
 */
class EntityFactory extends Factory
{
    protected $model = Entity::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company(),
            'type' => 'company',
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'metadata' => null,
        ];
    }
}
