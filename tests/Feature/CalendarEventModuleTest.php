<?php

namespace Tests\Feature;

use App\Mail\CalendarEventReminderMail;
use App\Models\ActivityLog;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Entity;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CalendarEventModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_tenant_can_view_calendar(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $this->eventForTenant($tenant, $user, ['title' => 'Planning meeting']);

        $this->actingAs($user)
            ->get(route('calendar.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('calendar/Index')
                ->has('events.data', 1)
                ->where('events.data.0.title', 'Planning meeting')
                ->etc());
    }

    public function test_calendar_feed_returns_tenant_events(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        [$otherUser, $otherTenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $this->eventForTenant($tenant, $user, ['title' => 'Visible event']);
        $this->eventForTenant($otherTenant, $otherUser, ['title' => 'Hidden event']);

        $this->actingAs($user)
            ->getJson(route('calendar.feed'))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Visible event');
    }

    public function test_user_can_create_event_without_association(): void
    {
        [$user] = $this->userWithTenant(Tenant::ROLE_OWNER);

        $response = $this->actingAs($user)->post(route('calendar-events.store'), $this->eventPayload($user, [
            'title' => 'Standalone task',
        ]));

        $event = CalendarEvent::firstWhere('title', 'Standalone task');

        $response->assertRedirect(route('calendar-events.show', $event));
        $this->assertDatabaseHas('calendar_events', [
            'id' => $event->id,
            'eventable_type' => null,
            'eventable_id' => null,
        ]);
    }

    public function test_user_can_create_event_associated_to_entity_person_and_deal(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $entity = Entity::factory()->create(['tenant_id' => $tenant->id]);
        $person = Person::factory()->forEntity($entity)->create();
        $deal = $this->dealForTenant($tenant, $user, $entity, $person);

        $this->actingAs($user)->post(route('calendar-events.store'), $this->eventPayload($user, [
            'title' => 'Entity event',
            'eventable_type' => 'entity',
            'eventable_id' => $entity->id,
        ]))->assertRedirect();
        $this->actingAs($user)->post(route('calendar-events.store'), $this->eventPayload($user, [
            'title' => 'Person event',
            'eventable_type' => 'person',
            'eventable_id' => $person->id,
        ]))->assertRedirect();
        $this->actingAs($user)->post(route('calendar-events.store'), $this->eventPayload($user, [
            'title' => 'Deal event',
            'eventable_type' => 'deal',
            'eventable_id' => $deal->id,
        ]))->assertRedirect();

        $this->assertDatabaseHas('calendar_events', ['title' => 'Entity event', 'eventable_type' => Entity::class, 'eventable_id' => $entity->id]);
        $this->assertDatabaseHas('calendar_events', ['title' => 'Person event', 'eventable_type' => Person::class, 'eventable_id' => $person->id]);
        $this->assertDatabaseHas('calendar_events', ['title' => 'Deal event', 'eventable_type' => Deal::class, 'eventable_id' => $deal->id]);
        $this->assertNotNull($deal->fresh()->last_activity_at);
    }

    public function test_user_cannot_associate_event_to_other_tenant_records_or_owner(): void
    {
        [$user] = $this->userWithTenant(Tenant::ROLE_OWNER);
        [$otherUser, $otherTenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $otherEntity = Entity::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherPerson = Person::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherDeal = $this->dealForTenant($otherTenant, $otherUser, $otherEntity, $otherPerson);

        foreach ([['entity', $otherEntity->id], ['person', $otherPerson->id], ['deal', $otherDeal->id]] as [$type, $id]) {
            $this->actingAs($user)
                ->from(route('calendar-events.create'))
                ->post(route('calendar-events.store'), $this->eventPayload($user, [
                    'eventable_type' => $type,
                    'eventable_id' => $id,
                ]))
                ->assertRedirect(route('calendar-events.create'))
                ->assertSessionHasErrors('eventable_id');
        }

        $this->actingAs($user)
            ->from(route('calendar-events.create'))
            ->post(route('calendar-events.store'), $this->eventPayload($otherUser))
            ->assertRedirect(route('calendar-events.create'))
            ->assertSessionHasErrors('owner_id');
    }

    public function test_user_can_update_and_soft_delete_event(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_MANAGER);
        $event = $this->eventForTenant($tenant, $user, ['title' => 'Original event']);

        $this->actingAs($user)
            ->put(route('calendar-events.update', $event), $this->eventPayload($user, [
                'title' => 'Updated event',
                'status' => CalendarEvent::STATUS_COMPLETED,
            ]))
            ->assertRedirect(route('calendar-events.show', $event));

        $this->assertDatabaseHas('calendar_events', [
            'id' => $event->id,
            'title' => 'Updated event',
            'status' => CalendarEvent::STATUS_COMPLETED,
        ]);

        $this->actingAs($user)
            ->delete(route('calendar-events.destroy', $event))
            ->assertRedirect(route('calendar.index'));

        $this->assertSoftDeleted('calendar_events', ['id' => $event->id]);
    }

    public function test_user_can_complete_and_cancel_event(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $event = $this->eventForTenant($tenant, $user);

        $this->actingAs($user)
            ->patch(route('calendar-events.complete', $event))
            ->assertRedirect();

        $this->assertDatabaseHas('calendar_events', ['id' => $event->id, 'status' => CalendarEvent::STATUS_COMPLETED]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'calendar_event.completed', 'subject_id' => $event->id]);

        $this->actingAs($user)
            ->patch(route('calendar-events.cancel', $event))
            ->assertRedirect();

        $this->assertDatabaseHas('calendar_events', ['id' => $event->id, 'status' => CalendarEvent::STATUS_CANCELLED]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'calendar_event.cancelled', 'subject_id' => $event->id]);
    }

    public function test_logs_are_created_for_calendar_event_lifecycle(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $this->actingAs($user);

        $event = CalendarEvent::create([
            'tenant_id' => $tenant->id,
            'owner_id' => $user->id,
            'title' => 'Logged event',
            'type' => CalendarEvent::TYPE_TASK,
            'status' => CalendarEvent::STATUS_PENDING,
            'start_at' => now()->addDay(),
            'starts_at' => now()->addDay(),
        ]);
        $event->update(['title' => 'Logged event updated']);
        $event->delete();

        $this->assertDatabaseHas('activity_logs', ['action' => 'calendar_event.created', 'subject_id' => $event->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'calendar_event.updated', 'subject_id' => $event->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'calendar_event.deleted', 'subject_id' => $event->id]);
    }

    public function test_due_reminder_is_sent_once(): void
    {
        Mail::fake();
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $event = $this->eventForTenant($tenant, $user, [
            'title' => 'Reminder event',
            'reminder_at' => now()->subMinute(),
            'reminder_sent_at' => null,
            'status' => CalendarEvent::STATUS_PENDING,
        ]);

        Artisan::call('calendar:send-reminders');
        Artisan::call('calendar:send-reminders');

        Mail::assertSent(CalendarEventReminderMail::class, 1);
        $this->assertNotNull($event->fresh()->reminder_sent_at);
        $this->assertSame(1, ActivityLog::where('action', 'calendar_event.reminder_sent')->where('subject_id', $event->id)->count());
    }

    public function test_viewer_cannot_manage_events_and_cross_tenant_isolated(): void
    {
        [$viewer, $tenant] = $this->userWithTenant(Tenant::ROLE_VIEWER);
        $owner = User::factory()->create(['current_tenant_id' => $tenant->id]);
        $owner->tenants()->attach($tenant->id, ['role' => Tenant::ROLE_OWNER]);
        $event = $this->eventForTenant($tenant, $owner);
        [$otherUser, $otherTenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $otherEvent = $this->eventForTenant($otherTenant, $otherUser);

        $this->actingAs($viewer)->get(route('calendar-events.show', $event))->assertOk();
        $this->actingAs($viewer)->get(route('calendar-events.create'))->assertForbidden();
        $this->actingAs($viewer)->delete(route('calendar-events.destroy', $event))->assertForbidden();
        $this->actingAs($viewer)->patch(route('calendar-events.complete', $event))->assertForbidden();
        $this->actingAs($viewer)->patch(route('calendar-events.cancel', $event))->assertForbidden();
        $this->actingAs($viewer)->get(route('calendar-events.show', $otherEvent))->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function userWithTenant(string $role): array
    {
        $tenant = Tenant::factory()->create();
        DealStage::ensureDefaultStages($tenant);
        $user = User::factory()->create([
            'current_tenant_id' => $tenant->id,
        ]);

        $user->tenants()->attach($tenant->id, ['role' => $role]);

        return [$user->refresh(), $tenant];
    }

    private function eventForTenant(Tenant $tenant, User $owner, array $attributes = []): CalendarEvent
    {
        $startAt = $attributes['start_at'] ?? now()->addDay()->setTime(10, 0);

        return CalendarEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'owner_id' => $owner->id,
            'start_at' => $startAt,
            'end_at' => $attributes['end_at'] ?? $startAt->copy()->addHour(),
            'starts_at' => $startAt,
            'ends_at' => $attributes['end_at'] ?? $startAt->copy()->addHour(),
            ...$attributes,
        ]);
    }

    private function dealForTenant(Tenant $tenant, User $owner, Entity $entity, Person $person): Deal
    {
        $stage = DealStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', DealStage::SLUG_LEAD)
            ->firstOrFail();

        return Deal::factory()->create([
            'tenant_id' => $tenant->id,
            'entity_id' => $entity->id,
            'person_id' => $person->id,
            'owner_id' => $owner->id,
            'deal_stage_id' => $stage->id,
            'stage' => $stage->slug,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function eventPayload(User $owner, array $overrides = []): array
    {
        return [
            'title' => 'Calendar event',
            'description' => 'Commercial activity.',
            'type' => CalendarEvent::TYPE_TASK,
            'status' => CalendarEvent::STATUS_PENDING,
            'start_at' => '2026-06-01 10:00:00',
            'end_at' => '2026-06-01 11:00:00',
            'location' => 'Remote',
            'owner_id' => $owner->id,
            'priority' => CalendarEvent::PRIORITY_MEDIUM,
            'reminder_at' => '2026-06-01 09:30:00',
            'eventable_type' => null,
            'eventable_id' => null,
            ...$overrides,
        ];
    }
}
