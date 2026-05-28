<?php

namespace App\Services\AI;

use App\Models\ActivityLog;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\DealNote;
use App\Models\DealStage;
use App\Models\Entity;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductStatsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CRMQueryExecutorService
{
    public function __construct(private readonly ProductStatsService $productStats) {}

    /**
     * @param  array{intent:string,parameters:array<string,mixed>}  $intent
     * @return array<string,mixed>
     */
    public function execute(User $user, array $intent): array
    {
        $tenantId = (int) $user->current_tenant_id;
        $parameters = $intent['parameters'] ?? [];

        return match ($intent['intent']) {
            'deal_volume_by_stage' => $this->dealVolumeByStage($tenantId, $parameters),
            'deal_count_by_stage' => $this->dealCountByStage($tenantId, $parameters),
            'find_person_phone' => $this->findPersonContact($tenantId, $parameters, 'phone'),
            'find_person_email' => $this->findPersonContact($tenantId, $parameters, 'email'),
            'find_entity_contacts' => $this->findEntityContacts($tenantId, $parameters),
            'deals_closing_soon' => $this->dealsClosingSoon($tenantId, $parameters),
            'inactive_deals' => $this->inactiveDeals($tenantId, $parameters),
            'top_products_by_quantity' => $this->topProducts($tenantId, $parameters, 'quantity'),
            'top_products_by_value' => $this->topProducts($tenantId, $parameters, 'value'),
            'open_deals_by_owner' => $this->openDealsByOwner($tenantId, $parameters),
            'create_deal_note' => $this->confirmationRequired('Posso criar uma nota no negocio, mas preciso que confirme a acao.', 'create_note'),
            'create_calendar_activity' => $this->confirmationRequired('Posso criar uma atividade no calendario, mas preciso que confirme a acao.', 'create_activity'),
            default => $this->help(),
        };
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function executeAction(User $user, string $type, array $payload): array
    {
        $tenantId = (int) $user->current_tenant_id;

        if (! in_array($user->roleForTenant(), [Tenant::ROLE_OWNER, Tenant::ROLE_MANAGER, Tenant::ROLE_SALES], true)) {
            abort(403);
        }

        if ($type === 'create_note') {
            $deal = Deal::where('tenant_id', $tenantId)->findOrFail((int) ($payload['deal_id'] ?? 0));
            Gate::authorize('update', $deal);

            $note = DealNote::create([
                'tenant_id' => $tenantId,
                'deal_id' => $deal->id,
                'user_id' => $user->id,
                'body' => Str::limit((string) ($payload['body'] ?? 'Nota criada pelo Chat CRM.'), 5000, ''),
            ]);

            $deal->forceFill(['last_activity_at' => now()])->save();
            $this->log($tenantId, $user->id, 'ai_chat.action_created', 'ai_chat', $note, 'Nota criada pelo Chat CRM.', [
                'action_type' => $type,
                'deal_id' => $deal->id,
            ]);

            return [
                'answer_text' => 'Nota adicionada ao negocio '.$deal->title.'.',
                'created_records' => [['type' => 'deal_note', 'id' => $note->id]],
                'actions' => [$this->openRecordAction('Abrir negocio', route('deals.show', $deal, false))],
            ];
        }

        if ($type === 'create_activity') {
            $deal = Deal::where('tenant_id', $tenantId)->findOrFail((int) ($payload['deal_id'] ?? 0));
            Gate::authorize('update', $deal);
            $ownerId = (int) ($payload['owner_id'] ?? $deal->owner_id);
            abort_unless($user->belongsToTenant($tenantId) && User::whereKey($ownerId)->whereHas('tenants', fn ($query) => $query->whereKey($tenantId))->exists(), 422);

            $event = CalendarEvent::create([
                'tenant_id' => $tenantId,
                'deal_id' => $deal->id,
                'eventable_type' => Deal::class,
                'eventable_id' => $deal->id,
                'owner_id' => $ownerId,
                'title' => Str::limit((string) ($payload['title'] ?? 'Atividade criada pelo Chat CRM'), 255, ''),
                'description' => $payload['description'] ?? null,
                'notes' => $payload['description'] ?? null,
                'type' => in_array($payload['activity_type'] ?? null, CalendarEvent::TYPES, true) ? $payload['activity_type'] : CalendarEvent::TYPE_TASK,
                'status' => CalendarEvent::STATUS_PENDING,
                'priority' => in_array($payload['priority'] ?? null, CalendarEvent::PRIORITIES, true) ? $payload['priority'] : ($deal->priority ?? CalendarEvent::PRIORITY_MEDIUM),
                'start_at' => Carbon::parse($payload['start_at'] ?? now()->addDay()),
                'starts_at' => Carbon::parse($payload['start_at'] ?? now()->addDay()),
                'end_at' => isset($payload['end_at']) ? Carbon::parse($payload['end_at']) : null,
                'ends_at' => isset($payload['end_at']) ? Carbon::parse($payload['end_at']) : null,
            ]);

            $deal->forceFill(['last_activity_at' => now()])->save();
            $this->log($tenantId, $user->id, 'ai_chat.action_created', 'ai_chat', $event, 'Atividade criada pelo Chat CRM.', [
                'action_type' => $type,
                'deal_id' => $deal->id,
            ]);

            return [
                'answer_text' => 'Atividade criada no calendario para o negocio '.$deal->title.'.',
                'created_records' => [['type' => 'calendar_event', 'id' => $event->id]],
                'actions' => [
                    $this->openRecordAction('Abrir atividade', route('calendar-events.show', $event, false)),
                    $this->openRecordAction('Abrir negocio', route('deals.show', $deal, false)),
                ],
            ];
        }

        abort(422, 'Acao nao suportada.');
    }

    /**
     * @param  array<string,mixed>  $parameters
     * @return array<string,mixed>
     */
    private function dealVolumeByStage(int $tenantId, array $parameters): array
    {
        $stage = $this->resolveStage($tenantId, $parameters['stage'] ?? null);

        if (! $stage) {
            return $this->notFound('Nao encontrei essa etapa no pipeline.');
        }

        $query = Deal::query()->where('tenant_id', $tenantId)->where('deal_stage_id', $stage->id);
        $count = (clone $query)->count();
        $total = (float) (clone $query)->sum('value');

        return [
            'answer_text' => "O volume de negocios no estado {$stage->name} e ".$this->money($total)." em {$count} negocio(s).",
            'records' => [[
                'type' => 'deal_stage',
                'id' => $stage->id,
                'title' => $stage->name,
                'subtitle' => $count.' negocio(s) - '.$this->money($total),
                'url' => route('deals.index', ['deal_stage_id' => $stage->id], false),
            ]],
            'actions' => [$this->openRecordAction('Abrir lista de negocios', route('deals.index', ['deal_stage_id' => $stage->id], false))],
            'metadata' => ['total_value' => $total, 'deals_count' => $count],
        ];
    }

    /**
     * @param  array<string,mixed>  $parameters
     * @return array<string,mixed>
     */
    private function dealCountByStage(int $tenantId, array $parameters): array
    {
        $stage = $this->resolveStage($tenantId, $parameters['stage'] ?? null);

        if (! $stage) {
            return $this->notFound('Nao encontrei essa etapa no pipeline.');
        }

        $count = Deal::query()->where('tenant_id', $tenantId)->where('deal_stage_id', $stage->id)->count();

        return [
            'answer_text' => "Existem {$count} negocio(s) no estado {$stage->name}.",
            'records' => [],
            'actions' => [$this->openRecordAction('Abrir lista de negocios', route('deals.index', ['deal_stage_id' => $stage->id], false))],
            'metadata' => ['deals_count' => $count],
        ];
    }

    /**
     * @param  array<string,mixed>  $parameters
     * @return array<string,mixed>
     */
    private function findPersonContact(int $tenantId, array $parameters, string $field): array
    {
        $name = trim((string) ($parameters['person_name'] ?? $parameters['name'] ?? ''));
        $people = Person::query()
            ->with('entity:id,name')
            ->where('tenant_id', $tenantId)
            ->when($name !== '', fn ($query) => $query->where('name', 'like', '%'.$name.'%'))
            ->limit(5)
            ->get();

        if ($people->isEmpty()) {
            return $this->notFound('Nao encontrei essa pessoa neste tenant.');
        }

        $records = $people->map(fn (Person $person) => [
            'type' => 'person',
            'id' => $person->id,
            'title' => $person->name,
            'subtitle' => ($field === 'phone' ? ($person->phone ?: 'Sem telefone') : ($person->email ?: 'Sem email')).($person->entity ? ' - '.$person->entity->name : ''),
            'url' => route('people.show', $person, false),
        ])->values()->all();

        $first = $people->first();
        $value = $field === 'phone' ? ($first->phone ?: 'sem telefone registado') : ($first->email ?: 'sem email registado');

        return [
            'answer_text' => "{$first->name} tem {$value}.",
            'records' => $records,
            'actions' => [$this->openRecordAction('Abrir pessoa', route('people.show', $first, false))],
            'metadata' => ['matches' => $people->count()],
        ];
    }

    /**
     * @param  array<string,mixed>  $parameters
     * @return array<string,mixed>
     */
    private function findEntityContacts(int $tenantId, array $parameters): array
    {
        $name = trim((string) ($parameters['entity_name'] ?? $parameters['name'] ?? ''));
        $entity = Entity::query()
            ->with('people:id,tenant_id,entity_id,name,email,phone')
            ->where('tenant_id', $tenantId)
            ->when($name !== '', fn ($query) => $query->where('name', 'like', '%'.$name.'%'))
            ->first();

        if (! $entity) {
            return $this->notFound('Nao encontrei essa entidade neste tenant.');
        }

        $records = $entity->people->map(fn (Person $person) => [
            'type' => 'person',
            'id' => $person->id,
            'title' => $person->name,
            'subtitle' => trim(($person->email ?: '').' '.($person->phone ?: '')),
            'url' => route('people.show', $person, false),
        ])->values()->all();

        return [
            'answer_text' => $entity->people->isEmpty()
                ? "A entidade {$entity->name} ainda nao tem pessoas associadas."
                : "Encontrei {$entity->people->count()} contacto(s) associados a {$entity->name}.",
            'records' => $records,
            'actions' => [$this->openRecordAction('Abrir entidade', route('entities.show', $entity, false))],
            'metadata' => ['entity_id' => $entity->id],
        ];
    }

    /**
     * @param  array<string,mixed>  $parameters
     * @return array<string,mixed>
     */
    private function dealsClosingSoon(int $tenantId, array $parameters): array
    {
        $days = max(1, (int) ($parameters['days'] ?? 14));
        $openStageIds = $this->openStageIds($tenantId);
        $deals = Deal::query()
            ->with(['stage:id,name', 'owner:id,name'])
            ->where('tenant_id', $tenantId)
            ->whereIn('deal_stage_id', $openStageIds)
            ->whereBetween('expected_close_date', [now()->toDateString(), now()->addDays($days)->toDateString()])
            ->orderBy('expected_close_date')
            ->limit(8)
            ->get();

        return [
            'answer_text' => $deals->isEmpty()
                ? "Nao ha negocios com fecho previsto nos proximos {$days} dias."
                : "Encontrei {$deals->count()} negocio(s) com fecho previsto nos proximos {$days} dias.",
            'records' => $this->dealRecords($deals),
            'actions' => [$this->openRecordAction('Abrir negocios', route('deals.index', false))],
            'metadata' => ['days' => $days],
        ];
    }

    /**
     * @param  array<string,mixed>  $parameters
     * @return array<string,mixed>
     */
    private function inactiveDeals(int $tenantId, array $parameters): array
    {
        $days = max(1, (int) ($parameters['days'] ?? 7));
        $threshold = now()->subDays($days);
        $openStageIds = $this->openStageIds($tenantId);
        $deals = Deal::query()
            ->with(['stage:id,name', 'owner:id,name'])
            ->where('tenant_id', $tenantId)
            ->whereIn('deal_stage_id', $openStageIds)
            ->where(function ($query) use ($threshold) {
                $query->whereNull('last_activity_at')->where('created_at', '<=', $threshold)
                    ->orWhere('last_activity_at', '<=', $threshold);
            })
            ->orderByRaw('COALESCE(last_activity_at, created_at) asc')
            ->limit(8)
            ->get();

        return [
            'answer_text' => $deals->isEmpty()
                ? "Nao encontrei negocios sem atividade ha mais de {$days} dias."
                : "Encontrei {$deals->count()} negocio(s) sem atividade ha mais de {$days} dias.",
            'records' => $this->dealRecords($deals),
            'actions' => [$this->openRecordAction('Abrir negocios', route('deals.index', false))],
            'metadata' => ['days' => $days],
        ];
    }

    /**
     * @param  array<string,mixed>  $parameters
     * @return array<string,mixed>
     */
    private function topProducts(int $tenantId, array $parameters, string $sort): array
    {
        $limit = min(10, max(1, (int) ($parameters['limit'] ?? 5)));
        $rows = $this->productStats->rows($tenantId, ['sort' => $sort === 'quantity' ? 'quantity' : 'value'])
            ->take($limit);

        return [
            'answer_text' => $rows->isEmpty()
                ? 'Ainda nao ha produtos associados a negocios.'
                : 'Estes sao os produtos com maior '.($sort === 'quantity' ? 'quantidade' : 'valor').' no pipeline.',
            'records' => $rows->map(fn (object $row) => [
                'type' => 'product',
                'id' => $row->product_id,
                'title' => $row->product_name,
                'subtitle' => number_format((float) $row->total_quantity, 2, ',', '.').' unidades - '.$this->money((float) $row->total_value),
                'url' => route('products.show', $row->product_id, false),
            ])->values()->all(),
            'actions' => [$this->openRecordAction('Abrir estatisticas de produtos', route('product-stats.index', false))],
            'metadata' => ['sort' => $sort],
        ];
    }

    /**
     * @param  array<string,mixed>  $parameters
     * @return array<string,mixed>
     */
    private function openDealsByOwner(int $tenantId, array $parameters): array
    {
        $openStageIds = $this->openStageIds($tenantId);
        $deals = Deal::query()
            ->with(['owner:id,name', 'stage:id,name'])
            ->where('tenant_id', $tenantId)
            ->whereIn('deal_stage_id', $openStageIds)
            ->latest()
            ->limit(8)
            ->get();

        return [
            'answer_text' => $deals->isEmpty()
                ? 'Nao ha negocios abertos para mostrar por responsavel.'
                : 'Aqui esta uma amostra de negocios abertos por responsavel.',
            'records' => $this->dealRecords($deals),
            'actions' => [$this->openRecordAction('Abrir negocios', route('deals.index', false))],
            'metadata' => [],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function confirmationRequired(string $answer, string $actionType): array
    {
        return [
            'answer_text' => $answer,
            'records' => [],
            'actions' => [[
                'type' => $actionType,
                'label' => $actionType === 'create_note' ? 'Adicionar nota' : 'Criar atividade',
                'requires_confirmation' => true,
                'payload' => [],
            ]],
            'metadata' => ['requires_action_confirmation' => true],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function help(): array
    {
        return [
            'answer_text' => 'Posso responder sobre volume e contagem de negocios por etapa, contactos de pessoas, negocios sem atividade, fechos previstos e produtos mais relevantes.',
            'records' => [],
            'actions' => [],
            'metadata' => [],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function notFound(string $message): array
    {
        return [
            'answer_text' => $message,
            'records' => [],
            'actions' => [],
            'metadata' => [],
        ];
    }

    private function resolveStage(int $tenantId, mixed $stageName): ?DealStage
    {
        if (! is_string($stageName) || trim($stageName) === '') {
            return DealStage::query()->where('tenant_id', $tenantId)->where('slug', DealStage::SLUG_NEGOTIATION)->first();
        }

        $stageName = trim($stageName);
        $slug = Str::slug(Str::ascii($stageName));
        $knownSlugs = [
            'negociacao' => DealStage::SLUG_NEGOTIATION,
            'em-negociacao' => DealStage::SLUG_NEGOTIATION,
            'proposta' => DealStage::SLUG_PROPOSAL,
            'follow-up' => DealStage::SLUG_FOLLOW_UP,
            'lead' => DealStage::SLUG_LEAD,
            'ganho' => DealStage::SLUG_WON,
            'perdido' => DealStage::SLUG_LOST,
        ];
        $slug = $knownSlugs[$slug] ?? $slug;

        return DealStage::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($stageName, $slug) {
                $query->where('name', 'like', '%'.$stageName.'%')
                    ->orWhere('slug', $slug)
                    ->orWhere('slug', str_replace('-', '_', $slug));
            })
            ->first();
    }

    /**
     * @return array<int,int>
     */
    private function openStageIds(int $tenantId): array
    {
        return DealStage::query()
            ->where('tenant_id', $tenantId)
            ->where('is_won', false)
            ->where('is_lost', false)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  iterable<Deal>  $deals
     * @return array<int,array<string,mixed>>
     */
    private function dealRecords(iterable $deals): array
    {
        $records = [];

        foreach ($deals as $deal) {
            $records[] = [
                'type' => 'deal',
                'id' => $deal->id,
                'title' => $deal->title,
                'subtitle' => $this->money((float) $deal->value).' - '.($deal->stage?->name ?? 'Sem etapa'),
                'url' => route('deals.show', $deal, false),
            ];
        }

        return $records;
    }

    /**
     * @return array<string,mixed>
     */
    private function openRecordAction(string $label, string $url): array
    {
        return [
            'type' => 'open_record',
            'label' => $label,
            'url' => $url,
            'requires_confirmation' => false,
        ];
    }

    private function money(float $value): string
    {
        return number_format($value, 2, ',', '.').' EUR';
    }

    /**
     * @param  array<string,mixed>  $properties
     */
    private function log(int $tenantId, ?int $userId, string $action, string $module, object $subject, string $description, array $properties = []): void
    {
        ActivityLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'subject_type' => $subject::class,
            'subject_id' => $subject->id ?? null,
            'description' => $description,
            'properties' => $properties,
        ]);
    }
}
