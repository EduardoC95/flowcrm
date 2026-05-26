<?php

namespace App\Http\Controllers;

use App\Http\Requests\MergePersonRequest;
use App\Http\Requests\StorePersonRequest;
use App\Http\Requests\UpdatePersonRequest;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\Entity;
use App\Models\Person;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Person::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'entity_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
            'sort' => ['nullable', 'in:name,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $search = $filters['search'] ?? null;
        $entityId = $filters['entity_id'] ?? null;
        $status = $filters['status'] ?? null;
        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? 'asc';

        $people = Person::query()
            ->with('entity:id,name')
            ->withCount(['deals', 'calendarEvents'])
            ->when($search, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%");
                });
            })
            ->when($entityId, fn ($query, int $entityId) => $query->where('entity_id', $entityId))
            ->when($status, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Person $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'email' => $person->email,
                'phone' => $person->phone,
                'position' => $person->position,
                'status' => $person->status,
                'entity' => $person->entity?->only(['id', 'name']),
                'deals_count' => $person->deals_count,
                'calendar_events_count' => $person->calendar_events_count,
                'created_at' => $person->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('people/Index', [
            'people' => $people,
            'filters' => [
                'search' => $search,
                'entity_id' => $entityId,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statuses' => Person::STATUSES,
            'entities' => $this->entityOptions(),
            'can' => [
                'create' => $request->user()->can('create', Person::class),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Person::class);

        return Inertia::render('people/Create', [
            'statuses' => Person::STATUSES,
            'entities' => $this->entityOptions(),
        ]);
    }

    public function store(StorePersonRequest $request): RedirectResponse
    {
        $person = Person::create([
            ...$request->validated(),
            'entity_id' => $request->validated('entity_id') ?: null,
            'status' => $request->validated('status') ?: Person::STATUS_ACTIVE,
        ]);

        return redirect()
            ->route('people.show', $person)
            ->with('success', 'Pessoa criada com sucesso.');
    }

    public function show(Person $person): Response
    {
        Gate::authorize('view', $person);

        $person->load([
            'entity:id,name,email,phone,status',
            'deals:id,tenant_id,entity_id,person_id,owner_id,deal_stage_id,title,stage,value,probability,expected_close_date,priority,created_at',
            'deals.stage:id,name,slug,color',
            'deals.owner:id,name',
            'calendarEvents:id,tenant_id,entity_id,person_id,deal_id,eventable_type,eventable_id,title,type,status,start_at,end_at,starts_at,ends_at,location',
            'activityLogs' => fn ($query) => $query
                ->latest()
                ->limit(20)
                ->with('user:id,name'),
        ]);

        return Inertia::render('people/Show', [
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'email' => $person->email,
                'phone' => $person->phone,
                'position' => $person->position,
                'status' => $person->status,
                'notes' => $person->notes,
                'created_at' => $person->created_at?->toDateTimeString(),
                'updated_at' => $person->updated_at?->toDateTimeString(),
                'entity' => $person->entity,
                'deals' => $person->deals,
                'calendar_events' => $person->calendarEvents,
                'activity_logs' => $person->activityLogs->map(fn ($log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'description' => $log->description,
                    'properties' => $log->properties,
                    'created_at' => $log->created_at?->toDateTimeString(),
                    'user' => $log->user?->only(['id', 'name']),
                ]),
            ],
            'mergeCandidates' => Person::query()
                ->whereKeyNot($person->id)
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (Person $candidate) => [
                    'id' => $candidate->id,
                    'name' => $candidate->name,
                    'email' => $candidate->email,
                ]),
            'can' => [
                'update' => request()->user()->can('update', $person),
                'delete' => request()->user()->can('delete', $person),
                'merge' => request()->user()->can('update', $person),
            ],
        ]);
    }

    public function edit(Person $person): Response
    {
        Gate::authorize('update', $person);

        return Inertia::render('people/Edit', [
            'person' => $person->only([
                'id',
                'entity_id',
                'name',
                'email',
                'phone',
                'position',
                'status',
                'notes',
            ]),
            'statuses' => Person::STATUSES,
            'entities' => $this->entityOptions(),
        ]);
    }

    public function update(UpdatePersonRequest $request, Person $person): RedirectResponse
    {
        $person->update([
            ...$request->validated(),
            'entity_id' => $request->validated('entity_id') ?: null,
            'status' => $request->validated('status') ?: Person::STATUS_ACTIVE,
        ]);

        return redirect()
            ->route('people.show', $person)
            ->with('success', 'Pessoa atualizada com sucesso.');
    }

    public function destroy(Person $person): RedirectResponse
    {
        Gate::authorize('delete', $person);

        $person->delete();

        return redirect()
            ->route('people.index')
            ->with('success', 'Pessoa apagada com sucesso.');
    }

    public function merge(MergePersonRequest $request, Person $person, ActivityLogger $logger): RedirectResponse
    {
        $target = Person::findOrFail($request->validated('target_person_id'));

        Deal::where('person_id', $person->id)->update(['person_id' => $target->id]);
        CalendarEvent::where('person_id', $person->id)->update(['person_id' => $target->id]);
        CalendarEvent::where('eventable_type', Person::class)
            ->where('eventable_id', $person->id)
            ->update(['eventable_id' => $target->id]);

        $person->delete();

        $logger->log(
            'person.merged',
            'people',
            $target->tenant_id,
            $target,
            'Duplicate person merged.',
            [
                'source_person_id' => $person->id,
                'source_person_name' => $person->name,
                'target_person_id' => $target->id,
                'target_person_name' => $target->name,
            ],
        );

        return redirect()
            ->route('people.show', $target)
            ->with('success', 'Pessoa duplicada fundida com sucesso.');
    }

    private function entityOptions()
    {
        return Entity::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Entity $entity) => [
                'id' => $entity->id,
                'name' => $entity->name,
            ]);
    }
}
