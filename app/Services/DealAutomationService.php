<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\InternalNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Throwable;

class DealAutomationService
{
    /**
     * @return array{rules:int, success:int, skipped:int, failed:int}
     */
    public function runAllActiveRules(): array
    {
        $summary = ['rules' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0];

        AutomationRule::withoutGlobalScope('tenant')
            ->where('active', true)
            ->whereNull('paused_at')
            ->where('trigger_type', AutomationRule::TRIGGER_DEAL_INACTIVITY)
            ->each(function (AutomationRule $rule) use (&$summary) {
                $summary['rules']++;
                $result = $this->runRule($rule);

                $summary['success'] += $result['success'];
                $summary['skipped'] += $result['skipped'];
                $summary['failed'] += $result['failed'];
            });

        return $summary;
    }

    /**
     * @return array{success:int, skipped:int, failed:int}
     */
    public function runRule(AutomationRule $rule): array
    {
        $summary = ['success' => 0, 'skipped' => 0, 'failed' => 0];

        $this->findInactiveDeals($rule)->each(function (Deal $deal) use ($rule, &$summary) {
            if ($this->alreadyRanRecently($rule, $deal)) {
                $this->recordRun($rule, $deal, AutomationRun::STATUS_SKIPPED, 'Já existe execução recente para este negócio.');
                $summary['skipped']++;

                return;
            }

            try {
                $event = $this->createActivityForDeal($rule, $deal);

                $this->recordRun($rule, $deal, AutomationRun::STATUS_SUCCESS, 'Atividade criada automaticamente.', $event);
                $this->log('automation_run.success', $rule, $deal, 'Automação criou uma atividade no calendário.', [
                    'calendar_event_id' => $event->id,
                ]);

                if ($rule->notify_owner) {
                    $this->createNotification($rule, $deal, $event);
                }

                $summary['success']++;
            } catch (Throwable $exception) {
                $this->recordRun($rule, $deal, AutomationRun::STATUS_FAILED, $exception->getMessage());
                $this->log('automation_run.failed', $rule, $deal, 'Automação falhou ao criar atividade.', [
                    'error' => $exception->getMessage(),
                ]);
                $summary['failed']++;
            }
        });

        return $summary;
    }

    /**
     * @return Collection<int, Deal>
     */
    public function findInactiveDeals(AutomationRule $rule): Collection
    {
        $threshold = now()->subDays((int) $rule->inactivity_days);

        return Deal::withoutGlobalScope('tenant')
            ->with(['stage', 'owner', 'entity', 'person'])
            ->where('tenant_id', $rule->tenant_id)
            ->where(function ($query) use ($threshold) {
                $query->where(function ($query) use ($threshold) {
                    $query->whereNull('last_activity_at')
                        ->where('created_at', '<=', $threshold);
                })->orWhere('last_activity_at', '<=', $threshold);
            })
            ->whereHas('stage', function ($query) {
                $query->where('is_won', false)
                    ->where('is_lost', false);
            })
            ->get();
    }

    public function createActivityForDeal(AutomationRule $rule, Deal $deal): CalendarEvent
    {
        $payload = $this->normalizedPayload($rule);
        $dueAt = $this->nextBusinessDateTime(now()->addDays((int) $payload['due_in_days']));
        $priority = $payload['priority'] === 'inherit'
            ? ($deal->priority ?: CalendarEvent::PRIORITY_MEDIUM)
            : $payload['priority'];

        $event = $this->withoutAuthenticatedTenantGuard(fn () => CalendarEvent::create([
            'tenant_id' => $deal->tenant_id,
            'eventable_type' => Deal::class,
            'eventable_id' => $deal->id,
            'deal_id' => $deal->id,
            'entity_id' => $deal->entity_id,
            'person_id' => $deal->person_id,
            'owner_id' => $deal->owner_id,
            'title' => $this->renderTemplate($payload['activity_title_template'], $deal, $rule),
            'description' => $this->renderTemplate($payload['activity_description_template'], $deal, $rule),
            'notes' => $this->renderTemplate($payload['activity_description_template'], $deal, $rule),
            'type' => $payload['activity_type'],
            'start_at' => $dueAt,
            'end_at' => $dueAt->copy()->addHour(),
            'starts_at' => $dueAt,
            'ends_at' => $dueAt->copy()->addHour(),
            'status' => CalendarEvent::STATUS_PENDING,
            'priority' => $priority,
        ]));

        $this->withoutAuthenticatedTenantGuard(fn () => $deal->forceFill([
            'last_activity_at' => now(),
        ])->save());

        return $event;
    }

