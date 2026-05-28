<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConvertAISuggestionToActivityRequest;
use App\Http\Requests\PostponeAISuggestionRequest;
use App\Models\ActivityLog;
use App\Models\AISuggestion;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\InternalNotification;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AISuggestionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AISuggestion::class);

        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,accepted,postponed,archived,ignored'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'type' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer'],
            'deal_id' => ['nullable', 'integer'],
        ]);

        $query = $this->visibleSuggestions($request)
            ->with(['user:id,name', 'deal:id,title,value,priority,expected_close_date,last_activity_at,deal_stage_id,owner_id', 'deal.stage:id,name,slug,color', 'person:id,name,email,phone', 'entity:id,name,email']);

        if (! ($filters['status'] ?? null)) {
            $query->where(function ($query) {
                $query->where('status', AISuggestion::STATUS_PENDING)
                    ->orWhere(fn ($query) => $query
                        ->where('status', AISuggestion::STATUS_POSTPONED)
                        ->where('postponed_until', '<=', now()));
            });
        }

        $query
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, string $priority) => $query->where('priority', $priority))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['deal_id'] ?? null, fn ($query, int $dealId) => $query->where('deal_id', $dealId));

        if ($this->canViewAll($request) && ($filters['user_id'] ?? null)) {
            $query->where('user_id', $filters['user_id']);
        }

        $suggestions = $query
            ->orderByDesc('score')
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (AISuggestion $suggestion) => $this->suggestionRow($suggestion));

        $summaryQuery = $this->visibleSuggestions($request);

        return Inertia::render('ai-suggestions/Index', [
            'suggestions' => $suggestions,
            'filters' => [
                'status' => $filters['status'] ?? null,
                'priority' => $filters['priority'] ?? null,
                'type' => $filters['type'] ?? null,
                'user_id' => $filters['user_id'] ?? null,
                'deal_id' => $filters['deal_id'] ?? null,
            ],
            'summary' => [
                'pending' => (clone $summaryQuery)->where('status', AISuggestion::STATUS_PENDING)->count(),
                'urgent' => (clone $summaryQuery)->whereIn('priority', [AISuggestion::PRIORITY_HIGH, AISuggestion::PRIORITY_URGENT])->where('status', AISuggestion::STATUS_PENDING)->count(),
                'impacted_value' => (float) (clone $summaryQuery)->join('deals', 'deals.id', '=', 'ai_suggestions.deal_id')->where('ai_suggestions.status', AISuggestion::STATUS_PENDING)->sum('deals.value'),
                'converted_this_week' => (clone $summaryQuery)->whereNotNull('converted_calendar_event_id')->where('accepted_at', '>=', now()->startOfWeek())->count(),
            ],
            'options' => [
                'types' => AISuggestion::TYPES,
                'priorities' => AISuggestion::PRIORITIES,
                'statuses' => AISuggestion::STATUSES,
                'users' => $this->canViewAll($request)
                    ? $request->user()->currentTenant?->users()->select('users.id', 'users.name')->orderBy('name')->get() ?? []
                    : [],
            ],
            'canViewAll' => $this->canViewAll($request),
            'canAct' => in_array($request->user()->roleForTenant(), [
                Tenant::ROLE_OWNER,
                Tenant::ROLE_MANAGER,
                Tenant::ROLE_SALES,
            ], true),
        ]);
    }

    public function show(Request $request, AISuggestion $suggestion): Response
    {
        Gate::authorize('view', $suggestion);

        $suggestion->load(['user:id,name', 'deal.stage:id,name,slug,color', 'deal.owner:id,name', 'person:id,name,email,phone', 'entity:id,name,email,phone', 'calendarEvent:id,title,start_at', 'convertedCalendarEvent:id,title,start_at']);

        return Inertia::render('ai-suggestions/Show', [
            'suggestion' => $this->suggestionRow($suggestion, true),
            'can' => [
                'act' => $request->user()->can('accept', $suggestion),
            ],
        ]);
    }

    public function accept(Request $request, AISuggestion $suggestion): RedirectResponse
    {
        Gate::authorize('accept', $suggestion);

        $suggestion->update([
            'status' => AISuggestion::STATUS_ACCEPTED,
            'accepted_at' => now(),
            'accepted_by' => $request->user()->id,
        ]);

        $this->log($suggestion, $request->user()->id, 'ai_suggestion.accepted', 'Sugestao aceite.');

        return back()->with('success', 'Sugestao aceite.');
    }

    public function postpone(PostponeAISuggestionRequest $request, AISuggestion $suggestion): RedirectResponse
    {
        $suggestion->update([
            'status' => AISuggestion::STATUS_POSTPONED,
            'postponed_until' => $request->validated('postponed_until'),
        ]);

        $this->log($suggestion, $request->user()->id, 'ai_suggestion.postponed', 'Sugestao adiada.');

        return back()->with('success', 'Sugestao adiada.');
    }

    public function archive(Request $request, AISuggestion $suggestion): RedirectResponse
    {
        Gate::authorize('archive', $suggestion);

        $suggestion->update([
            'status' => AISuggestion::STATUS_ARCHIVED,
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]);

        $this->log($suggestion, $request->user()->id, 'ai_suggestion.archived', 'Sugestao arquivada.');

        return back()->with('success', 'Sugestao arquivada.');
    }

    public function ignore(Request $request, AISuggestion $suggestion): RedirectResponse
    {
        Gate::authorize('ignore', $suggestion);

        $suggestion->update([
            'status' => AISuggestion::STATUS_IGNORED,
            'ignored_at' => now(),
            'ignored_by' => $request->user()->id,
        ]);

        $this->log($suggestion, $request->user()->id, 'ai_suggestion.ignored', 'Sugestao ignorada.');

        return back()->with('success', 'Sugestao ignorada.');
    }

    public function convertToActivity(ConvertAISuggestionToActivityRequest $request, AISuggestion $suggestion): RedirectResponse
    {
        $data = $request->validated();
        $startAt = Carbon::parse($data['start_at'] ?? $suggestion->suggested_due_at ?? now()->addDay());

        $event = CalendarEvent::create([
            'tenant_id' => $suggestion->tenant_id,
            'deal_id' => $suggestion->deal_id,
            'entity_id' => $suggestion->entity_id,
            'person_id' => $suggestion->person_id,
            'eventable_type' => $suggestion->deal_id ? Deal::class : null,
            'eventable_id' => $suggestion->deal_id,
            'owner_id' => $suggestion->user_id,
            'title' => $data['title'] ?? $suggestion->suggested_action,
            'description' => $data['description'] ?? $suggestion->reason,
            'notes' => $data['description'] ?? $suggestion->reason,
            'type' => $data['activity_type'] ?? CalendarEvent::TYPE_TASK,
            'status' => CalendarEvent::STATUS_PENDING,
            'priority' => $data['priority'] ?? $suggestion->priority,
            'start_at' => $startAt,
            'starts_at' => $startAt,
            'end_at' => isset($data['end_at']) ? Carbon::parse($data['end_at']) : null,
            'ends_at' => isset($data['end_at']) ? Carbon::parse($data['end_at']) : null,
        ]);

        $suggestion->update([
            'status' => AISuggestion::STATUS_ACCEPTED,
            'accepted_at' => now(),
            'accepted_by' => $request->user()->id,
            'converted_calendar_event_id' => $event->id,
        ]);

        if ($suggestion->deal) {
            $suggestion->deal->forceFill(['last_activity_at' => now()])->save();
        }

        InternalNotification::create([
            'tenant_id' => $suggestion->tenant_id,
            'user_id' => $suggestion->user_id,
            'title' => 'Sugestao convertida em atividade',
            'body' => 'Foi criada uma atividade a partir da sugestao: '.$suggestion->title,
            'type' => 'ai_suggestion',
            'notifiable_type' => CalendarEvent::class,
            'notifiable_id' => $event->id,
        ]);

        $this->log($suggestion, $request->user()->id, 'ai_suggestion.converted_to_activity', 'Sugestao convertida em atividade.', [
            'calendar_event_id' => $event->id,
        ]);
        ActivityLog::create([
            'tenant_id' => $event->tenant_id,
            'user_id' => $request->user()->id,
            'action' => 'calendar_event.created',
            'module' => 'calendar',
            'subject_type' => CalendarEvent::class,
            'subject_id' => $event->id,
            'description' => 'Atividade criada a partir do Agente Comercial AI.',
            'properties' => ['ai_suggestion_id' => $suggestion->id],
        ]);

        return back()
            ->with('success', 'Sugestao convertida em atividade.')
            ->with('created_event_url', route('calendar-events.show', $event));
    }

    private function visibleSuggestions(Request $request)
    {
        $query = AISuggestion::query();

        if (! $this->canViewAll($request)) {
            $query->where('user_id', $request->user()->id);
        }

        return $query;
    }

    private function canViewAll(Request $request): bool
    {
        return in_array($request->user()->roleForTenant(), [Tenant::ROLE_OWNER, Tenant::ROLE_MANAGER], true);
    }

    /**
     * @return array<string,mixed>
     */
    private function suggestionRow(AISuggestion $suggestion, bool $full = false): array
    {
        $dealStage = $suggestion->deal?->relationLoaded('stage') ? $suggestion->deal->getRelation('stage') : null;
        $dealOwner = $suggestion->deal?->relationLoaded('owner') ? $suggestion->deal->getRelation('owner') : null;

        return [
            'id' => $suggestion->id,
            'type' => $suggestion->type,
            'title' => $suggestion->title,
            'reason' => $suggestion->reason,
            'suggested_action' => $suggestion->suggested_action,
            'suggested_due_at' => $suggestion->suggested_due_at?->toDateTimeString(),
            'priority' => $suggestion->priority,
            'status' => $suggestion->status,
            'source' => $suggestion->source,
            'score' => $suggestion->score,
            'metadata' => $suggestion->metadata ?? [],
            'postponed_until' => $suggestion->postponed_until?->toDateTimeString(),
            'created_at' => $suggestion->created_at?->toDateTimeString(),
            'user' => $suggestion->user?->only(['id', 'name']),
            'deal' => $suggestion->deal ? [
                'id' => $suggestion->deal->id,
                'title' => $suggestion->deal->title,
                'value' => (float) $suggestion->deal->value,
                'priority' => $suggestion->deal->priority,
                'expected_close_date' => $suggestion->deal->expected_close_date?->toDateString(),
                'last_activity_at' => $suggestion->deal->last_activity_at?->toDateTimeString(),
                'stage' => $dealStage?->only(['id', 'name', 'slug', 'color']),
                'owner' => $dealOwner?->only(['id', 'name']),
                'url' => route('deals.show', $suggestion->deal, false),
            ] : null,
            'person' => $suggestion->person ? [
                ...$suggestion->person->only(['id', 'name', 'email', 'phone']),
                'url' => route('people.show', $suggestion->person, false),
            ] : null,
            'entity' => $suggestion->entity ? [
                ...$suggestion->entity->only(['id', 'name', 'email', 'phone']),
                'url' => route('entities.show', $suggestion->entity, false),
            ] : null,
            'converted_calendar_event' => $suggestion->convertedCalendarEvent ? [
                'id' => $suggestion->convertedCalendarEvent->id,
                'title' => $suggestion->convertedCalendarEvent->title,
                'start_at' => $suggestion->convertedCalendarEvent->start_at?->toDateTimeString(),
                'url' => route('calendar-events.show', $suggestion->convertedCalendarEvent, false),
            ] : null,
            'url' => route('ai-suggestions.show', $suggestion, false),
            'full' => $full,
        ];
    }

    /**
     * @param  array<string,mixed>  $properties
     */
    private function log(AISuggestion $suggestion, ?int $userId, string $action, string $description, array $properties = []): void
    {
        ActivityLog::create([
            'tenant_id' => $suggestion->tenant_id,
            'user_id' => $userId,
            'action' => $action,
            'module' => 'ai_suggestions',
            'subject_type' => AISuggestion::class,
            'subject_id' => $suggestion->id,
            'description' => $description,
            'properties' => $properties ?: null,
        ]);
    }
}
