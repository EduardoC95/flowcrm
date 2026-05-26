<?php

namespace Database\Factories;

use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\Entity;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    protected $model = CalendarEvent::class;

    public function definition(): array
    {
        $startAt = now()->addDays(fake()->numberBetween(1, 14))->setTime(10, 0);

        return [
            'tenant_id' => Tenant::factory(),
            'eventable_type' => null,
            'eventable_id' => null,
            'entity_id' => null,
            'person_id' => null,
            'deal_id' => null,
            'owner_id' => null,
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'type' => fake()->randomElement(CalendarEvent::TYPES),
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addHour(),
            'starts_at' => $startAt,
            'ends_at' => $startAt->copy()->addHour(),
            'location' => fake()->city(),
            'priority' => fake()->randomElement(CalendarEvent::PRIORITIES),
            'status' => CalendarEvent::STATUS_PENDING,
            'reminder_at' => null,
            'reminder_sent_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function forDeal(Deal $deal): static
    {
        return $this->state(fn () => [
            'tenant_id' => $deal->tenant_id,
            'eventable_type' => Deal::class,
            'eventable_id' => $deal->id,
            'entity_id' => $deal->entity_id,
            'person_id' => $deal->person_id,
            'deal_id' => $deal->id,
        ]);
    }

    public function forPerson(Person $person): static
    {
        return $this->state(fn () => [
            'tenant_id' => $person->tenant_id,
            'eventable_type' => Person::class,
            'eventable_id' => $person->id,
            'entity_id' => $person->entity_id,
            'person_id' => $person->id,
        ]);
    }

    public function forEntity(Entity $entity): static
    {
        return $this->state(fn () => [
            'tenant_id' => $entity->tenant_id,
            'eventable_type' => Entity::class,
            'eventable_id' => $entity->id,
            'entity_id' => $entity->id,
        ]);
    }

    public function ownedBy(User $user): static
    {
        return $this->state(fn () => [
            'owner_id' => $user->id,
        ]);
    }
}
