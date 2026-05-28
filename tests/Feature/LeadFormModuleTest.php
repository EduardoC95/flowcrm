<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\DealStage;
use App\Models\LeadForm;
use App\Models\LeadFormSubmission;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LeadFormModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_manager_can_create_forms_but_sales_and_viewer_cannot_manage(): void
    {
        [$owner] = $this->userWithTenant(Tenant::ROLE_OWNER);
        [$manager] = $this->userWithTenant(Tenant::ROLE_MANAGER);
        $sales = User::factory()->create(['current_tenant_id' => $owner->current_tenant_id]);
        $sales->tenants()->attach($owner->current_tenant_id, ['role' => Tenant::ROLE_SALES]);
        $viewer = User::factory()->create(['current_tenant_id' => $owner->current_tenant_id]);
        $viewer->tenants()->attach($owner->current_tenant_id, ['role' => Tenant::ROLE_VIEWER]);

        $this->actingAs($owner)->post(route('lead-forms.store'), $this->formPayload(['name' => 'Owner form']))->assertRedirect();
        $this->actingAs($manager)->post(route('lead-forms.store'), $this->formPayload(['name' => 'Manager form', 'slug' => 'manager-form']))->assertRedirect();

        $form = LeadForm::withoutGlobalScopes()->firstWhere('name', 'Owner form');

        $this->actingAs($sales)->get(route('lead-forms.index'))->assertOk();
        $this->actingAs($sales)->post(route('lead-forms.store'), $this->formPayload(['name' => 'Sales form']))->assertForbidden();
        $this->actingAs($viewer)->delete(route('lead-forms.destroy', $form))->assertForbidden();
    }

    public function test_index_show_update_and_soft_delete_work(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $form = $this->leadForm($tenant, $user);

        $this->actingAs($user)
            ->get(route('lead-forms.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('lead-forms/Index')
                ->has('leadForms.data', 1)
                ->where('leadForms.data.0.id', $form->id)
                ->etc());

        $this->actingAs($user)
            ->get(route('lead-forms.show', $form))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('lead-forms/Show')
                ->where('leadForm.embed.public_url', route('public.lead-forms.show', $form->slug))
                ->where('leadForm.embed.iframe_embed_code', '<iframe src="'.route('public.lead-forms.show', $form->slug).'" width="100%" height="700" style="border:0;"></iframe>')
                ->etc());

        $this->actingAs($user)
            ->patch(route('lead-forms.update', $form), $this->formPayload(['name' => 'Atualizado', 'slug' => $form->slug]))
            ->assertRedirect(route('lead-forms.show', $form));

        $this->assertDatabaseHas('lead_forms', ['id' => $form->id, 'name' => 'Atualizado']);

        $this->actingAs($user)->delete(route('lead-forms.destroy', $form))->assertRedirect(route('lead-forms.index'));
        $this->assertSoftDeleted('lead_forms', ['id' => $form->id]);
    }

    public function test_public_active_form_loads_without_auth_and_inactive_form_rejects_submissions(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $form = $this->leadForm($tenant, $user);
        $inactive = $this->leadForm($tenant, $user, ['slug' => 'inactive-form', 'active' => false]);

        $this->get(route('public.lead-forms.show', $form->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/lead-forms/Show')
                ->where('leadForm.slug', $form->slug)
                ->etc());

        $this->post(route('public.lead-forms.submit', $inactive->slug), $this->submissionPayload())
            ->assertNotFound();
    }

    public function test_public_submission_creates_submission_person_deal_logs_and_notification(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $form = $this->leadForm($tenant, $user);

        $this->withServerVariables(['HTTP_USER_AGENT' => 'Lead Form Test Browser'])
            ->post(route('public.lead-forms.submit', $form->slug), [
                ...$this->submissionPayload(),
                'source_url' => 'https://example.test/contactos',
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/lead-forms/Show')
                ->where('submitted', true)
                ->etc());

        $submission = LeadFormSubmission::withoutGlobalScopes()->firstOrFail();
        $person = Person::withoutGlobalScopes()->findOrFail($submission->created_person_id);
        $deal = Deal::withoutGlobalScopes()->findOrFail($submission->created_deal_id);

        $this->assertSame($tenant->id, $submission->tenant_id);
        $this->assertSame('https://example.test/contactos', $submission->source_url);
        $this->assertSame('Lead Form Test Browser', $submission->user_agent);
        $this->assertTrue($submission->captcha_passed);
        $this->actingAs($user)
            ->get(route('lead-forms.show', $form))
            ->assertInertia(fn (Assert $page) => $page
                ->where('submissions.data.0.ip_address', '127.0.0.0')
                ->missing('submissions.data.0.user_agent')
                ->etc());
        $this->assertSame(Person::STATUS_LEAD, $person->status);
        $this->assertSame($person->id, $deal->person_id);
        $this->assertSame($tenant->id, $deal->tenant_id);
        $this->assertSame(DealStage::SLUG_LEAD, $deal->stage);
        $this->assertDatabaseHas('activity_logs', ['tenant_id' => $tenant->id, 'action' => 'lead_form.submitted']);
        $this->assertDatabaseHas('activity_logs', ['tenant_id' => $tenant->id, 'action' => 'lead.created_from_form']);
        $this->assertDatabaseHas('internal_notifications', ['tenant_id' => $tenant->id, 'user_id' => $user->id, 'notifiable_id' => $deal->id]);
    }

    public function test_public_submission_validates_required_fields_and_email(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $form = $this->leadForm($tenant, $user);

        $this->post(route('public.lead-forms.submit', $form->slug), [
            'name' => '',
            'email' => 'not-an-email',
            'source_url' => 'https://example.test',
        ])->assertSessionHasErrors(['name', 'email']);
    }

    public function test_tenant_isolation_for_admin_and_public_submission_target_tenant(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        [$otherUser, $otherTenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $otherForm = $this->leadForm($otherTenant, $otherUser, ['slug' => 'other-tenant-form']);

        $this->actingAs($user)->get(route('lead-forms.show', $otherForm))->assertNotFound();

        $this->post(route('public.lead-forms.submit', $otherForm->slug), $this->submissionPayload())->assertOk();

        $this->assertDatabaseHas('lead_form_submissions', ['tenant_id' => $otherTenant->id, 'lead_form_id' => $otherForm->id]);
        $this->assertDatabaseMissing('lead_form_submissions', ['tenant_id' => $tenant->id, 'lead_form_id' => $otherForm->id]);
    }

    public function test_public_routes_are_rate_limited(): void
    {
        $middleware = Route::getRoutes()->getByName('public.lead-forms.submit')?->gatherMiddleware() ?? [];

        $this->assertContains('throttle:10,1', $middleware);
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
    private function leadForm(Tenant $tenant, User $creator, array $attributes = []): LeadForm
    {
        return LeadForm::create([
            'tenant_id' => $tenant->id,
            'name' => 'Pedido de contacto',
            'slug' => 'pedido-contacto',
            'description' => 'Fale connosco.',
            'fields' => $this->fields(),
            'confirmation_message' => 'Obrigado.',
            'active' => true,
            'require_captcha' => true,
            'created_by' => $creator->id,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function formPayload(array $overrides = []): array
    {
        return [
            'name' => 'Pedido de contacto',
            'slug' => 'pedido-contacto',
            'description' => 'Fale connosco.',
            'fields' => $this->fields(),
            'confirmation_message' => 'Obrigado.',
            'active' => true,
            'require_captcha' => true,
            ...$overrides,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function submissionPayload(): array
    {
        return [
            'name' => 'Maria Lead',
            'email' => 'maria.lead@example.test',
            'phone' => '+351 910 000 001',
            'company' => 'Lead Company',
            'message' => 'Quero saber mais sobre a solução.',
            'source_url' => 'https://example.test',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fields(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome', 'type' => LeadForm::FIELD_TEXT, 'required' => true, 'placeholder' => 'O seu nome'],
            ['key' => 'email', 'label' => 'Email', 'type' => LeadForm::FIELD_EMAIL, 'required' => true, 'placeholder' => 'email@empresa.pt'],
            ['key' => 'phone', 'label' => 'Telefone', 'type' => LeadForm::FIELD_PHONE, 'required' => false, 'placeholder' => '+351 ...'],
            ['key' => 'company', 'label' => 'Empresa', 'type' => LeadForm::FIELD_TEXT, 'required' => false, 'placeholder' => 'Empresa'],
            ['key' => 'message', 'label' => 'Mensagem', 'type' => LeadForm::FIELD_TEXTAREA, 'required' => false, 'placeholder' => 'Mensagem'],
        ];
    }
}
