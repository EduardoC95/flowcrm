<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAutomationRuleRequest;
use App\Http\Requests\UpdateAutomationRuleRequest;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AutomationRuleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AutomationRule::class);

        $automations = AutomationRule::query()
            ->with('creator:id,name')
            ->withCount('runs')
            ->withMax('runs', 'ran_at')
            ->latest()
            ->paginate(10)
            ->through(fn (AutomationRule $automation) => $this->automationRow($automation));

        return Inertia::render('automations/Index', [
            'automations' => $automations,
            'can' => [
                'create' => $request->user()->can('create', AutomationRule::class),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', AutomationRule::class);

        return Inertia::render('automations/Create', [
            'defaults' => $this->defaultPayload(),
            'options' => $this->options(),
        ]);
    }

    public function store(StoreAutomationRuleRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $automation = AutomationRule::create([
            ...$this->validatedPayload($request->validated()),
            'created_by' => $request->user()->id,
        ]);

        $logger->log('automation_rule.created', 'automations', $automation->tenant_id, $automation, 'Regra de automação criada.', [
            'name' => $automation->name,
        ]);

        return redirect()
            ->route('automations.show', $automation)
            ->with('success', 'Automação criada com sucesso.');
    }

    public function show(Request $request, AutomationRule $automation): Response
    {
        Gate::authorize('view', $automation);

        $automation->load(['creator:id,name', 'pausedBy:id,name']);

        $runs = $automation->runs()
            ->with(['deal:id,title', 'calendarEvent:id,title,start_at'])
            ->latest('ran_at')
            ->paginate(12)
            ->through(fn (AutomationRun $run) => [
                'id' => $run->id,
                'status' => $run->status,
                'result' => $run->result,
                'ran_at' => $run->ran_at?->toDateTimeString(),
                'deal' => $run->deal?->only(['id', 'title']),
                'calendar_event' => $run->calendarEvent ? [
                    'id' => $run->calendarEvent->id,
                    'title' => $run->calendarEvent->title,
                    'start_at' => $run->calendarEvent->start_at?->toDateTimeString(),
                ] : null,
            ]);

        return Inertia::render('automations/Show', [
            'automation' => [
                ...$this->automationRow($automation),
                'description' => $automation->description,
                'action_payload' => $automation->action_payload ?? $this->defaultPayload(),
                'creator' => $automation->creator?->only(['id', 'name']),
                'paused_by' => $automation->pausedBy?->only(['id', 'name']),
                'paused_at' => $automation->paused_at?->toDateTimeString(),
            ],
            'runs' => $runs,
            'can' => [
                'update' => $request->user()->can('update', $automation),
                'delete' => $request->user()->can('delete', $automation),
                'pause' => $request->user()->can('pause', $automation),
                'resume' => $request->user()->can('resume', $automation),
            ],
        ]);
    }

    public function edit(AutomationRule $automation): Response
    {
        Gate::authorize('update', $automation);

        return Inertia::render('automations/Edit', [
            'automation' => [
                'id' => $automation->id,
                'name' => $automation->name,
                'description' => $automation->description,
                'trigger_type' => $automation->trigger_type,
                'inactivity_days' => $automation->inactivity_days,
                'action_type' => $automation->action_type,
                'action_payload' => $automation->action_payload ?? $this->defaultPayload(),
                'notify_owner' => $automation->notify_owner,
                'active' => $automation->active,
            ],
            'options' => $this->options(),
        ]);
    }

    public function update(UpdateAutomationRuleRequest $request, AutomationRule $automation, ActivityLogger $logger): RedirectResponse
    {
        $automation->update($this->validatedPayload($request->validated()));

        $logger->log('automation_rule.updated', 'automations', $automation->tenant_id, $automation, 'Regra de automação atualizada.', [
            'changes' => $automation->getChanges(),
        ]);

        return redirect()
            ->route('automations.show', $automation)
            ->with('success', 'Automação atualizada com sucesso.');
    }

    public function destroy(AutomationRule $automation, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('delete', $automation);

        $logger->log('automation_rule.deleted', 'automations', $automation->tenant_id, $automation, 'Regra de automação apagada.', [
            'name' => $automation->name,
        ]);

        $automation->delete();

        return redirect()
            ->route('automations.index')
            ->with('success', 'Automação apagada com sucesso.');
    }

    public function pause(Request $request, AutomationRule $automation, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('pause', $automation);

        $automation->update([
            'active' => false,
            'paused_at' => now(),
            'paused_by' => $request->user()->id,
        ]);

        $logger->log('automation_rule.paused', 'automations', $automation->tenant_id, $automation, 'Regra de automação pausada.');

        return back()->with('success', 'Automação pausada.');
    }

    public function resume(AutomationRule $automation, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('resume', $automation);

        $automation->update([
            'active' => true,
            'paused_at' => null,
            'paused_by' => null,
        ]);

        $logger->log('automation_rule.resumed', 'automations', $automation->tenant_id, $automation, 'Regra de automação retomada.');

        return back()->with('success', 'Automação retomada.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function validatedPayload(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'trigger_type' => $validated['trigger_type'],
            'inactivity_days' => $validated['inactivity_days'] ?? null,
            'action_type' => $validated['action_type'],
            'action_payload' => [
                ...$this->defaultPayload(),
                ...($validated['action_payload'] ?? []),
            ],
            'notify_owner' => (bool) ($validated['notify_owner'] ?? false),
            'active' => (bool) ($validated['active'] ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function automationRow(AutomationRule $automation): array
    {
        return [
            'id' => $automation->id,
            'name' => $automation->name,
            'description' => $automation->description,
            'trigger_type' => $automation->trigger_type,
            'inactivity_days' => $automation->inactivity_days,
            'action_type' => $automation->action_type,
            'action_payload' => $automation->action_payload ?? $this->defaultPayload(),
            'notify_owner' => $automation->notify_owner,
            'active' => $automation->active,
            'paused_at' => $automation->paused_at?->toDateTimeString(),
            'runs_count' => $automation->runs_count ?? null,
            'last_run_at' => $automation->runs_max_ran_at ?? null,
            'creator' => $automation->creator?->only(['id', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPayload(): array
    {
        return [
            'activity_type' => CalendarEvent::TYPE_TASK,
            'activity_title_template' => 'Follow-up automático: {deal_title}',
            'activity_description_template' => 'Este negócio está sem atividade há {inactivity_days} dias. Rever próximos passos.',
            'due_in_days' => 1,
            'priority' => 'inherit',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'trigger_types' => [
                ['value' => AutomationRule::TRIGGER_DEAL_INACTIVITY, 'label' => 'Negócio sem atividade'],
            ],
            'action_types' => [
                ['value' => AutomationRule::ACTION_CREATE_CALENDAR_ACTIVITY, 'label' => 'Criar atividade no calendário'],
            ],
            'activity_types' => [
                ['value' => CalendarEvent::TYPE_TASK, 'label' => 'Tarefa'],
                ['value' => CalendarEvent::TYPE_CALL, 'label' => 'Chamada'],
                ['value' => CalendarEvent::TYPE_MEETING, 'label' => 'Reunião'],
                ['value' => CalendarEvent::TYPE_REMINDER, 'label' => 'Lembrete'],
            ],
            'priorities' => [
                ['value' => 'inherit', 'label' => 'Herdar do negócio'],
                ['value' => Deal::PRIORITY_LOW, 'label' => 'Baixa'],
                ['value' => Deal::PRIORITY_MEDIUM, 'label' => 'Média'],
                ['value' => Deal::PRIORITY_HIGH, 'label' => 'Alta'],
                ['value' => Deal::PRIORITY_URGENT, 'label' => 'Urgente'],
            ],
        ];
    }
}
