<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExecuteAIChatActionRequest;
use App\Http\Requests\StoreAIChatMessageRequest;
use App\Models\AIChatConversation;
use App\Models\AIChatMessage;
use App\Services\AI\AIChatMessageService;
use App\Services\AI\CRMQueryExecutorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIChatController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AIChatConversation::class);

        return Inertia::render('ai-chat/Index', [
            ...$this->pageProps($request, null),
        ]);
    }

    public function show(Request $request, AIChatConversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        return Inertia::render('ai-chat/Index', [
            ...$this->pageProps($request, $conversation),
        ]);
    }

    public function storeMessage(StoreAIChatMessageRequest $request, AIChatMessageService $service, ?AIChatConversation $conversation = null): JsonResponse
    {
        if ($conversation) {
            Gate::authorize('view', $conversation);
        }

        $payload = $service->handle($request->user(), $conversation, $request->validated('message'));
        $conversation = $payload['conversation'];

        return response()->json([
            'conversation' => $this->conversationRow($conversation),
            'user_message' => $this->messageRow($payload['user_message']),
            'assistant_message' => $this->messageRow($payload['assistant_message']),
            'result' => $payload['result'],
            'stream_url' => route('ai-chat.stream', $conversation, false),
        ]);
    }

    public function streamMessage(AIChatConversation $conversation): StreamedResponse
    {
        Gate::authorize('view', $conversation);

        $message = $conversation->messages()
            ->where('role', AIChatMessage::ROLE_ASSISTANT)
            ->latest()
            ->first();

        return response()->stream(function () use ($message) {
            if (! $message) {
                echo "event: error\n";
                echo 'data: '.json_encode(['message' => 'Sem resposta para transmitir'])."\n\n";

                return;
            }

            $chunks = preg_split('/(\s+)/', $message->content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [$message->content];
            $buffer = '';

            foreach ($chunks as $chunk) {
                $buffer .= $chunk;

                if (mb_strlen($buffer) < 24) {
                    continue;
                }

                echo "event: chunk\n";
                echo 'data: '.json_encode(['content' => $buffer])."\n\n";
                @ob_flush();
                flush();
                $buffer = '';
            }

            if ($buffer !== '') {
                echo "event: chunk\n";
                echo 'data: '.json_encode(['content' => $buffer])."\n\n";
            }

            echo "event: done\n";
            echo 'data: '.json_encode(['ok' => true])."\n\n";
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function executeAction(ExecuteAIChatActionRequest $request, AIChatConversation $conversation, CRMQueryExecutorService $executor): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $result = $executor->executeAction(
            $request->user(),
            $request->validated('type'),
            $request->validated('payload'),
        );

        AIChatMessage::create([
            'tenant_id' => $request->user()->current_tenant_id,
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AIChatMessage::ROLE_ASSISTANT,
            'content' => $result['answer_text'],
            'intent' => 'action_'.$request->validated('type'),
            'metadata' => [
                'actions' => $result['actions'] ?? [],
            ],
            'created_records' => $result['created_records'] ?? null,
        ]);

        $conversation->update(['last_message_at' => now()]);

        return response()->json($result);
    }

    public function suggestedQuestions(): JsonResponse
    {
        return response()->json([
            'suggestions' => [
                'Qual o volume de negocios no estado Negociacao?',
                'Quais sao os negocios sem atividade ha mais de 7 dias?',
                'Que negocios fecham nos proximos 14 dias?',
                'Quais sao os produtos com maior valor no pipeline?',
                'Qual o telemovel do Antonio Pinheiro?',
                'Que acoes comerciais devo fazer hoje?',
                'Mostra-me sugestoes de alto impacto',
                'Que negocios estao em risco?',
                'Criar uma tarefa de follow-up para o negocio CRM rollout',
            ],
        ]);
    }

    public function destroy(AIChatConversation $conversation): JsonResponse
    {
        Gate::authorize('delete', $conversation);

        $conversation->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string,mixed>
     */
    private function pageProps(Request $request, ?AIChatConversation $activeConversation): array
    {
        $conversations = AIChatConversation::query()
            ->where('user_id', $request->user()->id)
            ->latest('last_message_at')
            ->limit(30)
            ->get()
            ->map(fn (AIChatConversation $conversation) => $this->conversationRow($conversation));

        $messages = $activeConversation
            ? $activeConversation->messages()->oldest()->get()->map(fn (AIChatMessage $message) => $this->messageRow($message))->values()
            : collect();

        return [
            'conversations' => $conversations,
            'activeConversation' => $activeConversation ? $this->conversationRow($activeConversation) : null,
            'messages' => $messages,
            'suggestions' => [
                'Qual o volume de negocios no estado Negociacao?',
                'Quais sao os negocios sem atividade ha mais de 7 dias?',
                'Que negocios fecham nos proximos 14 dias?',
                'Quais sao os produtos com maior valor no pipeline?',
                'Qual o telemovel da Maria Silva?',
                'Que acoes comerciais devo fazer hoje?',
                'Mostra-me sugestoes de alto impacto',
                'Que negocios estao em risco?',
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function conversationRow(AIChatConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'last_message_at' => $conversation->last_message_at?->toDateTimeString(),
            'url' => route('ai-chat.show', $conversation, false),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function messageRow(AIChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'intent' => $message->intent,
            'metadata' => $message->metadata ?? [],
            'created_records' => $message->created_records ?? [],
            'created_at' => $message->created_at?->toDateTimeString(),
        ];
    }
}
