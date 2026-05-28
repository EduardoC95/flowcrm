<?php

namespace App\Services\AI;

use App\Models\ActivityLog;
use App\Models\AISuggestion;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\DealNote;
use App\Models\DealProposal;
use App\Models\DealStage;
use App\Models\InternalNotification;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OpenAI\OpenAIService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CommercialAgentService
{
    private const INACTIVITY_DAYS = 7;

    private const PROPOSAL_FOLLOW_UP_DAYS = 3;

    private const HIGH_VALUE_THRESHOLD = 5000;

    public function __construct(private readonly OpenAIService $openAI) {}

    /**
     * @return array<string,int>
     */
    public function analyzeTenant(Tenant $tenant): array
    {
        $this->log($tenant->id, null, 'commercial_agent.analysis_started', 'ai_suggestions', null, 'Analise comercial iniciada.', [
            'tenant_id' => $tenant->id,
        ]);

        $created = 0;
        $duplicates = 0;
        $deals = Deal::withoutGlobalScopes()
            ->with(['stage', 'owner', 'person', 'entity', 'proposals', 'calendarEvents', 'dealNotes'])
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->get();

        foreach ($deals as $deal) {
            $result = $this->analyzeDeal($deal);
            $created += $result['created'];
            $duplicates += $result['duplicates'];
        }

        $this->log($tenant->id, null, 'commercial_agent.analysis_completed', 'ai_suggestions', null, 'Analise comercial concluida.', [
            'deals_analyzed' => $deals->count(),
            'suggestions_created' => $created,
            'duplicates' => $duplicates,
        ]);

        return [
            'tenants' => 1,
            'deals' => $deals->count(),
            'created' => $created,
            'duplicates' => $duplicates,
        ];
    }

    /**
     * @return array<string,int>
     */
    public function analyzeUser(User $user): array
    {
        $tenant = $user->currentTenant;

        if (! $tenant) {
            return ['created' => 0, 'duplicates' => 0, 'deals' => 0];
        }

        $created = 0;
        $duplicates = 0;
        $deals = Deal::withoutGlobalScopes()
            ->with(['stage', 'owner', 'person', 'entity', 'proposals', 'calendarEvents', 'dealNotes'])
            ->where('tenant_id', $tenant->id)
            ->where('owner_id', $user->id)
            ->whereNull('deleted_at')
            ->get();

        foreach ($deals as $deal) {
            $result = $this->analyzeDeal($deal);
            $created += $result['created'];
            $duplicates += $result['duplicates'];
        }

        return ['created' => $created, 'duplicates' => $duplicates, 'deals' => $deals->count()];
    }

    /**
     * @return array<string,int>
     */
    public function analyzeDeal(Deal $deal, string $source = 'daily_analysis'): array
    {
        $deal->loadMissing(['stage', 'owner', 'person', 'entity', 'proposals', 'calendarEvents', 'dealNotes']);

        if (! $deal->owner_id || $this->isClosedDeal($deal)) {
            return ['created' => 0, 'duplicates' => 0];
        }

        $created = 0;
        $duplicates = 0;

        foreach ($this->suggestionCandidatesForDeal($deal, $source) as $candidate) {
            if ($this->preventDuplicateSuggestion($candidate)) {
                $duplicates++;

                continue;
            }

            $this->createSuggestion($candidate);
            $created++;
        }

        return ['created' => $created, 'duplicates' => $duplicates];
    }

    /**
     * @return array<string,int>
     */
    public function analyzeRecentActivity(CalendarEvent $event): array
    {
        $event->loadMissing('deal');

        if (! $event->deal) {
            return ['created' => 0, 'duplicates' => 0];
        }

        AISuggestion::withoutGlobalScopes()
            ->where('tenant_id', $event->tenant_id)
            ->where('deal_id', $event->deal_id)
            ->whereIn('type', [AISuggestion::TYPE_NO_ACTIVITY, AISuggestion::TYPE_HIGH_VALUE_STALLED])
            ->whereIn('status', [AISuggestion::STATUS_PENDING, AISuggestion::STATUS_POSTPONED])
            ->update([
                'status' => AISuggestion::STATUS_ARCHIVED,
                'archived_at' => now(),
                'metadata' => ['archived_reason' => 'Nova atividade criada no negocio.'],
            ]);

        return $this->analyzeDeal($event->deal, 'realtime_activity_created');
    }

    /**
     * @return array<string,int>
     */
    public function analyzeNewPerson(Person $person): array
    {
        if ($person->status !== Person::STATUS_LEAD) {
            return ['created' => 0, 'duplicates' => 0];
        }

        $user = $this->tenantOwner($person->tenant_id);

        if (! $user) {
            return ['created' => 0, 'duplicates' => 0];
        }

        $data = [
            'tenant_id' => $person->tenant_id,
            'user_id' => $user->id,
            'person_id' => $person->id,
            'entity_id' => $person->entity_id,
            'type' => AISuggestion::TYPE_NEW_LEAD_NEEDS_FIRST_CONTACT,
            'title' => 'Fazer primeiro contacto com '.$person->name,
            'reason' => 'Esta lead foi criada recentemente e ainda precisa de primeiro contacto.',
            'suggested_action' => 'Criar tarefa de primeiro contacto',
            'suggested_due_at' => $this->nextBusinessDateTime(now()->addDay()),
            'priority' => AISuggestion::PRIORITY_HIGH,
            'status' => AISuggestion::STATUS_PENDING,
            'source' => 'realtime_person_created',
            'score' => $this->adjustScoreForUserHistory($user, AISuggestion::TYPE_NEW_LEAD_NEEDS_FIRST_CONTACT, 70),
            'metadata' => ['person_status' => $person->status],
        ];

        if ($this->preventDuplicateSuggestion($data)) {
            return ['created' => 0, 'duplicates' => 1];
        }

        $this->createSuggestion($data);

        return ['created' => 1, 'duplicates' => 0];
    }

    /**
     * @return array<string,int>
     */
    public function analyzeNewDeal(Deal $deal): array
    {
        return $this->analyzeDeal($deal, 'realtime_deal_created');
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function createSuggestion(array $data): AISuggestion
    {
        $suggestion = AISuggestion::create([
            ...$data,
            'status' => $data['status'] ?? AISuggestion::STATUS_PENDING,
            'priority' => $data['priority'] ?? AISuggestion::PRIORITY_MEDIUM,
            'score' => max(0, min(100, (int) ($data['score'] ?? 0))),
        ]);

        $this->log(
            $suggestion->tenant_id,
            $suggestion->user_id,
            'ai_suggestion.created',
            'ai_suggestions',
            $suggestion,
            'Sugestao comercial criada.',
            [
                'type' => $suggestion->type,
                'source' => $suggestion->source,
                'score' => $suggestion->score,
            ],
        );

        return $suggestion;
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function preventDuplicateSuggestion(array $data): bool
    {
        return AISuggestion::withoutGlobalScopes()
            ->where('tenant_id', $data['tenant_id'])
            ->where('user_id', $data['user_id'])
            ->where('type', $data['type'])
            ->when($data['deal_id'] ?? null, fn ($query, int $dealId) => $query->where('deal_id', $dealId))
            ->when($data['person_id'] ?? null, fn ($query, int $personId) => $query->where('person_id', $personId))
            ->when($data['entity_id'] ?? null, fn ($query, int $entityId) => $query->where('entity_id', $entityId))
            ->whereIn('status', [AISuggestion::STATUS_PENDING, AISuggestion::STATUS_POSTPONED])
            ->where('created_at', '>=', now()->subDays(7))
            ->exists();
    }

    public function buildReason(Deal $deal, string $type): string
    {
        $daysWithoutActivity = $this->daysWithoutActivity($deal);

        return match ($type) {
            AISuggestion::TYPE_NO_ACTIVITY => "Este negocio nao tem atividade recente ha {$daysWithoutActivity} dias.",
            AISuggestion::TYPE_PROPOSAL_SENT_NO_FOLLOWUP => 'Foi enviada uma proposta, mas ainda nao houve acompanhamento recente.',
            AISuggestion::TYPE_CLOSING_DATE_NEAR => 'A data prevista de fecho esta proxima.',
            AISuggestion::TYPE_HIGH_VALUE_STALLED => 'Este negocio tem valor relevante e esta sem atividade recente.',
            AISuggestion::TYPE_CLIENT_REQUESTED_INFO => 'Ha notas ou atividades a indicar que o cliente pediu informacao ou aguarda resposta.',
            AISuggestion::TYPE_NEW_LEAD_NEEDS_FIRST_CONTACT => 'Esta lead ainda precisa de primeiro contacto.',
            default => 'O agente comercial encontrou uma oportunidade de proximo passo.',
        };
    }

    public function calculateScore(Deal $deal, string $type): int
    {
        $score = 25;
        $daysWithoutActivity = $this->daysWithoutActivity($deal);

        if ((float) $deal->value >= self::HIGH_VALUE_THRESHOLD) {
            $score += 20;
        }

        if (in_array($deal->priority, [Deal::PRIORITY_HIGH, Deal::PRIORITY_URGENT], true)) {
            $score += $deal->priority === Deal::PRIORITY_URGENT ? 20 : 12;
        }

        if ($deal->expected_close_date && $deal->expected_close_date->betweenIncluded(now()->startOfDay(), now()->addDays(7)->endOfDay())) {
            $score += 18;
        }

        $score += min(20, $daysWithoutActivity * 2);

        if ($deal->proposals->where('status', DealProposal::STATUS_SENT)->isNotEmpty()) {
            $score += 10;
        }

        if ($type === AISuggestion::TYPE_NEW_LEAD_NEEDS_FIRST_CONTACT) {
            $score += 15;
        }

        return max(0, min(100, $score));
    }

    public function resolveSuggestedDueDate(Deal $deal, string $type): Carbon
    {
        $date = match ($type) {
            AISuggestion::TYPE_CLOSING_DATE_NEAR, AISuggestion::TYPE_HIGH_VALUE_STALLED => now()->addDay(),
            AISuggestion::TYPE_PROPOSAL_SENT_NO_FOLLOWUP, AISuggestion::TYPE_CLIENT_REQUESTED_INFO => now()->addHours(4),
            default => now()->addDays(2),
        };

        return $this->nextBusinessDateTime($date);
    }

    public function adjustScoreForUserHistory(User $user, string $type, int $score): int
    {
        $ignored = AISuggestion::withoutGlobalScopes()
            ->where('tenant_id', $user->current_tenant_id)
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('status', AISuggestion::STATUS_IGNORED)
            ->where('ignored_at', '>=', now()->subDays(30))
            ->count();

        $accepted = AISuggestion::withoutGlobalScopes()
            ->where('tenant_id', $user->current_tenant_id)
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('status', AISuggestion::STATUS_ACCEPTED)
            ->where('accepted_at', '>=', now()->subDays(30))
            ->count();

        return max(0, min(100, $score - min(30, $ignored * 10) + min(15, $accepted * 5)));
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
            return $this->nextBusinessDateTime($date->addDay()->setTime(9, 0));
        }

        return $date;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function suggestionCandidatesForDeal(Deal $deal, string $source): array
    {
        $candidates = [];
        $user = $deal->owner;

        if (! $user) {
            return [];
        }

        $user->forceFill(['current_tenant_id' => $deal->tenant_id]);
        $daysWithoutActivity = $this->daysWithoutActivity($deal);

        if ($daysWithoutActivity >= self::INACTIVITY_DAYS) {
            $candidates[] = $this->dealSuggestionData($deal, AISuggestion::TYPE_NO_ACTIVITY, 'Criar tarefa de follow-up', $source);
        }

        if ((float) $deal->value >= self::HIGH_VALUE_THRESHOLD && $daysWithoutActivity >= 5) {
            $candidates[] = $this->dealSuggestionData($deal, AISuggestion::TYPE_HIGH_VALUE_STALLED, 'Contactar cliente prioritariamente', $source);
        }

        if ($deal->expected_close_date && $deal->expected_close_date->betweenIncluded(now()->startOfDay(), now()->addDays(7)->endOfDay())) {
            $candidates[] = $this->dealSuggestionData($deal, AISuggestion::TYPE_CLOSING_DATE_NEAR, 'Marcar chamada de validacao antes do fecho', $source);
        }

        $latestSentProposal = $deal->proposals
            ->where('status', DealProposal::STATUS_SENT)
            ->sortByDesc('sent_at')
            ->first();

        if ($latestSentProposal?->sent_at && $latestSentProposal->sent_at->lte(now()->subDays(self::PROPOSAL_FOLLOW_UP_DAYS)) && (! $deal->last_activity_at || $deal->last_activity_at->lte($latestSentProposal->sent_at))) {
            $candidates[] = $this->dealSuggestionData($deal, AISuggestion::TYPE_PROPOSAL_SENT_NO_FOLLOWUP, 'Telefonar ou enviar follow-up da proposta', $source);
        }

        if ($this->hasClientRequestedInfoSignal($deal)) {
            $candidates[] = $this->dealSuggestionData($deal, AISuggestion::TYPE_CLIENT_REQUESTED_INFO, 'Enviar informacao pedida pelo cliente', $source);
        }

        if (($deal->person?->status === Person::STATUS_LEAD || $deal->stage === DealStage::SLUG_LEAD) && $daysWithoutActivity >= 1) {
            $candidates[] = $this->dealSuggestionData($deal, AISuggestion::TYPE_NEW_LEAD_NEEDS_FIRST_CONTACT, 'Criar tarefa de primeiro contacto', $source);
        }

        return $candidates;
    }

    /**
     * @return array<string,mixed>
     */
    private function dealSuggestionData(Deal $deal, string $type, string $action, string $source): array
    {
        $score = $this->calculateScore($deal, $type);
        $user = $deal->owner;
        $reason = $this->buildReason($deal, $type);
        $enhanced = $this->enhanceSuggestionText($deal, $type, $reason, $action);

        return [
            'tenant_id' => $deal->tenant_id,
            'user_id' => $deal->owner_id,
            'deal_id' => $deal->id,
            'person_id' => $deal->person_id,
            'entity_id' => $deal->entity_id,
            'type' => $type,
            'title' => $this->titleFor($type, $deal),
            'reason' => $enhanced['reason'] ?? $reason,
            'suggested_action' => $enhanced['suggested_action'] ?? $action,
            'suggested_due_at' => $this->resolveSuggestedDueDate($deal, $type),
            'priority' => $this->priorityFor($deal, $score),
            'status' => AISuggestion::STATUS_PENDING,
            'source' => $source,
            'score' => $user ? $this->adjustScoreForUserHistory($user, $type, $score) : $score,
            'metadata' => [
                'deal_value' => (float) $deal->value,
                'deal_priority' => $deal->priority,
                'stage' => $deal->stage,
                'days_without_activity' => $this->daysWithoutActivity($deal),
            ],
        ];
    }

    /**
     * @return array<string,string>
     */
    private function enhanceSuggestionText(Deal $deal, string $type, string $reason, string $action): array
    {
        if (! $this->openAI->enabled() || app()->environment('testing')) {
            return [];
        }

        return $this->openAI->improveCommercialSuggestion([
            'type' => $type,
            'deal_title' => $deal->title,
            'stage' => $deal->stage,
            'days_without_activity' => $this->daysWithoutActivity($deal),
            'last_notes_summary' => $this->shortInteractionSummary($deal),
            'reason' => $reason,
            'suggested_action' => $action,
        ]);
    }

    private function titleFor(string $type, Deal $deal): string
    {
        return match ($type) {
            AISuggestion::TYPE_NO_ACTIVITY => 'Retomar contacto em '.$deal->title,
            AISuggestion::TYPE_PROPOSAL_SENT_NO_FOLLOWUP => 'Acompanhar proposta enviada',
            AISuggestion::TYPE_CLOSING_DATE_NEAR => 'Validar fecho previsto',
            AISuggestion::TYPE_HIGH_VALUE_STALLED => 'Desbloquear negocio de alto valor',
            AISuggestion::TYPE_CLIENT_REQUESTED_INFO => 'Responder a pedido de informacao',
            AISuggestion::TYPE_NEW_LEAD_NEEDS_FIRST_CONTACT => 'Fazer primeiro contacto com lead',
            default => 'Proximo passo comercial',
        };
    }

    private function priorityFor(Deal $deal, int $score): string
    {
        if ($deal->priority === Deal::PRIORITY_URGENT || $score >= 85) {
            return AISuggestion::PRIORITY_URGENT;
        }

        if ($deal->priority === Deal::PRIORITY_HIGH || $score >= 65) {
            return AISuggestion::PRIORITY_HIGH;
        }

        if ($score <= 35) {
            return AISuggestion::PRIORITY_LOW;
        }

        return AISuggestion::PRIORITY_MEDIUM;
    }

    private function isClosedDeal(Deal $deal): bool
    {
        $stage = $deal->relationLoaded('stage') ? $deal->getRelation('stage') : $deal->stage()->first();

        return (bool) ($stage?->is_won || $stage?->is_lost);
    }

    private function daysWithoutActivity(Deal $deal): int
    {
        $reference = $deal->last_activity_at ?? $deal->created_at ?? now();

        return max(0, (int) $reference->diffInDays(now()));
    }

    private function hasClientRequestedInfoSignal(Deal $deal): bool
    {
        $keywords = ['pediu informacao', 'enviar detalhes', 'aguarda resposta', 'documentacao', 'proposta atualizada'];
        $texts = collect()
            ->merge($deal->dealNotes->pluck('body'))
            ->merge($deal->calendarEvents->pluck('description'))
            ->filter()
            ->map(fn (string $text) => Str::of($text)->lower()->ascii()->toString());

        return $texts->contains(fn (string $text) => collect($keywords)->contains(fn (string $keyword) => str_contains($text, $keyword)));
    }

    private function tenantOwner(int $tenantId): ?User
    {
        return User::whereHas('tenants', fn ($query) => $query->whereKey($tenantId)->where('tenant_user.role', Tenant::ROLE_OWNER))->first();
    }

    private function shortInteractionSummary(Deal $deal): string
    {
        return Collection::make()
            ->merge($deal->dealNotes->sortByDesc('created_at')->take(2)->pluck('body'))
            ->merge($deal->calendarEvents->sortByDesc('start_at')->take(2)->pluck('description'))
            ->filter()
            ->map(fn (string $text) => Str::limit($text, 120))
            ->implode(' | ');
    }

    /**
     * @param  array<string,mixed>  $properties
     */
    private function log(int $tenantId, ?int $userId, string $action, string $module, ?object $subject, string $description, array $properties = []): void
    {
        ActivityLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject->id ?? null,
            'description' => $description,
            'properties' => $properties ?: null,
        ]);
    }
}
