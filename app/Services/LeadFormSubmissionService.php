<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\InternalNotification;
use App\Models\LeadForm;
use App\Models\LeadFormSubmission;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeadFormSubmissionService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(LeadForm $form, array $payload, Request $request, bool $captchaPassed): LeadFormSubmission
    {
        return $this->withoutAuthenticatedTenantGuard(fn () => DB::transaction(function () use ($form, $payload, $request, $captchaPassed) {
            $owner = $this->resolveOwner($form);
            $stage = $this->resolveLeadStage($form);
            $message = (string) ($payload['message'] ?? $payload['description'] ?? '');

            $submission = LeadFormSubmission::create([
                'tenant_id' => $form->tenant_id,
                'lead_form_id' => $form->id,
                'payload' => $payload,
                'source_url' => $request->input('source_url') ?: $request->headers->get('referer'),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'captcha_passed' => $captchaPassed,
                'submitted_at' => now(),
            ]);

            $person = Person::create([
                'tenant_id' => $form->tenant_id,
                'name' => (string) ($payload['name'] ?? 'Lead sem nome'),
                'email' => $payload['email'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'position' => null,
                'status' => Person::STATUS_LEAD,
                'notes' => trim(sprintf(
                    "Origem: Formulário público: %s\nEmpresa: %s\nMensagem: %s",
                    $form->name,
                    $payload['company'] ?? '-',
                    $message ?: '-',
                )),
            ]);

            $deal = Deal::create([
                'tenant_id' => $form->tenant_id,
                'person_id' => $person->id,
                'entity_id' => null,
                'owner_id' => $owner->id,
                'deal_stage_id' => $stage->id,
                'title' => sprintf('Lead via %s - %s', $form->name, $person->name),
                'stage' => $stage->slug,
                'value' => 0,
                'probability' => 0,
                'priority' => Deal::PRIORITY_MEDIUM,
                'description' => trim(sprintf(
                    "Lead criada automaticamente através do formulário público \"%s\".\nOrigem: %s\nMensagem: %s",
                    $form->name,
                    $submission->source_url ?? 'sem origem',
                    $message ?: '-',
                )),
                'last_activity_at' => now(),
            ]);

            $submission->update([
                'created_person_id' => $person->id,
                'created_deal_id' => $deal->id,
            ]);

            ActivityLog::create([
                'tenant_id' => $form->tenant_id,
                'user_id' => null,
                'action' => 'lead_form.submitted',
                'module' => 'lead_forms',
                'subject_type' => LeadForm::class,
                'subject_id' => $form->id,
                'description' => 'Formulário público submetido.',
                'properties' => [
                    'submission_id' => $submission->id,
                    'created_person_id' => $person->id,
                    'created_deal_id' => $deal->id,
                ],
                'ip_address' => $submission->ip_address,
                'user_agent' => $submission->user_agent,
            ]);

            ActivityLog::create([
                'tenant_id' => $form->tenant_id,
                'user_id' => null,
                'action' => 'lead.created_from_form',
                'module' => 'deals',
                'subject_type' => Deal::class,
                'subject_id' => $deal->id,
                'description' => 'Lead criada automaticamente a partir de formulário público.',
                'properties' => [
                    'lead_form_id' => $form->id,
                    'submission_id' => $submission->id,
                    'person_id' => $person->id,
                ],
                'ip_address' => $submission->ip_address,
                'user_agent' => $submission->user_agent,
            ]);

            InternalNotification::create([
                'tenant_id' => $form->tenant_id,
                'user_id' => $owner->id,
                'title' => 'Nova lead recebida',
                'body' => sprintf('Foi criada uma nova lead através do formulário %s.', $form->name),
                'type' => 'lead_form',
                'notifiable_type' => Deal::class,
                'notifiable_id' => $deal->id,
            ]);

            return $submission->refresh();
        }));
    }

    private function resolveOwner(LeadForm $form): User
    {
        $creator = User::withoutGlobalScopes()->find($form->created_by);

        if ($creator && $creator->belongsToTenant($form->tenant_id)) {
            return $creator;
        }

        $owner = Tenant::withoutGlobalScopes()
            ->findOrFail($form->tenant_id)
            ->users()
            ->wherePivot('role', Tenant::ROLE_OWNER)
            ->first();

        return $owner ?? User::whereHas('tenants', fn ($query) => $query->whereKey($form->tenant_id))->firstOrFail();
    }

    private function resolveLeadStage(LeadForm $form): DealStage
    {
        $stage = DealStage::withoutGlobalScopes()
            ->where('tenant_id', $form->tenant_id)
            ->where('slug', DealStage::SLUG_LEAD)
            ->first();

        if ($stage) {
            return $stage;
        }

        $tenant = Tenant::withoutGlobalScopes()->findOrFail($form->tenant_id);
        DealStage::ensureDefaultStages($tenant);

        return DealStage::withoutGlobalScopes()
            ->where('tenant_id', $form->tenant_id)
            ->where('slug', DealStage::SLUG_LEAD)
            ->firstOrFail();
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withoutAuthenticatedTenantGuard(callable $callback): mixed
    {
        $guard = Auth::guard();
        $user = $guard->user();
        $guard->forgetUser();

        try {
            return $callback();
        } finally {
            if ($user) {
                $guard->setUser($user);
            }
        }
    }
}
