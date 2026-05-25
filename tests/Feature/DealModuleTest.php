<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Entity;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DealModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_tenant_can_view_deals_index(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $deal = $this->dealForTenant($tenant, $user, ['title' => 'CRM rollout']);

        $this->actingAs($user)
            ->get(route('deals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('deals/Index')
                ->has('deals.data', 1)
                ->where('deals.data.0.id', $deal->id)
                ->where('deals.data.0.title', 'CRM rollout')
                ->etc());
    }

    public function test_user_can_view_kanban_board(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $this->dealForTenant($tenant, $user);

        $this->actingAs($user)
            ->get(route('deals.board'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('deals/Board')
                ->has('stages', 6)
                ->etc());
    }

    public function test_user_can_create_deal_associated_to_entity(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $entity = Entity::factory()->create(['tenant_id' => $tenant->id]);
        $stage = $this->stage($tenant, DealStage::SLUG_LEAD);

        $response = $this->actingAs($user)->post(route('deals.store'), [
            ...$this->dealPayload($user, $stage),
            'entity_id' => $entity->id,
            'title' => 'Entity opportunity',
        ]);

        $deal = Deal::firstWhere('title', 'Entity opportunity');

        $response->assertRedirect(route('deals.show', $deal));
        $this->assertDatabaseHas('deals', [
            'tenant_id' => $tenant->id,
            'entity_id' => $entity->id,
            'owner_id' => $user->id,
            'deal_stage_id' => $stage->id,
            'title' => 'Entity opportunity',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'action' => 'deal.created',
            'module' => 'deals',
        ]);
    }

    public function test_user_can_create_deal_associated_to_person(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $entity = Entity::factory()->create(['tenant_id' => $tenant->id]);
        $person = Person::factory()->forEntity($entity)->create();
        $stage = $this->stage($tenant, DealStage::SLUG_PROPOSAL);

        $this->actingAs($user)
            ->post(route('deals.store'), [
                ...$this->dealPayload($user, $stage),
                'person_id' => $person->id,
                'entity_id' => null,
                'title' => 'Person opportunity',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('deals', [
            'tenant_id' => $tenant->id,
            'entity_id' => $entity->id,
            'person_id' => $person->id,
            'title' => 'Person opportunity',
        ]);
    }

    public function test_user_cannot_create_deal_without_entity_or_person(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $stage = $this->stage($tenant, DealStage::SLUG_LEAD);

        $this->actingAs($user)
            ->from(route('deals.create'))
            ->post(route('deals.store'), $this->dealPayload($user, $stage))
            ->assertRedirect(route('deals.create'))
            ->assertSessionHasErrors(['entity_id', 'person_id']);
    }

    public function test_user_cannot_use_other_tenant_entity_person_stage_or_owner(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        [$otherUser, $otherTenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $stage = $this->stage($tenant, DealStage::SLUG_LEAD);
        $otherStage = $this->stage($otherTenant, DealStage::SLUG_LEAD);
        $otherEntity = Entity::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherPerson = Person::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->actingAs($user)
            ->from(route('deals.create'))
            ->post(route('deals.store'), [
                ...$this->dealPayload($user, $stage),
                'entity_id' => $otherEntity->id,
                'person_id' => $otherPerson->id,
                'deal_stage_id' => $otherStage->id,
                'owner_id' => $otherUser->id,
            ])
            ->assertRedirect(route('deals.create'))
            ->assertSessionHasErrors(['entity_id', 'person_id', 'deal_stage_id', 'owner_id']);
    }

    public function test_user_can_update_deal(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_MANAGER);
        $deal = $this->dealForTenant($tenant, $user, ['title' => 'Original deal']);
        $stage = $this->stage($tenant, DealStage::SLUG_NEGOTIATION);

        $this->actingAs($user)
            ->put(route('deals.update', $deal), [
                ...$this->dealPayload($user, $stage),
                'entity_id' => $deal->entity_id,
                'title' => 'Updated deal',
                'value' => 12345,
                'probability' => 70,
            ])
            ->assertRedirect(route('deals.show', $deal));

        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'title' => 'Updated deal',
            'deal_stage_id' => $stage->id,
            'value' => 12345,
            'probability' => 70,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'action' => 'deal.updated',
        ]);
    }

    public function test_user_can_soft_delete_deal(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $deal = $this->dealForTenant($tenant, $user);

        $this->actingAs($user)
            ->delete(route('deals.destroy', $deal))
            ->assertRedirect(route('deals.index'));

        $this->assertSoftDeleted('deals', ['id' => $deal->id]);
        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'action' => 'deal.deleted',
        ]);
    }

    public function test_user_can_move_deal_between_stages_and_log_is_created(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $deal = $this->dealForTenant($tenant, $user, [
            'deal_stage_id' => $this->stage($tenant, DealStage::SLUG_LEAD)->id,
            'stage' => DealStage::SLUG_LEAD,
        ]);
        $newStage = $this->stage($tenant, DealStage::SLUG_FOLLOW_UP);

        $this->actingAs($user)
            ->patchJson(route('deals.move-stage', $deal), [
                'deal_stage_id' => $newStage->id,
            ])
            ->assertOk()
            ->assertJsonPath('deal.stage.slug', DealStage::SLUG_FOLLOW_UP);

        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'deal_stage_id' => $newStage->id,
            'stage' => DealStage::SLUG_FOLLOW_UP,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'deal.stage_moved',
            'subject_id' => $deal->id,
        ]);
    }

    public function test_board_calculates_total_by_stage(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $stage = $this->stage($tenant, DealStage::SLUG_LEAD);
        $this->dealForTenant($tenant, $user, ['deal_stage_id' => $stage->id, 'stage' => $stage->slug, 'value' => 1000]);
        $this->dealForTenant($tenant, $user, ['deal_stage_id' => $stage->id, 'stage' => $stage->slug, 'value' => 2500]);

        $this->actingAs($user)
            ->get(route('deals.board'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stages.0.slug', DealStage::SLUG_LEAD)
                ->where('stages.0.deals_count', 2)
                ->where('stages.0.total_value', 3500)
                ->etc());
    }

    public function test_board_filters_by_owner_date_and_value(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $otherOwner = User::factory()->create(['current_tenant_id' => $tenant->id]);
        $otherOwner->tenants()->attach($tenant->id, ['role' => Tenant::ROLE_SALES]);
        $stage = $this->stage($tenant, DealStage::SLUG_LEAD);

        $this->dealForTenant($tenant, $user, [
            'deal_stage_id' => $stage->id,
            'stage' => $stage->slug,
            'title' => 'Visible deal',
            'owner_id' => $user->id,
            'value' => 3000,
            'expected_close_date' => '2026-06-10',
        ]);
        $this->dealForTenant($tenant, $otherOwner, [
            'deal_stage_id' => $stage->id,
            'stage' => $stage->slug,
            'title' => 'Hidden deal',
            'owner_id' => $otherOwner->id,
            'value' => 100,
            'expected_close_date' => '2026-07-10',
        ]);

        $this->actingAs($user)
            ->get(route('deals.board', [
                'owner_id' => $user->id,
                'expected_close_date_from' => '2026-06-01',
                'expected_close_date_to' => '2026-06-30',
                'min_value' => 1000,
                'max_value' => 4000,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stages.0.deals_count', 1)
                ->where('stages.0.deals.0.title', 'Visible deal')
                ->etc());
    }

    public function test_user_cannot_access_deal_from_another_tenant(): void
    {
        [$user] = $this->userWithTenant(Tenant::ROLE_OWNER);
        [$otherUser, $otherTenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $otherDeal = $this->dealForTenant($otherTenant, $otherUser);

        $this->actingAs($user)
            ->get(route('deals.show', $otherDeal))
            ->assertNotFound();
    }

    public function test_viewer_can_view_but_cannot_manage_deals(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_VIEWER);
        $owner = User::factory()->create(['current_tenant_id' => $tenant->id]);
        $owner->tenants()->attach($tenant->id, ['role' => Tenant::ROLE_OWNER]);
        $deal = $this->dealForTenant($tenant, $owner);
        $stage = $this->stage($tenant, DealStage::SLUG_PROPOSAL);

        $this->actingAs($user)->get(route('deals.show', $deal))->assertOk();
        $this->actingAs($user)->get(route('deals.create'))->assertForbidden();
        $this->actingAs($user)->delete(route('deals.destroy', $deal))->assertForbidden();
        $this->actingAs($user)->patchJson(route('deals.move-stage', $deal), ['deal_stage_id' => $stage->id])->assertForbidden();
    }

    public function test_logs_are_created_when_creating_updating_and_deleting_deals(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $entity = Entity::factory()->create(['tenant_id' => $tenant->id]);
        $stage = $this->stage($tenant, DealStage::SLUG_LEAD);
        $this->actingAs($user);

        $deal = Deal::create([
            'tenant_id' => $tenant->id,
            'entity_id' => $entity->id,
            'owner_id' => $user->id,
            'deal_stage_id' => $stage->id,
            'title' => 'Logged deal',
            'stage' => $stage->slug,
            'value' => 1000,
        ]);
        $deal->update(['title' => 'Logged deal updated']);
        $deal->delete();

        $this->assertSame(3, ActivityLog::where('tenant_id', $tenant->id)->where('module', 'deals')->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'deal.created', 'subject_id' => $deal->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'deal.updated', 'subject_id' => $deal->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'deal.deleted', 'subject_id' => $deal->id]);
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

        $user->tenants()->attach($tenant->id, [
            'role' => $role,
        ]);

        return [$user->refresh(), $tenant];
    }

    private function stage(Tenant $tenant, string $slug): DealStage
    {
        return DealStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function dealForTenant(Tenant $tenant, User $owner, array $attributes = []): Deal
    {
        $entity = $attributes['entity'] ?? Entity::factory()->create(['tenant_id' => $tenant->id]);
        $stage = $attributes['deal_stage_id'] ?? $this->stage($tenant, DealStage::SLUG_LEAD)->id;

        return Deal::factory()->create([
            'tenant_id' => $tenant->id,
            'entity_id' => $entity instanceof Entity ? $entity->id : $entity,
            'owner_id' => $owner->id,
            'deal_stage_id' => $stage,
            'stage' => $attributes['stage'] ?? DealStage::withoutGlobalScopes()->find($stage)?->slug ?? DealStage::SLUG_LEAD,
            ...$attributes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dealPayload(User $owner, DealStage $stage): array
    {
        return [
            'title' => 'Pipeline opportunity',
            'entity_id' => null,
            'person_id' => null,
            'owner_id' => $owner->id,
            'deal_stage_id' => $stage->id,
            'value' => 5000,
            'probability' => 40,
            'expected_close_date' => '2026-06-15',
            'priority' => Deal::PRIORITY_MEDIUM,
            'description' => 'Qualified commercial opportunity.',
        ];
    }
}
