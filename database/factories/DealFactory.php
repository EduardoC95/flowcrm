<?php

namespace Database\Factories;

use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Entity;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
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
            'person_id' => null,
            'owner_id' => null,
            'deal_stage_id' => null,
            'title' => fake()->sentence(3),
            'stage' => DealStage::SLUG_LEAD,
            'value' => fake()->randomFloat(2, 1000, 50000),
            'probability' => fake()->numberBetween(5, 90),
            'expected_close_date' => now()->addMonth(),
            'priority' => fake()->randomElement(Deal::PRIORITIES),
            'description' => fake()->optional()->paragraph(),
            'last_activity_at' => null,
        ];
    }

    public function forEntity(Entity $entity): static
    {
        return $this->state(fn () => [
            'tenant_id' => $entity->tenant_id,
            'entity_id' => $entity->id,
        ]);
    }

    public function forPerson(Person $person): static
    {
        return $this->state(fn () => [
            'tenant_id' => $person->tenant_id,
            'entity_id' => $person->entity_id,
            'person_id' => $person->id,
        ]);
    }

    public function forStage(DealStage $stage): static
    {
        return $this->state(fn () => [
            'tenant_id' => $stage->tenant_id,
            'deal_stage_id' => $stage->id,
            'stage' => $stage->slug,
        ]);
    }

    public function ownedBy(User $user): static
    {
        return $this->state(fn () => [
            'owner_id' => $user->id,
        ]);
    }
}
