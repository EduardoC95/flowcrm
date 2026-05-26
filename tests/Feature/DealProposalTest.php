<?php

namespace Tests\Feature;

use App\Mail\DealProposalMail;
use App\Models\Deal;
use App\Models\DealProposal;
use App\Models\DealStage;
use App\Models\Entity;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DealProposalTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_proposal_to_own_tenant_deal(): void
    {
        Storage::fake('local');
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $deal = $this->dealForTenant($tenant, $user);

        $this->actingAs($user)
            ->post(route('deals.proposals.store', $deal), [
                'proposal' => UploadedFile::fake()->create('proposal.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $proposal = DealProposal::firstOrFail();

        $this->assertSame($tenant->id, $proposal->tenant_id);
        $this->assertSame($deal->id, $proposal->deal_id);
        $this->assertSame(DealProposal::STATUS_DRAFT, $proposal->status);
        Storage::disk('local')->assertExists($proposal->path);
        $this->assertStringStartsWith('deal-proposals/', $proposal->path);
        $this->assertStringNotContainsString('public', $proposal->path);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'deal_proposal.uploaded',
            'subject_type' => DealProposal::class,
            'subject_id' => $proposal->id,
        ]);
    }

    public function test_upload_rejects_invalid_mime_and_oversized_files(): void
    {
        Storage::fake('local');
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $deal = $this->dealForTenant($tenant, $user);

        $this->actingAs($user)
            ->from(route('deals.show', $deal))
            ->post(route('deals.proposals.store', $deal), [
                'proposal' => UploadedFile::fake()->create('proposal.exe', 10, 'application/x-msdownload'),
            ])
            ->assertRedirect(route('deals.show', $deal))
            ->assertSessionHasErrors('proposal');

        $this->actingAs($user)
            ->from(route('deals.show', $deal))
            ->post(route('deals.proposals.store', $deal), [
                'proposal' => UploadedFile::fake()->create('proposal.pdf', 11000, 'application/pdf'),
            ])
            ->assertRedirect(route('deals.show', $deal))
            ->assertSessionHasErrors('proposal');
    }

    public function test_authorized_user_can_download_proposal_but_other_tenant_cannot(): void
    {
        Storage::fake('local');
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_VIEWER);
        $owner = User::factory()->create(['current_tenant_id' => $tenant->id]);
        $owner->tenants()->attach($tenant->id, ['role' => Tenant::ROLE_OWNER]);
        $deal = $this->dealForTenant($tenant, $owner);
        $proposal = $this->proposalForDeal($deal, $owner);
        [$otherUser, $otherTenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $otherDeal = $this->dealForTenant($otherTenant, $otherUser);
        $otherProposal = $this->proposalForDeal($otherDeal, $otherUser);

        $this->actingAs($user)
            ->get(route('deals.proposals.download', [$deal, $proposal]))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('deals.proposals.download', [$otherDeal, $otherProposal]))
            ->assertNotFound();
    }

    public function test_preview_suggests_person_email_then_entity_email(): void
    {
        Storage::fake('local');
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $entity = Entity::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Acme', 'email' => 'entity@example.test']);
        $person = Person::factory()->forEntity($entity)->create(['name' => 'Maria', 'email' => 'person@example.test']);
        $deal = $this->dealForTenant($tenant, $user, $entity, $person);
        $proposal = $this->proposalForDeal($deal, $user);

        $this->actingAs($user)
            ->getJson(route('deals.proposals.preview-email', [$deal, $proposal]))
            ->assertOk()
            ->assertJsonPath('recipient_email', 'person@example.test')
            ->assertJsonPath('email_subject', 'Proposta comercial - '.$deal->title);

        $person->update(['email' => null]);

        $this->actingAs($user)
            ->getJson(route('deals.proposals.preview-email', [$deal, $proposal]))
            ->assertOk()
            ->assertJsonPath('recipient_email', 'entity@example.test');
    }

    public function test_sending_proposal_sends_email_with_attachment_and_updates_history(): void
    {
        Storage::fake('local');
        Mail::fake();
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $deal = $this->dealForTenant($tenant, $user);
        $proposal = $this->proposalForDeal($deal, $user);

        $this->actingAs($user)
            ->post(route('deals.proposals.send', [$deal, $proposal]), [
                'recipient_email' => 'client@example.test',
                'email_subject' => 'Proposta comercial - '.$deal->title,
                'email_body' => 'Segue em anexo a proposta.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(DealProposalMail::class, function (DealProposalMail $mail) use ($proposal) {
            return $mail->proposal->is($proposal) && count($mail->attachments()) === 1;
        });

        $proposal->refresh();
        $this->assertSame(DealProposal::STATUS_SENT, $proposal->status);
        $this->assertNotNull($proposal->sent_at);
        $this->assertSame($user->id, $proposal->sent_by);
        $this->assertSame('client@example.test', $proposal->recipient_email);
        $this->assertSame('Proposta comercial - '.$deal->title, $proposal->email_subject);
        $this->assertSame('Segue em anexo a proposta.', $proposal->email_body);
        $this->assertNotNull($deal->fresh()->last_activity_at);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'deal_proposal.sent',
            'subject_type' => DealProposal::class,
            'subject_id' => $proposal->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'deal_proposal.sent',
            'subject_type' => Deal::class,
            'subject_id' => $deal->id,
        ]);
    }

    public function test_viewer_cannot_upload_send_or_remove_and_sales_can_manage(): void
    {
        Storage::fake('local');
        [$viewer, $tenant] = $this->userWithTenant(Tenant::ROLE_VIEWER);
        $owner = User::factory()->create(['current_tenant_id' => $tenant->id]);
        $owner->tenants()->attach($tenant->id, ['role' => Tenant::ROLE_OWNER]);
        $deal = $this->dealForTenant($tenant, $owner);
        $proposal = $this->proposalForDeal($deal, $owner);
        [$sales, $salesTenant] = $this->userWithTenant(Tenant::ROLE_SALES);
        $salesDeal = $this->dealForTenant($salesTenant, $sales);

        $this->actingAs($viewer)
            ->post(route('deals.proposals.store', $deal), [
                'proposal' => UploadedFile::fake()->create('proposal.pdf', 10, 'application/pdf'),
            ])
            ->assertForbidden();
        $this->actingAs($viewer)
            ->post(route('deals.proposals.send', [$deal, $proposal]), [
                'recipient_email' => 'client@example.test',
                'email_subject' => 'Subject',
                'email_body' => 'Body',
            ])
            ->assertForbidden();
        $this->actingAs($viewer)
            ->delete(route('deals.proposals.destroy', [$deal, $proposal]))
            ->assertForbidden();

        $this->actingAs($sales)
            ->post(route('deals.proposals.store', $salesDeal), [
                'proposal' => UploadedFile::fake()->create('proposal.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect();
    }

    public function test_soft_delete_proposal(): void
    {
        Storage::fake('local');
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_MANAGER);
        $deal = $this->dealForTenant($tenant, $user);
        $proposal = $this->proposalForDeal($deal, $user);

        $this->actingAs($user)
            ->delete(route('deals.proposals.destroy', [$deal, $proposal]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted('deal_proposals', ['id' => $proposal->id]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'deal_proposal.deleted',
            'subject_type' => DealProposal::class,
            'subject_id' => $proposal->id,
        ]);
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

    private function dealForTenant(Tenant $tenant, User $owner, ?Entity $entity = null, ?Person $person = null): Deal
    {
        $entity ??= Entity::factory()->create(['tenant_id' => $tenant->id]);
        $stage = DealStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', DealStage::SLUG_LEAD)
            ->firstOrFail();

        return Deal::factory()->create([
            'tenant_id' => $tenant->id,
            'entity_id' => $entity->id,
            'person_id' => $person?->id,
            'owner_id' => $owner->id,
            'deal_stage_id' => $stage->id,
            'stage' => $stage->slug,
            'title' => 'CRM rollout',
        ]);
    }

    private function proposalForDeal(Deal $deal, User $user): DealProposal
    {
        $path = 'deal-proposals/test-proposal.pdf';
        Storage::disk('local')->put($path, 'proposal');

        return DealProposal::create([
            'tenant_id' => $deal->tenant_id,
            'deal_id' => $deal->id,
            'uploaded_by' => $user->id,
            'original_name' => 'proposal.pdf',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => 8,
            'status' => DealProposal::STATUS_DRAFT,
        ]);
    }
}
