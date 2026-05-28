<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\DealFollowUp;
use App\Models\DealStage;
use App\Models\FollowUpTemplate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FollowUpService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function startForDeal(Deal $deal, ?User $user = null): ?DealFollowUp
    {
        $stage = $deal->stage()->first();

        if ($stage?->slug !== DealStage::SLUG_FOLLOW_UP) {
            return null;
        }

        $existing = $deal->activeFollowUp()->first();

        if ($existing instanceof DealFollowUp) {
            return $existing;
        }

        $followUp = DealFollowUp::create([
            'tenant_id' => $deal->tenant_id,
            'deal_id' => $deal->id,
            'status' => DealFollowUp::STATUS_ACTIVE,
            'next_send_at' => $this->nextBusinessDateTime(now()),
        ]);

        $this->logger->log(
            'follow_up.started',
            'follow_ups',
            $deal->tenant_id,
            $deal,
            'Automated follow-up cycle started.',
            [
                'deal_follow_up_id' => $followUp->id,
                'next_send_at' => $followUp->next_send_at?->toDateTimeString(),
                'started_by' => $user?->id,
            ],
        );

        return $followUp;
    }

    public function cancelForDeal(Deal $deal, string $reason, ?User $user = null): ?DealFollowUp
    {
        $followUp = $deal->activeFollowUp()->first();

        if (! $followUp instanceof DealFollowUp) {
            return null;
        }

        $followUp->update([
            'status' => DealFollowUp::STATUS_CANCELLED,
            'next_send_at' => null,
            'cancelled_at' => now(),
            'cancelled_by' => $user?->id,
            'cancellation_reason' => $reason,
        ]);

        $this->logger->log(
            'follow_up.cancelled',
            'follow_ups',
            $deal->tenant_id,
            $deal,
            'Automated follow-up cycle cancelled.',
            [
                'deal_follow_up_id' => $followUp->id,
                'reason' => $reason,
                'cancelled_by' => $user?->id,
            ],
        );

        return $followUp;
    }

    public function markClientReplied(Deal $deal, ?User $user = null): ?DealFollowUp
    {
        $followUp = $deal->activeFollowUp()->first();

        if (! $followUp instanceof DealFollowUp) {
            return null;
        }

        $followUp->update([
            'status' => DealFollowUp::STATUS_REPLIED,
            'next_send_at' => null,
            'replied_at' => now(),
            'replied_by' => $user?->id,
        ]);

        $this->logger->log(
            'follow_up.client_replied',
            'follow_ups',
            $deal->tenant_id,
            $deal,
            'Client reply recorded for automated follow-up.',
            [
                'deal_follow_up_id' => $followUp->id,
                'replied_by' => $user?->id,
            ],
        );

        return $followUp;
    }

    public function scheduleNext(DealFollowUp $followUp): DealFollowUp
    {
        $baseDate = ($followUp->last_sent_at ?? now())->copy()->addDays(2);

        $followUp->forceFill([
            'next_send_at' => $this->nextBusinessDateTime($baseDate),
        ])->save();

        return $followUp;
    }

    public function pickTemplate(DealFollowUp $followUp): ?FollowUpTemplate
    {
        $templates = $this->templatesForTenant($followUp->tenant_id);

        if ($templates->isEmpty()) {
            return null;
        }

        $lastTemplateId = $followUp->emails()
            ->latest('sent_at')
            ->value('follow_up_template_id');

        $eligible = $templates;

        if ($templates->count() > 1 && $lastTemplateId) {
            $eligible = $templates->reject(fn (FollowUpTemplate $template) => $template->id === $lastTemplateId)->values();
        }

        return $eligible->get($followUp->sent_count % max($eligible->count(), 1)) ?? $eligible->first();
    }

    public function resolveRecipientEmail(Deal $deal): ?string
    {
        $deal->loadMissing('person:id,name,email', 'entity:id,name,email');

        return $deal->person?->email ?: $deal->entity?->email;
    }

    public function nextBusinessDateTime(Carbon $date): Carbon
    {
        $next = $date->copy()->seconds(0);

        while ($next->isWeekend()) {
            $next->addDay()->setTime(9, 0);
        }

        if ($next->hour < 9) {
            return $next->setTime(9, 0);
        }

        if ($next->hour >= 18) {
            $next->addDay()->setTime(9, 0);

            while ($next->isWeekend()) {
                $next->addDay()->setTime(9, 0);
            }
        }

        return $next;
    }

    public function isWithinBusinessHours(Carbon $date): bool
    {
        return $date->isWeekday()
            && $date->hour >= 9
            && $date->hour < 18;
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function renderTemplate(FollowUpTemplate $template, Deal $deal, ?User $user = null): array
    {
        $deal->loadMissing('person:id,name,email', 'entity:id,name,email', 'owner:id,name');

        $replacements = [
            '{client_name}' => $deal->person?->name ?? $deal->entity?->name ?? 'cliente',
            '{deal_title}' => $deal->title,
            '{user_name}' => $user?->name ?? $deal->owner?->name ?? 'Equipa comercial',
            '{company_name}' => $deal->entity?->name ?? 'a sua empresa',
        ];

        return [
            'subject' => strtr($template->subject, $replacements),
            'body' => strtr($template->body, $replacements),
        ];
    }

    /**
     * @return Collection<int, FollowUpTemplate>
     */
    private function templatesForTenant(int $tenantId): Collection
    {
        return FollowUpTemplate::query()
            ->where('active', true)
            ->where(function ($query) use ($tenantId) {
                $query->whereNull('tenant_id')
                    ->orWhere('tenant_id', $tenantId);
            })
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }
}
