<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\DealFollowUp;
use App\Models\DealFollowUpEmail;
use App\Models\DealNote;
use App\Models\DealProduct;
use App\Models\DealProposal;
use App\Models\DealStage;
use App\Models\Entity;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_deal_timeline_aggregates_all_sources_and_sorts_desc(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $deal = $this->deal($tenant, $user);

        $this->seedTimelineSources($deal, $user, $tenant);

        $response = $this->actingAs($user)
            ->getJson(route('deals.timeline', $deal))
            ->assertOk();

        $types = collect($response->json('items'))->pluck('type');
        $dates = collect($response->json('items'))->pluck('occurred_at')->values();

        $this->assertTrue($types->contains('change'));
        $this->assertTrue($types->contains('activity'));
        $this->assertTrue($types->contains('note'));
        $this->assertTrue($types->contains('proposal'));
        $this->assertTrue($types->contains('follow_up'));
        $this->assertTrue($types->contains('product'));
        $this->assertSame($dates->sortDesc()->values()->all(), $dates->all());
    }

    public function test_timeline_respects_type_filter(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $deal = $this->deal($tenant, $user);
        $this->seedTimelineSources($deal, $user, $tenant);

        $response = $this->actingAs($user)
            ->getJson(route('deals.timeline', [$deal, 'type' => 'note']))
            ->assertOk();

        $this->assertNotEmpty($response->json('items'));
        $this->assertTrue(collect($response->json('items'))->every(fn (array $item) => $item['type'] === 'note'));
    }

    public function test_user_cannot_view_timeline_from_another_tenant(): void
    {
        [$user] = $this->userWithTenant(Tenant::ROLE_OWNER);
        [$otherUser, $otherTenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $otherDeal = $this->deal($otherTenant, $otherUser);

        $this->actingAs($user)
            ->getJson(route('deals.timeline', $otherDeal))
            ->assertNotFound();
    }

    public function test_quick_note_is_created_with_deal_context_and_log(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_SALES);
        $deal = $this->deal($tenant, $user);

        $this->actingAs($user)
            ->post(route('deals.quick-activities.store', $deal), [
                'type' => 'note',
                'body' => 'Cliente pediu revisão de valores.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('deal_notes', [
            'tenant_id' => $tenant->id,
            'deal_id' => $deal->id,
            'user_id' => $user->id,
            'body' => 'Cliente pediu revisão de valores.',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $tenant->id,
            'subject_id' => $deal->id,
            'action' => 'deal_note.created',
        ]);
        $this->assertNotNull($deal->refresh()->last_activity_at);
    }

    public function test_quick_activities_create_calendar_events_for_all_activity_types(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_MANAGER);
        $deal = $this->deal($tenant, $user);

        foreach (['task', 'call', 'meeting', 'reminder'] as $type) {
            $this->actingAs($user)
                ->post(route('deals.quick-activities.store', $deal), [
                    'type' => $type,
                    'title' => ucfirst($type).' demo',
                    'body' => 'Descrição rápida.',
                    'start_at' => '2026-06-10 10:00:00',
                    'end_at' => '2026-06-10 11:00:00',
                    'owner_id' => $user->id,
                    'priority' => Deal::PRIORITY_HIGH,
                ])
                ->assertRedirect();
        }

        foreach (['task', 'call', 'meeting', 'reminder'] as $type) {
            $this->assertDatabaseHas('calendar_events', [
                'tenant_id' => $tenant->id,
                'deal_id' => $deal->id,
                'eventable_type' => Deal::class,
                'eventable_id' => $deal->id,
                'owner_id' => $user->id,
                'type' => $type,
                'status' => CalendarEvent::STATUS_PENDING,
            ]);
        }

        $this->assertSame(4, ActivityLog::where('action', 'quick_activity.created')->where('subject_id', $deal->id)->count());
    }

    public function test_quick_activity_rejects_owner_outside_tenant(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        [$otherUser] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $deal = $this->deal($tenant, $user);

        $this->actingAs($user)
            ->from(route('deals.show', $deal))
            ->post(route('deals.quick-activities.store', $deal), [
                'type' => 'call',
                'title' => 'Chamada',
                'start_at' => '2026-06-10 10:00:00',
                'owner_id' => $otherUser->id,
            ])
            ->assertRedirect(route('deals.show', $deal))
            ->assertSessionHasErrors('owner_id');
    }

    public function test_viewer_cannot_create_quick_activity(): void
    {
        [$viewer, $tenant] = $this->userWithTenant(Tenant::ROLE_VIEWER);
        $owner = User::factory()->create(['current_tenant_id' => $tenant->id]);
        $owner->tenants()->attach($tenant->id, ['role' => Tenant::ROLE_OWNER]);
        $deal = $this->deal($tenant, $owner);

        $this->actingAs($viewer)
            ->post(route('deals.quick-activities.store', $deal), [
                'type' => 'note',
                'body' => 'Sem permissão.',
            ])
            ->assertForbidden();
    }

    public function test_notes_respect_tenant_isolation(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        [, $otherTenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $deal = $this->deal($tenant, $user);
        $otherDeal = $this->deal($otherTenant, User::factory()->create(['current_tenant_id' => $otherTenant->id]));
        DealNote::create([
            'tenant_id' => $otherTenant->id,
            'deal_id' => $otherDeal->id,
            'user_id' => $user->id,
            'body' => 'Nota de outro tenant.',
        ]);
        DealNote::create([
            'tenant_id' => $tenant->id,
            'deal_id' => $deal->id,
            'user_id' => $user->id,
            'body' => 'Nota visível.',
        ]);

        $items = $this->actingAs($user)->getJson(route('deals.timeline', $deal))->json('items');

        $this->assertTrue(collect($items)->contains(fn (array $item) => str_contains($item['description'] ?? '', 'Nota visível')));
        $this->assertFalse(collect($items)->contains(fn (array $item) => str_contains($item['description'] ?? '', 'outro tenant')));
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

    private function deal(Tenant $tenant, User $owner): Deal
    {
        $entity = Entity::factory()->create(['tenant_id' => $tenant->id]);
        $stage = DealStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', DealStage::SLUG_LEAD)
            ->firstOrFail();

        if (! $owner->tenants()->where('tenant_id', $tenant->id)->exists()) {
            $owner->tenants()->attach($tenant->id, ['role' => Tenant::ROLE_OWNER]);
        }

        return Deal::factory()->create([
            'tenant_id' => $tenant->id,
            'entity_id' => $entity->id,
            'owner_id' => $owner->id,
            'deal_stage_id' => $stage->id,
            'stage' => $stage->slug,
        ]);
    }

    private function seedTimelineSources(Deal $deal, User $user, Tenant $tenant): void
    {
        ActivityLog::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'action' => 'deal.updated',
            'module' => 'deals',
            'subject_type' => Deal::class,
            'subject_id' => $deal->id,
            'description' => 'Deal changed.',
            'created_at' => '2026-06-06 10:00:00',
        ]);

        CalendarEvent::create([
            'tenant_id' => $tenant->id,
            'deal_id' => $deal->id,
            'eventable_type' => Deal::class,
            'eventable_id' => $deal->id,
            'owner_id' => $user->id,
            'title' => 'Chamada de follow-up',
            'type' => CalendarEvent::TYPE_CALL,
            'status' => CalendarEvent::STATUS_PENDING,
            'start_at' => '2026-06-05 10:00:00',
            'starts_at' => '2026-06-05 10:00:00',
        ]);

        DealNote::create([
            'tenant_id' => $tenant->id,
            'deal_id' => $deal->id,
            'user_id' => $user->id,
            'body' => 'Nota importante.',
            'created_at' => '2026-06-04 10:00:00',
        ]);

        DealProposal::create([
            'tenant_id' => $tenant->id,
            'deal_id' => $deal->id,
            'uploaded_by' => $user->id,
            'original_name' => 'proposta.pdf',
            'path' => 'deal-proposals/proposta.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'status' => DealProposal::STATUS_SENT,
            'sent_at' => '2026-06-03 10:00:00',
            'sent_by' => $user->id,
            'recipient_email' => 'cliente@example.test',
            'email_subject' => 'Proposta',
            'email_body' => 'Segue proposta.',
        ]);

        $followUp = DealFollowUp::create([
            'tenant_id' => $tenant->id,
            'deal_id' => $deal->id,
            'status' => DealFollowUp::STATUS_ACTIVE,
        ]);

        DealFollowUpEmail::create([
            'tenant_id' => $tenant->id,
            'deal_id' => $deal->id,
            'deal_follow_up_id' => $followUp->id,
            'recipient_email' => 'cliente@example.test',
            'subject' => 'Follow-up',
            'body' => 'Precisa de ajuda?',
            'sent_at' => '2026-06-02 10:00:00',
        ]);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Licença',
            'unit_price' => 100,
            'active' => true,
        ]);

        DealProduct::create([
            'tenant_id' => $tenant->id,
            'deal_id' => $deal->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
            'total' => 200,
            'created_at' => '2026-06-01 10:00:00',
        ]);
    }
}
