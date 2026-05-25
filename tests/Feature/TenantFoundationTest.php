<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Entity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TenantFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_tenant_is_redirected_to_onboarding(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('tenant.onboarding', absolute: false));
    }

    public function test_user_cannot_access_records_from_another_tenant(): void
    {
        [$user] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $otherTenant = Tenant::factory()->create();
        $otherEntity = Entity::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $this->actingAs($user);

        $this->assertNull(Entity::find($otherEntity->id));
        $this->assertFalse(Gate::forUser($user)->allows('view', $otherEntity));
    }

    public function test_owner_can_view_records_from_their_own_tenant(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $entity = Entity::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($user);

        $this->assertTrue(Entity::whereKey($entity->id)->exists());
        $this->assertTrue(Gate::forUser($user)->allows('view', $entity));
    }

    public function test_activity_logs_are_created_for_loggable_model_changes(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $this->actingAs($user);

        $initialCount = ActivityLog::count();

        $entity = Entity::create([
            'name' => 'Example Company',
            'type' => 'company',
            'email' => 'hello@example.test',
        ]);

        $entity->update([
            'phone' => '+351 210 000 000',
        ]);

        $entity->delete();

        $this->assertSame($initialCount + 3, ActivityLog::count());
        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'module' => 'entities',
            'action' => 'entity.created',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'module' => 'entities',
            'action' => 'entity.updated',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'module' => 'entities',
            'action' => 'entity.deleted',
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
