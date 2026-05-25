<?php

namespace Database\Factories;

use App\Models\Entity;
use App\Models\Person;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'entity_id' => null,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'position' => fake()->jobTitle(),
            'status' => fake()->randomElement(Person::STATUSES),
            'notes' => fake()->optional()->sentence(),
            'job_title' => fake()->jobTitle(),
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
