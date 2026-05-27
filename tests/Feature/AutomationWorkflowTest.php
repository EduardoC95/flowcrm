<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Entity;
use App\Models\InternalNotification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AutomationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_manager_can_create_rules_but_sales_and_viewer_cannot_manage(): void
    {
        [$owner] = $this->userWithTenant(Tenant::ROLE_OWNER);
        [$manager] = $this->userWithTenant(Tenant::ROLE_MANAGER);
        $sales = User::factory()->create(['current_tenant_id' => $owner->current_tenant_id]);
        $sales->tenants()->attach($owner->current_tenant_id, ['role' => Tenant::ROLE_SALES]);
        $viewer = User::factory()->create(['current_tenant_id' => $owner->current_tenant_id]);
        $viewer->tenants()->attach($owner->current_tenant_id, ['role' => Tenant::ROLE_VIEWER]);

        $this->actingAs($owner)
            ->post(route('automations.store'), $this->rulePayload(['name' => 'Owner rule']))
            ->assertRedirect();

        $this->actingAs($manager)
            ->post(route('automations.store'), $this->rulePayload(['name' => 'Manager rule']))
            ->assertRedirect();

        $rule = AutomationRule::withoutGlobalScopes()->firstWhere('name', 'Owner rule');

        $this->actingAs($sales)->get(route('automations.index'))->assertOk();
        $this->actingAs($sales)->post(route('automations.store'), $this->rulePayload(['name' => 'Sales rule']))->assertForbidden();
        $this->actingAs($sales)->patch(route('automations.update', $rule), $this->rulePayload(['name' => 'Nope']))->assertForbidden();
        $this->actingAs($viewer)->delete(route('automations.destroy', $rule))->assertForbidden();
    }

    public function test_rule_can_be_edited_paused_resumed_deleted_and_logged(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $rule = $this->automationRule($tenant, $user);

        $this->actingAs($user)
            ->patch(route('automations.update', $rule), $this->rulePayload(['name' => 'Regra atualizada', 'inactivity_days' => 9]))
            ->assertRedirect(route('automations.show', $rule));

        $this->assertDatabaseHas('automation_rules', [
            'id' => $rule->id,
            'name' => 'Regra atualizada',
            'inactivity_days' => 9,
        ]);

        $this->actingAs($user)->patch(route('automations.pause', $rule))->assertRedirect();
        $this->assertDatabaseHas('automation_rules', ['id' => $rule->id, 'active' => false]);

        $this->actingAs($user)->patch(route('automations.resume', $rule))->assertRedirect();
        $this->assertDatabaseHas('automation_rules', ['id' => $rule->id, 'active' => true, 'paused_at' => null]);

        $this->actingAs($user)->delete(route('automations.destroy', $rule))->assertRedirect(route('automations.index'));
        $this->assertSoftDeleted('automation_rules', ['id' => $rule->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'automation_rule.updated', 'tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'automation_rule.paused', 'tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'automation_rule.resumed', 'tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'automation_rule.deleted', 'tenant_id' => $tenant->id]);
    }

    public function test_command_creates_activity_for_inactive_deal_and_notification(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $deal = $this->deal($tenant, $user, [
            'priority' => Deal::PRIORITY_URGENT,
            'last_activity_at' => now()->subDays(8),
        ]);
        $rule = $this->automationRule($tenant, $user, [
            'inactivity_days' => 5,
            'action_payload' => [
                'activity_type' => CalendarEvent::TYPE_TASK,
                'activity_title_template' => 'Rever {deal_title}',
                'activity_description_template' => 'Parado há {inactivity_days} dias.',
                'due_in_days' => 1,
                'priority' => 'inherit',
            ],
            'notify_owner' => true,
        ]);

        $this->artisan('automations:run')->assertSuccessful();

        $event = CalendarEvent::withoutGlobalScopes()->firstWhere('deal_id', $deal->id);
        $this->assertNotNull($event);
        $this->assertSame($deal->id, $event->eventable_id);
        $this->assertSame(Deal::class, $event->eventable_type);
        $this->assertSame($user->id, $event->owner_id);
        $this->assertSame(Deal::PRIORITY_URGENT, $event->priority);
        $this->assertSame('Rever '.$deal->title, $event->title);

        $this->assertDatabaseHas('automation_runs', [
            'automation_rule_id' => $rule->id,
            'deal_id' => $deal->id,
            'calendar_event_id' => $event->id,
            'status' => AutomationRun::STATUS_SUCCESS,
        ]);
        $this->assertDatabaseHas('internal_notifications', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'notifiable_id' => $event->id,
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'automation_run.success', 'tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'internal_notification.created', 'tenant_id' => $tenant->id]);
    }

    public function test_command_ignores_won_lost_and_recent_deals(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $won = $this->stage($tenant, DealStage::SLUG_WON);
        $lost = $this->stage($tenant, DealStage::SLUG_LOST);

        $this->deal($tenant, $user, ['deal_stage_id' => $won->id, 'stage' => $won->slug, 'last_activity_at' => now()->subDays(20)]);
        $this->deal($tenant, $user, ['deal_stage_id' => $lost->id, 'stage' => $lost->slug, 'last_activity_at' => now()->subDays(20)]);
        $this->deal($tenant, $user, ['last_activity_at' => now()->subDay()]);
        $this->automationRule($tenant, $user, ['inactivity_days' => 5]);

        $this->artisan('automations:run')->assertSuccessful();

        $this->assertDatabaseCount('calendar_events', 0);
        $this->assertDatabaseCount('automation_runs', 0);
    }

    public function test_command_does_not_duplicate_activity_for_same_rule_and_deal(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $deal = $this->deal($tenant, $user, ['last_activity_at' => now()->subDays(8)]);
        $this->automationRule($tenant, $user, ['inactivity_days' => 5]);

        $this->artisan('automations:run')->assertSuccessful();
        $deal->forceFill(['last_activity_at' => now()->subDays(8)])->save();
        $this->artisan('automations:run')->assertSuccessful();

        $this->assertSame(1, CalendarEvent::withoutGlobalScopes()->where('deal_id', $deal->id)->count());
        $this->assertSame(1, AutomationRun::withoutGlobalScopes()->where('deal_id', $deal->id)->where('status', AutomationRun::STATUS_SUCCESS)->count());
    }

    public function test_command_can_skip_notification_and_respects_tenant_isolation(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        [$otherUser, $otherTenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $visible = $this->deal($tenant, $user, ['last_activity_at' => now()->subDays(8)]);
        $hidden = $this->deal($otherTenant, $otherUser, ['last_activity_at' => now()->subDays(8)]);
        $this->automationRule($tenant, $user, ['inactivity_days' => 5, 'notify_owner' => false]);

        $this->artisan('automations:run')->assertSuccessful();

        $this->assertDatabaseHas('calendar_events', ['deal_id' => $visible->id, 'tenant_id' => $tenant->id]);
        $this->assertDatabaseMissing('calendar_events', ['deal_id' => $hidden->id, 'tenant_id' => $otherTenant->id]);
        $this->assertDatabaseMissing('internal_notifications', ['tenant_id' => $tenant->id]);
    }

    public function test_pages_index_and_show_load(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $rule = $this->automationRule($tenant, $user);

        $this->actingAs($user)
            ->get(route('automations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('automations/Index')
                ->has('automations.data', 1)
                ->where('automations.data.0.id', $rule->id)
                ->etc());

        $this->actingAs($user)
            ->get(route('automations.show', $rule))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('automations/Show')
                ->where('automation.id', $rule->id)
                ->etc());
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function userWithTenant(string $role): array
    {
        $tenant = Tenant::factory()->create();
        DealStage::ensureDefaultStages($tenant);
        $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
        $user->tenants()->attach($tenant->id, ['role' => $role]);

        return [$user->refresh(), $tenant];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function automationRule(Tenant $tenant, User $creator, array $attributes = []): AutomationRule
    {
        return AutomationRule::create([
            'tenant_id' => $tenant->id,
            'name' => 'Negócios sem atividade',
            'trigger_type' => AutomationRule::TRIGGER_DEAL_INACTIVITY,
            'inactivity_days' => 5,
            'action_type' => AutomationRule::ACTION_CREATE_CALENDAR_ACTIVITY,
            'action_payload' => [
                'activity_type' => CalendarEvent::TYPE_TASK,
                'activity_title_template' => 'Follow-up automático: {deal_title}',
                'activity_description_template' => 'Sem atividade há {inactivity_days} dias.',
                'due_in_days' => 1,
                'priority' => 'inherit',
            ],
            'notify_owner' => true,
            'active' => true,
            'created_by' => $creator->id,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function deal(Tenant $tenant, User $owner, array $attributes = []): Deal
    {
        $stageId = $attributes['deal_stage_id'] ?? $this->stage($tenant, DealStage::SLUG_LEAD)->id;
        $stageSlug = $attributes['stage'] ?? DealStage::withoutGlobalScopes()->find($stageId)?->slug ?? DealStage::SLUG_LEAD;
        $entity = Entity::factory()->create(['tenant_id' => $tenant->id]);

        return Deal::factory()->create([
            'tenant_id' => $tenant->id,
            'entity_id' => $entity->id,
            'owner_id' => $owner->id,
            'deal_stage_id' => $stageId,
            'stage' => $stageSlug,
            'created_at' => now()->subDays(12),
            ...$attributes,
        ]);
    }

    private function stage(Tenant $tenant, string $slug): DealStage
    {
        return DealStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function rulePayload(array $overrides = []): array
    {
        return [
            'name' => 'Regra de automação',
            'description' => 'Regra para negócios sem atividade.',
            'trigger_type' => AutomationRule::TRIGGER_DEAL_INACTIVITY,
            'inactivity_days' => 5,
            'action_type' => AutomationRule::ACTION_CREATE_CALENDAR_ACTIVITY,
            'action_payload' => [
                'activity_type' => CalendarEvent::TYPE_TASK,
                'activity_title_template' => 'Follow-up automático: {deal_title}',
                'activity_description_template' => 'Sem atividade há {inactivity_days} dias.',
                'due_in_days' => 1,
                'priority' => 'inherit',
            ],
            'notify_owner' => true,
            'active' => true,
            ...$overrides,
        ];
    }
}