    public function alreadyRanRecently(AutomationRule $rule, Deal $deal): bool
    {
        return AutomationRun::withoutGlobalScope('tenant')
            ->where('automation_rule_id', $rule->id)
            ->where('deal_id', $deal->id)
            ->where('status', AutomationRun::STATUS_SUCCESS)
            ->where('ran_at', '>=', now()->subDays((int) $rule->inactivity_days))
            ->exists();
    }

    public function createNotification(AutomationRule $rule, Deal $deal, CalendarEvent $event): InternalNotification
    {
        $notification = $this->withoutAuthenticatedTenantGuard(fn () => InternalNotification::create([
            'tenant_id' => $deal->tenant_id,
            'user_id' => $deal->owner_id,
            'title' => 'Nova atividade criada por automação',
            'body' => sprintf('A automação "%s" criou a atividade "%s" para o negócio "%s".', $rule->name, $event->title, $deal->title),
            'type' => 'automation',
            'notifiable_type' => CalendarEvent::class,
            'notifiable_id' => $event->id,
        ]));

        $this->log('internal_notification.created', $rule, $deal, 'Notificação interna criada para o responsável.', [
            'notification_id' => $notification->id,
        ]);

        return $notification;
    }

    public function nextBusinessDateTime(Carbon $date): Carbon
    {
        $date = $date->copy();

        while ($date->isWeekend()) {
            $date->addDay()->setTime(9, 0);
        }

        if ($date->hour < 9) {
            return $date->setTime(9, 0);
        }

        if ($date->hour >= 18) {
            $date->addDay()->setTime(9, 0);

            return $this->nextBusinessDateTime($date);
        }

        return $date;
    }

    private function recordRun(
        AutomationRule $rule,
        Deal $deal,
        string $status,
        ?string $result = null,
        ?CalendarEvent $event = null,
    ): AutomationRun {
        return $this->withoutAuthenticatedTenantGuard(fn () => AutomationRun::create([
            'tenant_id' => $rule->tenant_id,
            'automation_rule_id' => $rule->id,
            'deal_id' => $deal->id,
            'calendar_event_id' => $event?->id,
            'status' => $status,
            'result' => $result,
            'metadata' => [
                'rule_name' => $rule->name,
                'deal_title' => $deal->title,
            ],
            'ran_at' => now(),
        ]));
    }

    /**
     * @return array{activity_type:string, activity_title_template:string, activity_description_template:string, due_in_days:int, priority:string}
     */
    private function normalizedPayload(AutomationRule $rule): array
    {
        $payload = $rule->action_payload ?? [];

        return [
            'activity_type' => $payload['activity_type'] ?? CalendarEvent::TYPE_TASK,
            'activity_title_template' => $payload['activity_title_template'] ?? 'Follow-up automático: {deal_title}',
            'activity_description_template' => $payload['activity_description_template'] ?? 'Este negócio está sem atividade há {inactivity_days} dias. Rever próximos passos.',
            'due_in_days' => (int) ($payload['due_in_days'] ?? 1),
            'priority' => $payload['priority'] ?? 'inherit',
        ];
    }

    private function renderTemplate(string $template, Deal $deal, AutomationRule $rule): string
    {
        return strtr($template, [
            '{deal_title}' => $deal->title,
            '{deal_value}' => (string) $deal->value,
            '{deal_priority}' => $deal->priority ?? CalendarEvent::PRIORITY_MEDIUM,
            '{owner_name}' => $deal->owner?->name ?? 'Responsável',
            '{entity_name}' => $deal->entity?->name ?? '',
            '{person_name}' => $deal->person?->name ?? '',
            '{inactivity_days}' => (string) $rule->inactivity_days,
            '{automation_name}' => $rule->name,
        ]);
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

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(string $action, AutomationRule $rule, ?Deal $deal, string $description, array $properties = []): void
    {
        $this->withoutAuthenticatedTenantGuard(fn () => ActivityLog::create([
            'tenant_id' => $rule->tenant_id,
            'user_id' => null,
            'action' => $action,
            'module' => 'automations',
            'subject_type' => $deal ? Deal::class : AutomationRule::class,
            'subject_id' => $deal?->id ?? $rule->id,
            'description' => $description,
            'properties' => [
                'automation_rule_id' => $rule->id,
                ...$properties,
            ],
        ]));
    }
}
