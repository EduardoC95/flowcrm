<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\Entity;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersonModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_tenant_can_view_people_index(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        Person::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Maria Silva',
        ]);

        $this->actingAs($user)
            ->get(route('people.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('people/Index')
                ->has('people.data', 1)
                ->where('people.data.0.name', 'Maria Silva')
                ->etc());
    }

    public function test_user_can_create_person_without_entity(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);

        $response = $this->actingAs($user)->post(route('people.store'), [
            'name' => 'Standalone Person',
            'email' => 'standalone@example.test',
            'phone' => '+351 910 200 001',
            'position' => 'Consultant',
            'status' => Person::STATUS_ACTIVE,
            'notes' => 'No entity yet.',
        ]);

        $person = Person::firstWhere('name', 'Standalone Person');

        $response->assertRedirect(route('people.show', $person));
        $this->assertDatabaseHas('people', [
            'tenant_id' => $tenant->id,
            'entity_id' => null,
            'name' => 'Standalone Person',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'action' => 'person.created',
            'module' => 'people',
        ]);
    }

    public function test_user_can_create_person_associated_to_same_tenant_entity(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $entity = Entity::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user)
            ->post(route('people.store'), [
                'name' => 'Associated Person',
                'entity_id' => $entity->id,
                'email' => 'associated@example.test',
                'phone' => '+351 910 200 002',
                'position' => 'Buyer',
                'status' => Person::STATUS_CLIENT,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('people', [
            'tenant_id' => $tenant->id,
            'entity_id' => $entity->id,
            'name' => 'Associated Person',
        ]);
    }

    public function test_user_cannot_create_person_associated_to_other_tenant_entity(): void
    {
        [$user] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $otherEntity = Entity::factory()->create();

        $this->actingAs($user)
            ->from(route('people.create'))
            ->post(route('people.store'), [
                'name' => 'Invalid Person',
                'entity_id' => $otherEntity->id,
                'email' => 'invalid@example.test',
                'status' => Person::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('people.create'))
            ->assertSessionHasErrors('entity_id');

        $this->assertDatabaseMissing('people', [
            'name' => 'Invalid Person',
        ]);
    }

    public function test_user_can_update_person(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_MANAGER);
        $person = Person::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Original Person',
        ]);

        $this->actingAs($user)
            ->put(route('people.update', $person), [
                'name' => 'Updated Person',
                'email' => 'updated.person@example.test',
                'phone' => '+351 910 200 003',
                'position' => 'Decision Maker',
                'status' => Person::STATUS_PROSPECT,
                'notes' => 'Updated notes.',
            ])
            ->assertRedirect(route('people.show', $person));

        $this->assertDatabaseHas('people', [
            'id' => $person->id,
            'name' => 'Updated Person',
            'status' => Person::STATUS_PROSPECT,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'action' => 'person.updated',
        ]);
    }

    public function test_user_can_soft_delete_person(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $person = Person::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user)
            ->delete(route('people.destroy', $person))
            ->assertRedirect(route('people.index'));

        $this->assertSoftDeleted('people', ['id' => $person->id]);
        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'action' => 'person.deleted',
        ]);
    }

    public function test_search_by_name_filters_people(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        Person::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Needle Person']);
        Person::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Other Person']);

        $this->actingAs($user)
            ->get(route('people.index', ['search' => 'Needle']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('people.data', 1)
                ->where('people.data.0.name', 'Needle Person')
                ->etc());
    }

    public function test_status_filter_filters_people(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        Person::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Client Person', 'status' => Person::STATUS_CLIENT]);
        Person::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Lead Person', 'status' => Person::STATUS_LEAD]);

        $this->actingAs($user)
            ->get(route('people.index', ['status' => Person::STATUS_CLIENT]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('people.data', 1)
                ->where('people.data.0.status', Person::STATUS_CLIENT)
                ->etc());
    }

    public function test_entity_filter_filters_people(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $entity = Entity::factory()->create(['tenant_id' => $tenant->id]);
        Person::factory()->forEntity($entity)->create(['name' => 'Entity Person']);
        Person::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Standalone Person']);

        $this->actingAs($user)
            ->get(route('people.index', ['entity_id' => $entity->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('people.data', 1)
                ->where('people.data.0.name', 'Entity Person')
                ->where('people.data.0.entity.name', $entity->name)
                ->etc());
    }

    public function test_person_detail_includes_entity_deals_and_events(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $entity = Entity::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Acme Portugal']);
        $person = Person::factory()->forEntity($entity)->create(['name' => 'Maria Silva']);
        Deal::factory()->forPerson($person)->create(['title' => 'CRM rollout']);
        CalendarEvent::factory()->forPerson($person)->create(['title' => 'Discovery call']);

        $this->actingAs($user)
            ->get(route('people.show', $person))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('people/Show')
                ->where('person.entity.name', 'Acme Portugal')
                ->where('person.deals.0.title', 'CRM rollout')
                ->where('person.calendar_events.0.title', 'Discovery call')
                ->etc());
    }

    public function test_user_cannot_access_person_from_another_tenant(): void
    {
        [$user] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $otherPerson = Person::factory()->create();

        $this->actingAs($user)
            ->get(route('people.show', $otherPerson))
            ->assertNotFound();
    }

    public function test_viewer_can_view_but_cannot_manage_people(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_VIEWER);
        $person = Person::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user)->get(route('people.show', $person))->assertOk();
        $this->actingAs($user)->get(route('people.create'))->assertForbidden();
        $this->actingAs($user)->delete(route('people.destroy', $person))->assertForbidden();
    }

    public function test_logs_are_created_when_creating_updating_and_deleting_people(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $this->actingAs($user);

        $person = Person::create([
            'name' => 'Logged Person',
            'status' => Person::STATUS_ACTIVE,
        ]);
        $person->update(['name' => 'Logged Person Updated']);
        $person->delete();

        $this->assertSame(3, ActivityLog::where('tenant_id', $tenant->id)->where('module', 'people')->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'person.created', 'subject_id' => $person->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'person.updated', 'subject_id' => $person->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'person.deleted', 'subject_id' => $person->id]);
    }

    public function test_people_can_be_merged(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $target = Person::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Primary Person']);
        $duplicate = Person::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Duplicate Person']);
        $deal = Deal::factory()->forPerson($duplicate)->create();
        $event = CalendarEvent::factory()->forPerson($duplicate)->create();

        $this->actingAs($user)
            ->post(route('people.merge', $duplicate), [
                'target_person_id' => $target->id,
            ])
            ->assertRedirect(route('people.show', $target));

        $this->assertSoftDeleted('people', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('deals', ['id' => $deal->id, 'person_id' => $target->id]);
        $this->assertDatabaseHas('calendar_events', ['id' => $event->id, 'person_id' => $target->id]);
        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'person.merged',
            'subject_id' => $target->id,
        ]);
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function userWithTenant(string $role): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'current_tenant_id' => $tenant->id,
        ]);

        $user->tenants()->attach($tenant->id, [
            'role' => $role,
        ]);

        return [$user->refresh(), $tenant];
    }
}
