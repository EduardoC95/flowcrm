<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveDealStageRequest;
use App\Http\Requests\StoreDealRequest;
use App\Http\Requests\UpdateDealRequest;
use App\Models\Deal;
use App\Models\DealFollowUpEmail;
use App\Models\DealProposal;
use App\Models\DealStage;
use App\Models\Entity;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\FollowUpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DealController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Deal::class);
        $this->ensureStages($request);

        $filters = $this->validatedFilters($request);
        $deals = $this->dealQuery($filters)
            ->orderBy($filters['sort'], $filters['direction'])
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Deal $deal) => $this->dealRow($deal));

        return Inertia::render('deals/Index', [
            'deals' => $deals,
            'filters' => $filters,
            'stages' => $this->stageOptions(),
            'entities' => $this->entityOptions(),
            'people' => $this->personOptions(),
            'owners' => $this->ownerOptions($request),
            'priorities' => Deal::PRIORITIES,
            'can' => [
                'create' => $request->user()->can('create', Deal::class),
            ],
        ]);
    }

    public function board(Request $request): Response
    {
        Gate::authorize('viewAny', Deal::class);
        $this->ensureStages($request);

        $filters = $request->validate([
            'owner_id' => ['nullable', 'integer'],
            'expected_close_date_from' => ['nullable', 'date'],
            'expected_close_date_to' => ['nullable', 'date'],
            'min_value' => ['nullable', 'numeric', 'min:0'],
            'max_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $stages = DealStage::query()
            ->orderBy('position')
            ->get()
            ->map(function (DealStage $stage) use ($filters) {
                $deals = $this->dealQuery([
                    ...$this->emptyFilters(),
                    ...$filters,
                    'stage_id' => $stage->id,
                ])
                    ->orderBy('expected_close_date')
                    ->get()
                    ->map(fn (Deal $deal) => $this->dealRow($deal));

                return [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'slug' => $stage->slug,
                    'color' => $stage->color,
                    'position' => $stage->position,
                    'is_won' => $stage->is_won,
                    'is_lost' => $stage->is_lost,
                    'deals_count' => $deals->count(),
                    'total_value' => round($deals->sum('value'), 2),
                    'deals' => $deals,
                ];
            });

        return Inertia::render('deals/Board', [
            'stages' => $stages,
            'filters' => [
                'owner_id' => $filters['owner_id'] ?? null,
                'expected_close_date_from' => $filters['expected_close_date_from'] ?? null,
                'expected_close_date_to' => $filters['expected_close_date_to'] ?? null,
                'min_value' => $filters['min_value'] ?? null,
                'max_value' => $filters['max_value'] ?? null,
            ],
            'owners' => $this->ownerOptions($request),
            'can' => [
                'move' => $request->user()->can('create', Deal::class),
                'create' => $request->user()->can('create', Deal::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Deal::class);
        $this->ensureStages($request);

        return Inertia::render('deals/Create', [
            ...$this->formOptions($request),
            'defaults' => [
                'owner_id' => $request->user()->id,
                'deal_stage_id' => DealStage::query()->orderBy('position')->value('id'),
            ],
        ]);
    }

    public function store(StoreDealRequest $request): RedirectResponse
    {
        $deal = Deal::create($this->normalizedDealData($request->validated()));

        return redirect()
            ->route('deals.show', $deal)
            ->with('success', 'Negócio criado com sucesso.');
    }

    public function show(Deal $deal): Response
    {
        Gate::authorize('view', $deal);

        $deal->load([
            'entity:id,name,email,phone,status',
            'person:id,entity_id,name,email,phone,position,status',
            'owner:id,name,email',
            'stage:id,name,slug,color,is_won,is_lost',
            'proposals' => fn ($query) => $query
                ->latest()
                ->with(['uploader:id,name', 'sender:id,name']),
            'activeFollowUp',
            'followUpEmails' => fn ($query) => $query
                ->latest('sent_at')
                ->with(['template:id,name', 'sender:id,name']),
            'calendarEvents:id,tenant_id,entity_id,person_id,deal_id,eventable_type,eventable_id,title,type,status,start_at,end_at,starts_at,ends_at,location',
            'activityLogs' => fn ($query) => $query
                ->latest()
                ->limit(20)
                ->with('user:id,name'),
        ]);

        return Inertia::render('deals/Show', [
            'deal' => [
                ...$this->dealRow($deal),
                'description' => $deal->description,
                'last_activity_at' => $deal->last_activity_at?->toDateTimeString(),
                'created_at' => $deal->created_at?->toDateTimeString(),
                'updated_at' => $deal->updated_at?->toDateTimeString(),
                'proposals' => $deal->proposals->map(fn (DealProposal $proposal) => [
                    'id' => $proposal->id,
                    'original_name' => $proposal->original_name,
                    'mime_type' => $proposal->mime_type,
                    'size' => $proposal->size,
                    'status' => $proposal->status,
                    'created_at' => $proposal->created_at?->toDateTimeString(),
                    'sent_at' => $proposal->sent_at?->toDateTimeString(),
                    'recipient_email' => $proposal->recipient_email,
                    'email_subject' => $proposal->email_subject,
                    'uploader' => $proposal->uploader?->only(['id', 'name']),
                    'sender' => $proposal->sender?->only(['id', 'name']),
                    'download_url' => route('deals.proposals.download', [$deal, $proposal]),
                ]),
                'follow_up' => $deal->activeFollowUp ? [
                    'id' => $deal->activeFollowUp->id,
                    'status' => $deal->activeFollowUp->status,
                    'next_send_at' => $deal->activeFollowUp->next_send_at?->toDateTimeString(),
                    'last_sent_at' => $deal->activeFollowUp->last_sent_at?->toDateTimeString(),
                    'sent_count' => $deal->activeFollowUp->sent_count,
                    'cancelled_at' => $deal->activeFollowUp->cancelled_at?->toDateTimeString(),
                    'replied_at' => $deal->activeFollowUp->replied_at?->toDateTimeString(),
                ] : null,
                'follow_up_emails' => $deal->followUpEmails->map(fn (DealFollowUpEmail $email) => [
                    'id' => $email->id,
                    'recipient_email' => $email->recipient_email,
                    'subject' => $email->subject,
                    'body' => $email->body,
                    'sent_at' => $email->sent_at?->toDateTimeString(),
                    'template' => $email->template?->only(['id', 'name']),
                    'sender' => $email->sender?->only(['id', 'name']),
                ]),
                'calendar_events' => $deal->calendarEvents->map(fn ($event) => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'starts_at' => ($event->start_at ?? $event->starts_at)?->toDateTimeString(),
                    'ends_at' => ($event->end_at ?? $event->ends_at)?->toDateTimeString(),
                    'type' => $event->type,
                    'status' => $event->status,
                    'location' => $event->location,
                ]),
                'activity_logs' => $deal->activityLogs->map(fn ($log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'description' => $log->description,
                    'properties' => $log->properties,
                    'created_at' => $log->created_at?->toDateTimeString(),
                    'user' => $log->user?->only(['id', 'name']),
                ]),
            ],
            'can' => [
                'update' => request()->user()->can('update', $deal),
                'delete' => request()->user()->can('delete', $deal),
                'manageProposals' => request()->user()->can('create', [DealProposal::class, $deal]),
                'manageFollowUp' => request()->user()->can('update', $deal),
            ],
        ]);
    }

    public function edit(Request $request, Deal $deal): Response
    {
        Gate::authorize('update', $deal);
        $this->ensureStages($request);

        return Inertia::render('deals/Edit', [
            'deal' => [
                'id' => $deal->id,
                'entity_id' => $deal->entity_id,
                'person_id' => $deal->person_id,
                'owner_id' => $deal->owner_id,
                'deal_stage_id' => $deal->deal_stage_id,
                'title' => $deal->title,
                'value' => $deal->value,
                'probability' => $deal->probability,
                'expected_close_date' => $deal->expected_close_date?->toDateString(),
                'priority' => $deal->priority,
                'description' => $deal->description,
            ],
            ...$this->formOptions($request),
        ]);
    }

    public function update(UpdateDealRequest $request, Deal $deal): RedirectResponse
    {
        $deal->update($this->normalizedDealData($request->validated()));

        return redirect()
            ->route('deals.show', $deal)
            ->with('success', 'Negócio atualizado com sucesso.');
    }

    public function destroy(Deal $deal): RedirectResponse
    {
        Gate::authorize('delete', $deal);

        $deal->delete();

        return redirect()
            ->route('deals.index')
            ->with('success', 'Negócio apagado com sucesso.');
    }

    public function moveStage(
        MoveDealStageRequest $request,
        Deal $deal,
        ActivityLogger $logger,
        FollowUpService $followUpService,
    ): JsonResponse|RedirectResponse
    {
        $previousStage = $deal->stage()->first();
        $newStage = DealStage::findOrFail($request->validated('deal_stage_id'));

        $deal->forceFill([
            'deal_stage_id' => $newStage->id,
            'stage' => $newStage->slug,
            'last_activity_at' => now(),
        ])->save();

        $logger->log(
            'deal.stage_moved',
            'deals',
            $deal->tenant_id,
            $deal,
            'Deal moved between pipeline stages.',
            [
                'previous_stage_id' => $previousStage?->id,
                'previous_stage_slug' => $previousStage?->slug,
                'previous_stage_name' => $previousStage?->name,
                'new_stage_id' => $newStage->id,
                'new_stage_slug' => $newStage->slug,
                'new_stage_name' => $newStage->name,
            ],
        );

        $followUpMessage = $this->syncFollowUpAutomation($deal, $previousStage, $newStage, $followUpService, $request->user());
        $message = trim('Negócio movido com sucesso. '.($followUpMessage ?? ''));

        if ($followUpMessage && ! $request->expectsJson()) {
            return back()->with('success', $message);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Negócio movido com sucesso.',
                'deal' => $this->dealRow($deal->fresh(['entity:id,name', 'person:id,name', 'owner:id,name', 'stage:id,name,slug,color'])),
            ]);
        }

        return back()->with('success', 'Negócio movido com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        return [
            ...$this->emptyFilters(),
            ...$request->validate([
                'search' => ['nullable', 'string', 'max:255'],
                'stage_id' => ['nullable', 'integer'],
                'entity_id' => ['nullable', 'integer'],
                'person_id' => ['nullable', 'integer'],
                'owner_id' => ['nullable', 'integer'],
                'expected_close_date_from' => ['nullable', 'date'],
                'expected_close_date_to' => ['nullable', 'date'],
                'min_value' => ['nullable', 'numeric', 'min:0'],
                'max_value' => ['nullable', 'numeric', 'min:0'],
                'sort' => ['nullable', 'in:title,value,expected_close_date,created_at'],
                'direction' => ['nullable', 'in:asc,desc'],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyFilters(): array
    {
        return [
            'search' => null,
            'stage_id' => null,
            'entity_id' => null,
            'person_id' => null,
            'owner_id' => null,
            'expected_close_date_from' => null,
            'expected_close_date_to' => null,
            'min_value' => null,
            'max_value' => null,
            'sort' => 'expected_close_date',
            'direction' => 'asc',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function dealQuery(array $filters)
    {
        return Deal::query()
            ->with(['entity:id,name', 'person:id,name', 'owner:id,name', 'stage:id,name,slug,color,is_won,is_lost', 'activeFollowUp'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($filters['stage_id'] ?? null, fn ($query, int $stageId) => $query->where('deal_stage_id', $stageId))
            ->when($filters['entity_id'] ?? null, fn ($query, int $entityId) => $query->where('entity_id', $entityId))
            ->when($filters['person_id'] ?? null, fn ($query, int $personId) => $query->where('person_id', $personId))
            ->when($filters['owner_id'] ?? null, fn ($query, int $ownerId) => $query->where('owner_id', $ownerId))
            ->when($filters['expected_close_date_from'] ?? null, fn ($query, string $date) => $query->whereDate('expected_close_date', '>=', $date))
            ->when($filters['expected_close_date_to'] ?? null, fn ($query, string $date) => $query->whereDate('expected_close_date', '<=', $date))
            ->when($filters['min_value'] ?? null, fn ($query, string|float|int $value) => $query->where('value', '>=', $value))
            ->when($filters['max_value'] ?? null, fn ($query, string|float|int $value) => $query->where('value', '<=', $value));
    }

    /**
     * @return array<string, mixed>
     */
    private function dealRow(Deal $deal): array
    {
        $stage = $deal->relationLoaded('stage') ? $deal->getRelation('stage') : $deal->stage()->first();
        $activeFollowUp = $deal->relationLoaded('activeFollowUp')
            ? $deal->getRelation('activeFollowUp')
            : $deal->activeFollowUp()->first();

        return [
            'id' => $deal->id,
            'title' => $deal->title,
            'value' => (float) $deal->value,
            'probability' => $deal->probability,
            'expected_close_date' => $deal->expected_close_date?->toDateString(),
            'priority' => $deal->priority,
            'entity' => $deal->entity?->only(['id', 'name']),
            'person' => $deal->person?->only(['id', 'name']),
            'owner' => $deal->owner?->only(['id', 'name']),
            'stage' => $stage ? [
                'id' => $stage->id,
                'name' => $stage->name,
                'slug' => $stage->slug,
                'color' => $stage->color,
                'is_won' => $stage->is_won,
                'is_lost' => $stage->is_lost,
            ] : null,
            'active_follow_up' => $activeFollowUp ? [
                'id' => $activeFollowUp->id,
                'next_send_at' => $activeFollowUp->next_send_at?->toDateTimeString(),
                'last_sent_at' => $activeFollowUp->last_sent_at?->toDateTimeString(),
                'sent_count' => $activeFollowUp->sent_count,
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizedDealData(array $data): array
    {
        $person = isset($data['person_id']) ? Person::find($data['person_id']) : null;
        $stage = DealStage::find($data['deal_stage_id']);

        if (empty($data['entity_id']) && $person?->entity_id) {
            $data['entity_id'] = $person->entity_id;
        }

        return [
            ...$data,
            'entity_id' => $data['entity_id'] ?? null,
            'person_id' => $data['person_id'] ?? null,
            'value' => $data['value'] ?? 0,
            'probability' => $data['probability'] ?? 0,
            'priority' => $data['priority'] ?? null,
            'stage' => $stage?->slug ?? DealStage::SLUG_LEAD,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        return [
            'entities' => $this->entityOptions(),
            'people' => $this->personOptions(),
            'stages' => $this->stageOptions(),
            'owners' => $this->ownerOptions($request),
            'priorities' => Deal::PRIORITIES,
        ];
    }

    private function entityOptions()
    {
        return Entity::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Entity $entity) => $entity->only(['id', 'name']));
    }

    private function personOptions()
    {
        return Person::query()
            ->with('entity:id,name')
            ->orderBy('name')
            ->get(['id', 'entity_id', 'name'])
            ->map(fn (Person $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'entity_id' => $person->entity_id,
                'entity_name' => $person->entity?->name,
            ]);
    }

    private function stageOptions()
    {
        return DealStage::query()
            ->orderBy('position')
            ->get(['id', 'name', 'slug', 'color', 'position', 'is_won', 'is_lost']);
    }

    private function ownerOptions(Request $request)
    {
        $tenant = $request->user()->currentTenant;

        return $tenant?->users()
            ->orderBy('name')
            ->get(['users.id', 'users.name'])
            ->map(fn (User $user) => $user->only(['id', 'name'])) ?? collect();
    }

    private function ensureStages(Request $request): void
    {
        $tenant = $request->user()->currentTenant;

        if ($tenant instanceof Tenant && $tenant->dealStages()->count() === 0) {
            DealStage::ensureDefaultStages($tenant);
        }
    }

    private function syncFollowUpAutomation(
        Deal $deal,
        ?DealStage $previousStage,
        DealStage $newStage,
        FollowUpService $followUpService,
        ?User $user,
    ): ?string
    {
        if ($newStage->slug === DealStage::SLUG_FOLLOW_UP && $previousStage?->slug !== DealStage::SLUG_FOLLOW_UP) {
            $followUpService->startForDeal($deal->fresh(['stage']), $user);

            return 'Follow-up automático iniciado.';
        }

        if ($previousStage?->slug === DealStage::SLUG_FOLLOW_UP && $newStage->slug !== DealStage::SLUG_FOLLOW_UP) {
            $followUpService->cancelForDeal($deal, 'Negócio saiu do estado Follow Up', $user);

            return 'Follow-up automático cancelado.';
        }

        return null;
    }
}
