<?php

namespace App\Services\AI;

use App\Models\ActivityLog;
use App\Models\AIChatConversation;
use App\Models\AIChatMessage;
use App\Models\User;
use App\Services\OpenAI\OpenAIService;
use Illuminate\Support\Str;

class AIChatMessageService
{
    public function __construct(
        private readonly CRMQueryIntentService $intentService,
        private readonly CRMQueryExecutorService $executor,
        private readonly OpenAIService $openAI,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function handle(User $user, ?AIChatConversation $conversation, string $question): array
    {
        $tenantId = (int) $user->current_tenant_id;
        $question = Str::limit(trim($question), 2000, '');

        $conversation ??= AIChatConversation::create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'title' => Str::limit($question, 80, ''),
            'last_message_at' => now(),
        ]);

        $userMessage = AIChatMessage::create([
            'tenant_id' => $tenantId,
            'ai_chat_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => AIChatMessage::ROLE_USER,
            'content' => $question,
        ]);

        $this->log($tenantId, $user->id, 'ai_chat.message_sent', $conversation, 'Mensagem enviada no Chat CRM.');

        $intent = $this->intentService->detect($question);
        $this->log($tenantId, $user->id, 'ai_chat.intent_detected', $conversation, 'Intencao detetada pelo Chat CRM.', [
            'intent' => $intent['intent'],
            'confidence' => $intent['confidence'],
            'source' => config('openai.enabled') && filled(config('openai.api_key')) ? 'openai_or_fallback' : 'local_fallback',
        ]);

        $result = $this->executor->execute($user, $intent);
        $answer = $this->openAI->generateNaturalAnswer($result);

        $assistantMessage = AIChatMessage::create([
            'tenant_id' => $tenantId,
            'ai_chat_conversation_id' => $conversation->id,
            'user_id' => null,
            'role' => AIChatMessage::ROLE_ASSISTANT,
            'content' => $answer,
            'intent' => $intent['intent'],
            'metadata' => [
                'intent' => $intent,
                'records' => $result['records'] ?? [],
                'actions' => $result['actions'] ?? [],
                'metadata' => $result['metadata'] ?? [],
            ],
            'created_records' => $result['created_records'] ?? null,
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'title' => $conversation->title ?: Str::limit($question, 80, ''),
        ]);

        $this->log($tenantId, $user->id, 'ai_chat.response_generated', $conversation, 'Resposta gerada pelo Chat CRM.', [
            'intent' => $intent['intent'],
        ]);

        return [
            'conversation' => $conversation->fresh(),
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
            'result' => [
                ...$result,
                'answer_text' => $answer,
                'intent' => $intent,
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $queryResult
     * @return iterable<int,string>
     */
    public function streamChunks(array $queryResult): iterable
    {
        return $this->openAI->streamNaturalAnswer($queryResult);
    }

    /**
     * @param  array<string,mixed>  $properties
     */
    private function log(int $tenantId, ?int $userId, string $action, AIChatConversation $conversation, string $description, array $properties = []): void
    {
        ActivityLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'action' => $action,
            'module' => 'ai_chat',
            'subject_type' => AIChatConversation::class,
            'subject_id' => $conversation->id,
            'description' => $description,
            'properties' => $properties ?: null,
        ]);
    }
}
