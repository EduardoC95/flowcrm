<?php

namespace Tests\Feature;

use App\Models\AISuggestion;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\DealProposal;
use App\Models\DealStage;
use App\Models\Entity;
use App\Models\InternalNotification;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\CommercialAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommercialAgentSuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_suggestions_for_commercial_risks_and_avoids_duplicates(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $leadStage = $this->stage($tenant, DealStage::SLUG_LEAD);
        $negotiationStage = $this->stage($tenant, DealStage::SLUG_NEGOTIATION);
        $wonStage = $this->stage($tenant, DealStage::SLUG_WON);

        $inactiveDeal = $this->dealForTenant($tenant, $user, [
            'title' => 'Inactive deal',
            'deal_stage_id' => $leadStage->id,
            'stage' => $leadStage->slug,
            'last_activity_at' => now()->subDays(10),
            'value' => 1000,
        ]);
        $highValueDeal = $this->dealForTenant($tenant, $user, [
            'title' => 'High value stalled',
            'deal_stage_id' => $negotiationStage->id,
            'stage' => $negotiationStage->slug,
            'last_activity_at' => now()->subDays(8),
            'value' => 15000,
        ]);
        $closingDeal = $this->dealForTenant($tenant, $user, [
            'title' => 'Closing soon',
            'deal_stage_id' => $negotiationStage->id,
            'stage' => $negotiationStage->slug,
            'expected_close_date' => now()->addDays(3),
            'last_activity_at' => now(),
            'value' => 1000,
        ]);
        $proposalDeal = $this->dealForTenant($tenant, $user, [
            'title' => 'Proposal waiting',
            'deal_stage_id' => $negotiationStage->id,
            'stage' => $negotiationStage->slug,
            'last_activity_at' => now()->subDays(6),
        ]);
        DealProposal::create([
            'tenant_id' => $tenant->id,
            'deal_id' => $proposalDeal->id,
            'uploaded_by' => $user->id,
            'original_name' => 'proposal.pdf',
            'path' => 'deal-proposals/proposal.pdf',
            'mime_type' => 'application/pdf',
            'size' => 123,
            'status' => DealProposal::STATUS_SENT,
            'sent_at' => now()->subDays(5),
            'sent_by' => $user->id,
        ]);
        $this->dealForTenant($tenant, $user, [
            'title' => 'Won should be ignored',
            'deal_stage_id' => $wonStage->id,
            'stage' => $wonStage->slug,
            'last_activity_at' => now()->subDays(20),
        ]);
        AISuggestion::withoutGlobalScopes()->delete();

        $this->artisan('ai:analyze-commercial')->assertSuccessful();

        $this->assertDatabaseHas('ai_suggestions', ['deal_id' => $inactiveDeal->id, 'type' => AISuggestion::TYPE_NO_ACTIVITY]);
        $this->assertDatabaseHas('ai_suggestions', ['deal_id' => $highValueDeal->id, 'type' => AISuggestion::TYPE_HIGH_VALUE_STALLED]);
        $this->assertDatabaseHas('ai_suggestions', ['deal_id' => $closingDeal->id, 'type' => AISuggestion::TYPE_CLOSING_DATE_NEAR]);
        $this->assertDatabaseHas('ai_suggestions', ['deal_id' => $proposalDeal->id, 'type' => AISuggestion::TYPE_PROPOSAL_SENT_NO_FOLLOWUP]);
        $this->assertDatabaseMissing('ai_suggestions', ['title' => 'Won should be ignored']);
        $firstCount = AISuggestion::withoutGlobalScopes()->count();

        $this->artisan('ai:analyze-commercial')->assertSuccessful();

        $this->assertSame($firstCount, AISuggestion::withoutGlobalScopes()->count());
    }

    public function test_suggestion_lifecycle_and_conversion_to_activity(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_MANAGER);
        $deal = $this->dealForTenant($tenant, $user);
        $suggestion = $this->suggestionFor($tenant, $user, $deal);

        $this->actingAs($user)->patch(route('ai-suggestions.accept', $suggestion))->assertRedirect();
        $this->assertDatabaseHas('ai_suggestions', ['id' => $suggestion->id, 'status' => AISuggestion::STATUS_ACCEPTED, 'accepted_by' => $user->id]);

        $suggestion->update(['status' => AISuggestion::STATUS_PENDING, 'accepted_at' => null, 'accepted_by' => null]);
        $this->actingAs($user)->patch(route('ai-suggestions.postpone', $suggestion), [
            'postponed_until' => now()->addDays(2)->toDateTimeString(),
        ])->assertRedirect();
        $this->assertDatabaseHas('ai_suggestions', ['id' => $suggestion->id, 'status' => AISuggestion::STATUS_POSTPONED]);

        $this->actingAs($user)->patch(route('ai-suggestions.archive', $suggestion))->assertRedirect();
        $this->assertDatabaseHas('ai_suggestions', ['id' => $suggestion->id, 'status' => AISuggestion::STATUS_ARCHIVED]);

        $suggestion->update(['status' => AISuggestion::STATUS_PENDING]);
        $this->actingAs($user)->patch(route('ai-suggestions.ignore', $suggestion))->assertRedirect();
        $this->assertDatabaseHas('ai_suggestions', ['id' => $suggestion->id, 'status' => AISuggestion::STATUS_IGNORED]);

        $suggestion->update(['status' => AISuggestion::STATUS_PENDING, 'ignored_at' => null, 'ignored_by' => null]);
        $this->actingAs($user)->post(route('ai-suggestions.convert-to-activity', $suggestion), [
            'title' => 'Follow-up from suggestion',
            'start_at' => now()->addDay()->toDateTimeString(),
        ])->assertRedirect();

        $event = CalendarEvent::firstWhere('title', 'Follow-up from suggestion');
        $this->assertNotNull($event);
        $this->assertSame($deal->id, $event->deal_id);
        $this->assertDatabaseHas('ai_suggestions', ['id' => $suggestion->id, 'converted_calendar_event_id' => $event->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ai_suggestion.converted_to_activity']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'calendar_event.created', 'subject_id' => $event->id]);
        $this->assertTrue(InternalNotification::where('user_id', $user->id)->exists());
    }

    public function test_policy_visibility_and_viewer_restrictions(): void
    {
        [$owner, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $sales = User::factory()->create(['current_tenant_id' => $tenant->id]);
        $sales->tenants()->attach($tenant->id, ['role' => Tenant::ROLE_SALES]);
        $viewer = User::factory()->create(['current_tenant_id' => $tenant->id]);
        $viewer->tenants()->attach($tenant->id, ['role' => Tenant::ROLE_VIEWER]);
        $ownerDeal = $this->dealForTenant($tenant, $owner);
        $salesDeal = $this->dealForTenant($tenant, $sales);
        $ownerSuggestion = $this->suggestionFor($tenant, $owner, $ownerDeal, ['title' => 'Owner suggestion']);
        $salesSuggestion = $this->suggestionFor($tenant, $sales, $salesDeal, ['title' => 'Sales suggestion']);

        $this->actingAs($owner)
            ->get(route('ai-suggestions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('suggestions.data', 2)->etc());

        $this->actingAs($sales)
            ->get(route('ai-suggestions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('suggestions.data', 1)->where('suggestions.data.0.id', $salesSuggestion->id)->etc());

        $this->actingAs($sales)->get(route('ai-suggestions.show', $ownerSuggestion))->assertForbidden();
        $this->actingAs($viewer)->post(route('ai-suggestions.convert-to-activity', $ownerSuggestion))->assertForbidden();
    }

    public function test_score_adjusts_with_user_history_and_new_lead_generates_suggestion(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $deal = $this->dealForTenant($tenant, $user);
        AISuggestion::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'deal_id' => $deal->id,
            'type' => AISuggestion::TYPE_NO_ACTIVITY,
            'title' => 'Ignored',
            'reason' => 'Ignored',
            'suggested_action' => 'Ignored',
            'status' => AISuggestion::STATUS_IGNORED,
            'ignored_at' => now(),
            'ignored_by' => $user->id,
            'score' => 70,
        ]);

        $service = app(CommercialAgentService::class);
        $this->assertLessThan(70, $service->adjustScoreForUserHistory($user, AISuggestion::TYPE_NO_ACTIVITY, 70));

        Person::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Fresh Lead',
            'status' => Person::STATUS_LEAD,
        ]);

        $this->assertDatabaseHas('ai_suggestions', [
            'tenant_id' => $tenant->id,
            'type' => AISuggestion::TYPE_NEW_LEAD_NEEDS_FIRST_CONTACT,
            'person_id' => Person::firstWhere('name', 'Fresh Lead')->id,
        ]);
    }

    public function test_chat_crm_returns_commercial_suggestions(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $deal = $this->dealForTenant($tenant, $user, ['title' => 'Risky deal']);
        $suggestion = $this->suggestionFor($tenant, $user, $deal, ['title' => 'Contactar cliente hoje', 'score' => 90]);

        $this->actingAs($user)
            ->postJson(route('ai-chat.store'), ['message' => 'Que acoes comerciais devo fazer hoje?'])
            ->assertOk()
            ->assertJsonPath('result.intent.intent', 'commercial_suggestions')
            ->assertJsonFragment(['title' => $suggestion->title]);
    }

    public function test_tenant_isolation_for_suggestions(): void
    {
        [$user] = $this->userWithTenant(Tenant::ROLE_OWNER);
        [$otherUser, $otherTenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $otherDeal = $this->dealForTenant($otherTenant, $otherUser);
        $otherSuggestion = $this->suggestionFor($otherTenant, $otherUser, $otherDeal, ['title' => 'Hidden suggestion']);

        $this->actingAs($user)->get(route('ai-suggestions.show', $otherSuggestion))->assertNotFound();
        $this->actingAs($user)
            ->postJson(route('ai-chat.store'), ['message' => 'Mostra-me sugestoes de alto impacto'])
            ->assertOk()
            ->assertJsonMissing(['title' => 'Hidden suggestion']);
    }

    public function test_pages_load(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $deal = $this->dealForTenant($tenant, $user);
        $suggestion = $this->suggestionFor($tenant, $user, $deal);

        $this->actingAs($user)->get(route('ai-suggestions.index'))->assertOk();
        $this->actingAs($user)->get(route('ai-suggestions.show', $suggestion))->assertOk();
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

    private function stage(Tenant $tenant, string $slug): DealStage
    {
        return DealStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * @param  array<string,mixed>  $attributes
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
     * @param  array<string,mixed>  $attributes
     */
    private function suggestionFor(Tenant $tenant, User $user, Deal $deal, array $attributes = []): AISuggestion
    {
        return AISuggestion::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'deal_id' => $deal->id,
            'person_id' => $deal->person_id,
            'entity_id' => $deal->entity_id,
            'type' => AISuggestion::TYPE_NO_ACTIVITY,
            'title' => 'Retomar contacto',
            'reason' => 'Este negocio nao tem atividade recente.',
            'suggested_action' => 'Criar tarefa de follow-up',
            'suggested_due_at' => now()->addDay(),
            'priority' => AISuggestion::PRIORITY_HIGH,
            'status' => AISuggestion::STATUS_PENDING,
            'source' => 'test',
            'score' => 80,
            ...$attributes,
        ]);
    }
}
