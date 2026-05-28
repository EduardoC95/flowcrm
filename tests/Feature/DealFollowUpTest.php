<?php

namespace Tests\Feature;

use App\Mail\DealFollowUpMail;
use App\Models\Deal;
use App\Models\DealFollowUp;
use App\Models\DealFollowUpEmail;
use App\Models\DealStage;
use App\Models\Entity;
use App\Models\FollowUpTemplate;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DealFollowUpTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_moving_deal_to_follow_up_starts_active_cycle_without_duplicates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00'));
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $deal = $this->dealForTenant($tenant, $user, DealStage::SLUG_LEAD);
        $followUpStage = $this->stage($tenant, DealStage::SLUG_FOLLOW_UP);

        $this->actingAs($user)
            ->patchJson(route('deals.move-stage', $deal), ['deal_stage_id' => $followUpStage->id])
            ->assertOk();

        $this->actingAs($user)
            ->patchJson(route('deals.move-stage', $deal->refresh()), ['deal_stage_id' => $followUpStage->id])
            ->assertOk();

        $this->assertSame(1, DealFollowUp::where('deal_id', $deal->id)->where('status', DealFollowUp::STATUS_ACTIVE)->count());
        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $tenant->id,
            'subject_id' => $deal->id,
            'action' => 'follow_up.started',
        ]);
    }

    public function test_leaving_follow_up_cancels_active_cycle(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $deal = $this->dealForTenant($tenant, $user, DealStage::SLUG_FOLLOW_UP);
        $followUp = $this->activeFollowUp($deal);
        $proposalStage = $this->stage($tenant, DealStage::SLUG_PROPOSAL);

        $this->actingAs($user)
            ->patchJson(route('deals.move-stage', $deal), ['deal_stage_id' => $proposalStage->id])
            ->assertOk();

        $this->assertDatabaseHas('deal_follow_ups', [
            'id' => $followUp->id,
            'status' => DealFollowUp::STATUS_CANCELLED,
            'cancellation_reason' => 'Negócio saiu do estado Follow Up',
        ]);
    }

    public function test_user_can_cancel_follow_up_and_mark_client_replied(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_MANAGER);
        $deal = $this->dealForTenant($tenant, $user, DealStage::SLUG_FOLLOW_UP);
        $cancelled = $this->activeFollowUp($deal);

        $this->actingAs($user)
            ->patch(route('deals.follow-up.cancel', $deal), ['cancellation_reason' => 'Cliente pediu pausa'])
            ->assertRedirect();

        $this->assertDatabaseHas('deal_follow_ups', [
            'id' => $cancelled->id,
            'status' => DealFollowUp::STATUS_CANCELLED,
            'cancellation_reason' => 'Cliente pediu pausa',
        ]);

        $replied = $this->activeFollowUp($deal);

        $this->actingAs($user)
            ->patch(route('deals.follow-up.client-replied', $deal))
            ->assertRedirect();

        $this->assertDatabaseHas('deal_follow_ups', [
            'id' => $replied->id,
            'status' => DealFollowUp::STATUS_REPLIED,
            'next_send_at' => null,
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'follow_up.client_replied', 'subject_id' => $deal->id]);
    }

    public function test_send_due_command_sends_email_records_history_and_schedules_next_business_slot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-29 17:00:00'));
        Mail::fake();
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $template = $this->template();
        $deal = $this->dealForTenant($tenant, $user, DealStage::SLUG_FOLLOW_UP, personEmail: 'client@example.test');
        $followUp = $this->activeFollowUp($deal, ['next_send_at' => now()->subMinute()]);

        $this->artisan('followups:send-due')->assertSuccessful();

        Mail::assertSent(DealFollowUpMail::class, 1);
        $this->assertDatabaseHas('deal_follow_up_emails', [
            'deal_follow_up_id' => $followUp->id,
            'follow_up_template_id' => $template->id,
            'recipient_email' => 'client@example.test',
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'follow_up.email_sent', 'subject_id' => $deal->id]);

        $this->assertSame('2026-06-01 09:00:00', $followUp->refresh()->next_send_at->toDateTimeString());
        $this->assertSame(1, $followUp->sent_count);
    }

    public function test_send_due_command_does_not_send_outside_business_hours_or_duplicate(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-26 20:00:00'));
        Mail::fake();
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $this->template();
        $deal = $this->dealForTenant($tenant, $user, DealStage::SLUG_FOLLOW_UP, personEmail: 'client@example.test');
        $followUp = $this->activeFollowUp($deal, ['next_send_at' => now()->subMinute()]);

        $this->artisan('followups:send-due')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(0, DealFollowUpEmail::count());

        Carbon::setTestNow(Carbon::parse('2026-05-27 10:00:00'));
        $followUp->update(['next_send_at' => now()->subMinute()]);

        $this->artisan('followups:send-due')->assertSuccessful();
        $this->artisan('followups:send-due')->assertSuccessful();

        Mail::assertSent(DealFollowUpMail::class, 1);
        $this->assertSame(1, DealFollowUpEmail::count());
    }

    public function test_command_skips_deal_outside_follow_up_stage_and_missing_recipient_is_logged(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00'));
        Mail::fake();
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $this->template();

        $leadDeal = $this->dealForTenant($tenant, $user, DealStage::SLUG_LEAD, personEmail: 'client@example.test');
        $this->activeFollowUp($leadDeal, ['next_send_at' => now()->subMinute()]);

        $missingRecipientDeal = $this->dealForTenant($tenant, $user, DealStage::SLUG_FOLLOW_UP, personEmail: null, entityEmail: null);
        $this->activeFollowUp($missingRecipientDeal, ['next_send_at' => now()->subMinute()]);

        $this->artisan('followups:send-due')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertDatabaseHas('deal_follow_ups', [
            'deal_id' => $leadDeal->id,
            'status' => DealFollowUp::STATUS_CANCELLED,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'subject_id' => $missingRecipientDeal->id,
            'action' => 'follow_up.skipped_missing_recipient',
        ]);
    }

    public function test_template_selection_avoids_repeating_previous_template_when_possible(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00'));
        Mail::fake();
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $first = $this->template('Template 1', 1);
        $second = $this->template('Template 2', 2);
        $deal = $this->dealForTenant($tenant, $user, DealStage::SLUG_FOLLOW_UP, personEmail: 'client@example.test');
        $followUp = $this->activeFollowUp($deal, [
            'next_send_at' => now()->subMinute(),
            'last_sent_at' => now()->subDays(2),
            'sent_count' => 1,
        ]);
        DealFollowUpEmail::create([
            'tenant_id' => $tenant->id,
            'deal_id' => $deal->id,
            'deal_follow_up_id' => $followUp->id,
            'follow_up_template_id' => $first->id,
            'recipient_email' => 'client@example.test',
            'subject' => 'Previous',
            'body' => 'Previous',
            'sent_at' => now()->subDays(2),
        ]);

        $this->artisan('followups:send-due')->assertSuccessful();

        $this->assertDatabaseHas('deal_follow_up_emails', [
            'deal_follow_up_id' => $followUp->id,
            'follow_up_template_id' => $second->id,
        ]);
    }

    public function test_recipient_uses_entity_email_when_person_has_no_email(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00'));
        Mail::fake();
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $this->template();
        $deal = $this->dealForTenant($tenant, $user, DealStage::SLUG_FOLLOW_UP, personEmail: null, entityEmail: 'entity@example.test');
        $this->activeFollowUp($deal, ['next_send_at' => now()->subMinute()]);

        $this->artisan('followups:send-due')->assertSuccessful();

        $this->assertDatabaseHas('deal_follow_up_emails', [
            'deal_id' => $deal->id,
            'recipient_email' => 'entity@example.test',
        ]);
    }

    public function test_viewer_cannot_cancel_or_mark_client_replied(): void
    {
        [$viewer, $tenant] = $this->userWithTenant(Tenant::ROLE_VIEWER);
        $owner = User::factory()->create(['current_tenant_id' => $tenant->id]);
        $owner->tenants()->attach($tenant->id, ['role' => Tenant::ROLE_OWNER]);
        $deal = $this->dealForTenant($tenant, $owner, DealStage::SLUG_FOLLOW_UP);
        $this->activeFollowUp($deal);

        $this->actingAs($viewer)->patch(route('deals.follow-up.cancel', $deal))->assertForbidden();
        $this->actingAs($viewer)->patch(route('deals.follow-up.client-replied', $deal))->assertForbidden();
    }

    public function test_cross_tenant_follow_up_actions_are_blocked(): void
    {
        [$user] = $this->userWithTenant(Tenant::ROLE_OWNER);
        [$otherUser, $otherTenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $otherDeal = $this->dealForTenant($otherTenant, $otherUser, DealStage::SLUG_FOLLOW_UP);
        $this->activeFollowUp($otherDeal);

        $this->actingAs($user)
            ->patch(route('deals.follow-up.cancel', $otherDeal))
            ->assertNotFound();
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

    private function dealForTenant(
        Tenant $tenant,
        User $owner,
        string $stageSlug,
        ?string $personEmail = 'client@example.test',
        ?string $entityEmail = 'entity@example.test',
    ): Deal {
        $entity = Entity::factory()->create(['tenant_id' => $tenant->id, 'email' => $entityEmail]);
        $person = Person::factory()->forEntity($entity)->create(['tenant_id' => $tenant->id, 'email' => $personEmail]);
        $stage = $this->stage($tenant, $stageSlug);

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
     * @param  array<string, mixed>  $attributes
     */
    private function activeFollowUp(Deal $deal, array $attributes = []): DealFollowUp
    {
        return DealFollowUp::create([
            'tenant_id' => $deal->tenant_id,
            'deal_id' => $deal->id,
            'status' => DealFollowUp::STATUS_ACTIVE,
            'next_send_at' => now()->addHour(),
            ...$attributes,
        ]);
    }

    private function template(string $name = 'Follow-up template', int $position = 1): FollowUpTemplate
    {
        return FollowUpTemplate::create([
            'name' => $name,
            'subject' => 'Proposta - {deal_title}',
            'body' => "Olá {client_name},\n\nPrecisa de ajuda com {deal_title}?\n\nObrigado,\n{user_name}",
            'active' => true,
            'position' => $position,
        ]);
    }
}
