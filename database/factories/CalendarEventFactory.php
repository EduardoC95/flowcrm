<?php

namespace Database\Factories;

use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\Person;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    protected $model = CalendarEvent::class;

    public function definition(): array
    {
        $startsAt = now()->addDays(fake()->numberBetween(1, 14))->setTime(10, 0);

        return [
            'tenant_id' => Tenant::factory(),
            'entity_id' => null,
            'person_id' => null,
            'deal_id' => null,
            'title' => fake()->sentence(3),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'location' => fake()->city(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function forDeal(Deal $deal): static
    {
        return $this->state(fn () => [
            'tenant_id' => $deal->tenant_id,
            'entity_id' => $deal->entity_id,
            'person_id' => $deal->person_id,
            'deal_id' => $deal->id,
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
}
