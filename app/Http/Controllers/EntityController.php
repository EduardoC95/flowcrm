<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEntityRequest;
use App\Http\Requests\UpdateEntityRequest;
use App\Models\Entity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EntityController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Entity::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
            'sort' => ['nullable', 'in:name,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;
        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? 'asc';

        $entities = Entity::query()
            ->withCount(['people', 'deals'])
            ->when($search, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('vat', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Entity $entity) => [
                'id' => $entity->id,
                'name' => $entity->name,
                'vat' => $entity->vat,
                'email' => $entity->email,
                'phone' => $entity->phone,
                'status' => $entity->status,
                'people_count' => $entity->people_count,
                'deals_count' => $entity->deals_count,
                'created_at' => $entity->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('entities/Index', [
            'entities' => $entities,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statuses' => Entity::STATUSES,
            'can' => [
                'create' => $request->user()->can('create', Entity::class),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Entity::class);

        return Inertia::render('entities/Create', [
            'statuses' => Entity::STATUSES,
        ]);
    }

    public function store(StoreEntityRequest $request): RedirectResponse
    {
        $entity = Entity::create([
            ...$request->validated(),
            'status' => $request->validated('status') ?: Entity::STATUS_ACTIVE,
        ]);

        return redirect()
            ->route('entities.show', $entity)
            ->with('success', 'Entidade criada com sucesso.');
    }

    public function show(Entity $entity): Response
    {
        Gate::authorize('view', $entity);

        $entity->load([
            'people:id,tenant_id,entity_id,name,email,phone,position,job_title',
            'deals:id,tenant_id,entity_id,title,stage,value,expected_close_date,created_at',
            'calendarEvents:id,tenant_id,entity_id,title,starts_at,ends_at,location',
            'activityLogs' => fn ($query) => $query
                ->latest()
                ->limit(20)
                ->with('user:id,name'),
        ]);

        return Inertia::render('entities/Show', [
            'entity' => [
                'id' => $entity->id,
                'name' => $entity->name,
                'vat' => $entity->vat,
                'email' => $entity->email,
                'phone' => $entity->phone,
                'address' => $entity->address,
                'status' => $entity->status,
                'notes' => $entity->notes,
                'created_at' => $entity->created_at?->toDateTimeString(),
                'updated_at' => $entity->updated_at?->toDateTimeString(),
                'people' => $entity->people,
                'deals' => $entity->deals,
                'calendar_events' => $entity->calendarEvents,
                'activity_logs' => $entity->activityLogs->map(fn ($log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'description' => $log->description,
                    'properties' => $log->properties,
                    'created_at' => $log->created_at?->toDateTimeString(),
                    'user' => $log->user?->only(['id', 'name']),
                ]),
            ],
            'can' => [
                'update' => request()->user()->can('update', $entity),
                'delete' => request()->user()->can('delete', $entity),
            ],
        ]);
    }

    public function edit(Entity $entity): Response
    {
        Gate::authorize('update', $entity);

        return Inertia::render('entities/Edit', [
            'entity' => $entity->only([
                'id',
                'name',
                'vat',
                'email',
                'phone',
                'address',
                'status',
                'notes',
            ]),
            'statuses' => Entity::STATUSES,
        ]);
    }

    public function update(UpdateEntityRequest $request, Entity $entity): RedirectResponse
    {
        $entity->update([
            ...$request->validated(),
            'status' => $request->validated('status') ?: Entity::STATUS_ACTIVE,
        ]);

        return redirect()
            ->route('entities.show', $entity)
            ->with('success', 'Entidade atualizada com sucesso.');
    }

    public function destroy(Entity $entity): RedirectResponse
    {
        Gate::authorize('delete', $entity);

        $entity->delete();

        return redirect()
            ->route('entities.index')
            ->with('success', 'Entidade apagada com sucesso.');
    }
}
