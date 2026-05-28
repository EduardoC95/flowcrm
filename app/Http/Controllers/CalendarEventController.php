<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCalendarEventRequest;
use App\Http\Requests\UpdateCalendarEventRequest;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\Entity;
use App\Models\Person;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CalendarEventController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CalendarEvent::class);

        $filters = $this->validatedFilters($request);
        $events = $this->eventQuery($filters)
            ->orderBy('start_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (CalendarEvent $event) => $this->eventRow($event));

        return Inertia::render('calendar/Index', [
            'events' => $events,
            'feed' => $this->feedEvents($filters),
            'filters' => $filters,
            'types' => CalendarEvent::TYPES,
            'statuses' => CalendarEvent::STATUSES,
            'priorities' => CalendarEvent::PRIORITIES,
            'owners' => $this->ownerOptions($request),
            'can' => [
                'create' => $request->user()->can('create', CalendarEvent::class),
            ],
        ]);
    }

    public function calendarFeed(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CalendarEvent::class);

        $filters = $this->validatedFilters($request);

        return response()->json(
            $this->feedEvents($filters),
        );
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', CalendarEvent::class);

        return Inertia::render('calendar-events/Create', [
            ...$this->formOptions($request),
            'defaults' => [
                'owner_id' => $request->user()->id,
                'start_at' => now()->addHour()->format('Y-m-d\TH:i'),
            ],
        ]);
    }

    public function store(StoreCalendarEventRequest $request): RedirectResponse
    {
        $event = CalendarEvent::create($this->normalizedEventData($request->validated()));
        $this->touchRelatedDeal($event);

        return redirect()
            ->route('calendar-events.show', $event)
            ->with('success', 'Evento criado com sucesso.');
    }

    public function show(CalendarEvent $calendarEvent): Response
    {
        Gate::authorize('view', $calendarEvent);

        $calendarEvent->load([
            'owner:id,name,email',
            'eventable',
            'entity:id,name',
            'person:id,name',
            'deal:id,title',
            'activityLogs' => fn ($query) => $query
                ->latest()
                ->limit(20)
                ->with('user:id,name'),
        ]);

        return Inertia::render('calendar-events/Show', [
            'event' => [
                ...$this->eventRow($calendarEvent),
                'description' => $calendarEvent->description,
                'location' => $calendarEvent->location,
                'reminder_at' => $calendarEvent->reminder_at?->toDateTimeString(),
                'reminder_sent_at' => $calendarEvent->reminder_sent_at?->toDateTimeString(),
                'created_at' => $calendarEvent->created_at?->toDateTimeString(),
                'updated_at' => $calendarEvent->updated_at?->toDateTimeString(),
                'activity_logs' => $calendarEvent->activityLogs->map(fn ($log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'description' => $log->description,
                    'properties' => $log->properties,
                    'created_at' => $log->created_at?->toDateTimeString(),
                    'user' => $log->user?->only(['id', 'name']),
                ]),
            ],
            'can' => [
                'update' => request()->user()->can('update', $calendarEvent),
                'delete' => request()->user()->can('delete', $calendarEvent),
                'complete' => request()->user()->can('complete', $calendarEvent),
                'cancel' => request()->user()->can('cancel', $calendarEvent),
            ],
        ]);
    }

    public function edit(Request $request, CalendarEvent $calendarEvent): Response
    {
        Gate::authorize('update', $calendarEvent);

        return Inertia::render('calendar-events/Edit', [
            'event' => [
                'id' => $calendarEvent->id,
                'title' => $calendarEvent->title,
                'description' => $calendarEvent->description,
                'type' => $calendarEvent->type,
                'status' => $calendarEvent->status,
                'owner_id' => $calendarEvent->owner_id,
                'start_at' => $calendarEvent->start_at?->format('Y-m-d\TH:i'),
                'end_at' => $calendarEvent->end_at?->format('Y-m-d\TH:i'),
                'reminder_at' => $calendarEvent->reminder_at?->format('Y-m-d\TH:i'),
                'priority' => $calendarEvent->priority,
                'location' => $calendarEvent->location,
                'eventable_type' => $this->eventableAlias($calendarEvent->eventable_type),
                'eventable_id' => $calendarEvent->eventable_id,
            ],
            ...$this->formOptions($request),
        ]);
    }

    public function update(UpdateCalendarEventRequest $request, CalendarEvent $calendarEvent): RedirectResponse
    {
        $calendarEvent->update($this->normalizedEventData($request->validated()));
        $this->touchRelatedDeal($calendarEvent);

        return redirect()
            ->route('calendar-events.show', $calendarEvent)
            ->with('success', 'Evento atualizado com sucesso.');
    }

    public function destroy(CalendarEvent $calendarEvent): RedirectResponse
    {
        Gate::authorize('delete', $calendarEvent);

        $calendarEvent->delete();

        return redirect()
            ->route('calendar.index')
            ->with('success', 'Evento apagado com sucesso.');
    }

    public function complete(CalendarEvent $calendarEvent, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('complete', $calendarEvent);

        $calendarEvent->forceFill([
            'status' => CalendarEvent::STATUS_COMPLETED,
        ])->save();

        $logger->log('calendar_event.completed', 'calendar_events', $calendarEvent->tenant_id, $calendarEvent, 'Calendar event completed.');

        return back()->with('success', 'Evento marcado como concluído.');
    }

    public function cancel(CalendarEvent $calendarEvent, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('cancel', $calendarEvent);

        $calendarEvent->forceFill([
            'status' => CalendarEvent::STATUS_CANCELLED,
        ])->save();

        $logger->log('calendar_event.cancelled', 'calendar_events', $calendarEvent->tenant_id, $calendarEvent, 'Calendar event cancelled.');

        return back()->with('success', 'Evento cancelado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        return [
            'type' => null,
            'status' => null,
            'owner_id' => null,
            'date_from' => null,
            'date_to' => null,
            'associated_type' => null,
            ...$request->validate([
                'type' => ['nullable', 'in:'.implode(',', CalendarEvent::TYPES)],
                'status' => ['nullable', 'in:'.implode(',', CalendarEvent::STATUSES)],
                'owner_id' => ['nullable', 'integer'],
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date'],
                'associated_type' => ['nullable', 'in:entity,person,deal'],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function eventQuery(array $filters)
    {
        return CalendarEvent::query()
            ->with(['owner:id,name', 'eventable'])
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['owner_id'] ?? null, fn ($query, int $ownerId) => $query->where('owner_id', $ownerId))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('start_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('start_at', '<=', $date))
            ->when($filters['associated_type'] ?? null, fn ($query, string $type) => $query->where('eventable_type', $this->eventableClass($type)));
    }

    /**
     * @return array<string, mixed>
     */
    private function eventRow(CalendarEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'type' => $event->type,
            'status' => $event->status,
            'priority' => $event->priority,
            'start_at' => $event->start_at?->toDateTimeString(),
            'end_at' => $event->end_at?->toDateTimeString(),
            'owner' => $event->owner?->only(['id', 'name']),
            'associated' => $this->associatedRecord($event),
            'url' => route('calendar-events.show', $event),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function feedRow(CalendarEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'start' => $event->start_at?->toIso8601String(),
            'end' => $event->end_at?->toIso8601String(),
            'type' => $event->type,
            'status' => $event->status,
            'priority' => $event->priority,
            'owner' => $event->owner?->only(['id', 'name']),
            'associated' => $this->associatedRecord($event),
            'url' => route('calendar-events.show', $event),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function associatedRecord(CalendarEvent $event): ?array
    {
        $record = $event->eventable;

        if (! $record) {
            return null;
        }

        return [
            'id' => $record->id,
            'type' => $this->eventableAlias($record::class),
            'name' => $record->name ?? $record->title,
            'url' => match ($record::class) {
                Entity::class => route('entities.show', $record),
                Person::class => route('people.show', $record),
                Deal::class => route('deals.show', $record),
                default => null,
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizedEventData(array $data): array
    {
        $eventableClass = $this->eventableClass($data['eventable_type'] ?? null);
        $eventableId = $data['eventable_id'] ?? null;
        $eventable = $eventableClass && $eventableId ? $eventableClass::find($eventableId) : null;

        return [
            ...$data,
            'eventable_type' => $eventable?->getMorphClass(),
            'eventable_id' => $eventable?->id,
            'entity_id' => $eventable instanceof Entity ? $eventable->id : ($eventable instanceof Person || $eventable instanceof Deal ? $eventable->entity_id : null),
            'person_id' => $eventable instanceof Person ? $eventable->id : ($eventable instanceof Deal ? $eventable->person_id : null),
            'deal_id' => $eventable instanceof Deal ? $eventable->id : null,
            'starts_at' => $data['start_at'],
            'ends_at' => $data['end_at'] ?? null,
            'notes' => $data['description'] ?? null,
            'type' => $data['type'] ?? CalendarEvent::TYPE_TASK,
            'status' => $data['status'] ?? CalendarEvent::STATUS_PENDING,
            'priority' => $data['priority'] ?? null,
            'reminder_at' => $data['reminder_at'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        return [
            'entities' => Entity::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'people' => Person::query()->with('entity:id,name')->orderBy('name')->limit(500)->get(['id', 'entity_id', 'name'])->map(fn (Person $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'entity_name' => $person->entity?->name,
            ]),
            'deals' => Deal::query()->orderBy('title')->limit(500)->get(['id', 'title']),
            'owners' => $this->ownerOptions($request),
            'types' => CalendarEvent::TYPES,
            'statuses' => CalendarEvent::STATUSES,
            'priorities' => CalendarEvent::PRIORITIES,
        ];
    }

    private function ownerOptions(Request $request)
    {
        return $request->user()->currentTenant?->users()
            ->orderBy('name')
            ->get(['users.id', 'users.name'])
            ->map(fn (User $user) => $user->only(['id', 'name'])) ?? collect();
    }

    private function feedEvents(array $filters)
    {
        return $this->eventQuery($filters)
            ->orderBy('start_at')
            ->limit(500)
            ->get()
            ->map(fn (CalendarEvent $event) => $this->feedRow($event));
    }

    private function eventableClass(?string $alias): ?string
    {
        return match ($alias) {
            'entity' => Entity::class,
            'person' => Person::class,
            'deal' => Deal::class,
            default => null,
        };
    }

    private function eventableAlias(?string $class): ?string
    {
        return match ($class) {
            Entity::class => 'entity',
            Person::class => 'person',
            Deal::class => 'deal',
            default => null,
        };
    }

    private function touchRelatedDeal(CalendarEvent $event): void
    {
        if ($event->deal_id) {
            Deal::whereKey($event->deal_id)->update(['last_activity_at' => now()]);
        }
    }
}
