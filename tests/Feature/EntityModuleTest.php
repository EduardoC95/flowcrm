<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Deal;
use App\Models\Entity;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EntityModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_tenant_can_view_entities_index(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        Entity::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Acme Portugal',
        ]);

        $this->actingAs($user)
            ->get(route('entities.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('entities/Index')
                ->has('entities.data', 1)
                ->where('entities.data.0.name', 'Acme Portugal')
                ->etc());
    }

    public function test_user_can_create_entity(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);

        $response = $this->actingAs($user)->post(route('entities.store'), [
            'name' => 'Created Entity',
            'vat' => 'PT123456789',
            'email' => 'created@example.test',
            'phone' => '+351 210 000 000',
            'address' => 'Rua Exemplo 1',
            'status' => Entity::STATUS_PROSPECT,
            'notes' => 'Initial notes',
        ]);

        $entity = Entity::firstWhere('name', 'Created Entity');

        $response->assertRedirect(route('entities.show', $entity));
        $this->assertDatabaseHas('entities', [
            'tenant_id' => $tenant->id,
            'name' => 'Created Entity',
            'status' => Entity::STATUS_PROSPECT,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'action' => 'entity.created',
            'module' => 'entities',
        ]);
    }

    public function test_user_can_update_entity(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_MANAGER);
        $entity = Entity::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Original Entity',
        ]);

        $this->actingAs($user)
            ->put(route('entities.update', $entity), [
                'name' => 'Updated Entity',
                'vat' => 'PT987654321',
                'email' => 'updated@example.test',
                'phone' => '+351 220 000 000',
                'address' => 'Rua Atualizada 2',
                'status' => Entity::STATUS_CLIENT,
                'notes' => 'Updated notes',
            ])
            ->assertRedirect(route('entities.show', $entity));

        $this->assertDatabaseHas('entities', [
            'id' => $entity->id,
            'name' => 'Updated Entity',
            'status' => Entity::STATUS_CLIENT,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'action' => 'entity.updated',
        ]);
    }

    public function test_user_can_soft_delete_entity(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $entity = Entity::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($user)
            ->delete(route('entities.destroy', $entity))
            ->assertRedirect(route('entities.index'));

        $this->assertSoftDeleted('entities', [
            'id' => $entity->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'action' => 'entity.deleted',
        ]);
    }

    public function test_search_by_name_filters_entities(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        Entity::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Needle Company']);
        Entity::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Other Company']);

        $this->actingAs($user)
            ->get(route('entities.index', ['search' => 'Needle']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('entities.data', 1)
                ->where('entities.data.0.name', 'Needle Company')
                ->etc());
    }

    public function test_status_filter_filters_entities(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        Entity::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Client Company', 'status' => Entity::STATUS_CLIENT]);
        Entity::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Lead Company', 'status' => Entity::STATUS_LEAD]);

        $this->actingAs($user)
            ->get(route('entities.index', ['status' => Entity::STATUS_CLIENT]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('entities.data', 1)
                ->where('entities.data.0.status', Entity::STATUS_CLIENT)
                ->etc());
    }

    public function test_entity_detail_includes_related_people_and_deals(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $entity = Entity::factory()->create(['tenant_id' => $tenant->id]);
        Person::factory()->forEntity($entity)->create(['name' => 'Maria Silva']);
        Deal::factory()->forEntity($entity)->create(['title' => 'CRM rollout']);

        $this->actingAs($user)
            ->get(route('entities.show', $entity))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('entities/Show')
                ->where('entity.people.0.name', 'Maria Silva')
                ->where('entity.deals.0.title', 'CRM rollout')
                ->etc());
    }

    public function test_user_cannot_access_entity_from_another_tenant(): void
    {
        [$user] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $otherEntity = Entity::factory()->create();

        $this->actingAs($user)
            ->get(route('entities.show', $otherEntity))
            ->assertNotFound();
    }

    public function test_viewer_can_view_but_cannot_manage_entities(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_VIEWER);
        $entity = Entity::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user)->get(route('entities.show', $entity))->assertOk();
        $this->actingAs($user)->get(route('entities.create'))->assertForbidden();
        $this->actingAs($user)->delete(route('entities.destroy', $entity))->assertForbidden();
    }

    public function test_logs_are_created_when_creating_updating_and_deleting_entities(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $this->actingAs($user);

        $entity = Entity::create([
            'name' => 'Logged Entity',
            'status' => Entity::STATUS_ACTIVE,
        ]);
        $entity->update(['name' => 'Logged Entity Updated']);
        $entity->delete();

        $this->assertSame(3, ActivityLog::where('tenant_id', $tenant->id)->where('module', 'entities')->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'entity.created', 'subject_id' => $entity->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'entity.updated', 'subject_id' => $entity->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'entity.deleted', 'subject_id' => $entity->id]);
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
