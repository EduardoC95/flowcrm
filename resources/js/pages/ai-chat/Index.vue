<script setup lang="ts">
import ChatConversationList from '@/components/ai-chat/ChatConversationList.vue';
import ChatMessageBubble from '@/components/ai-chat/ChatMessageBubble.vue';
import SuggestedQuestions from '@/components/ai-chat/SuggestedQuestions.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Bot, SendHorizontal } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';

interface Conversation {
    id: number;
    title: string | null;
    last_message_at: string | null;
    url: string;
}

interface ChatAction {
    type: string;
    label: string;
    url?: string;
    requires_confirmation?: boolean;
    payload?: Record<string, unknown>;
}

interface RecordRow {
    type: string;
    id: number;
    title: string;
    subtitle?: string | null;
    url?: string | null;
}

interface ChatMessage {
    id: number | string;
    role: 'user' | 'assistant' | 'system';
    content: string;
    intent?: string | null;
    metadata?: {
        records?: RecordRow[];
        actions?: ChatAction[];
        [key: string]: unknown;
    };
    created_at?: string | null;
    streaming?: boolean;
}

const props = defineProps<{
    conversations: Conversation[];
    activeConversation: Conversation | null;
    messages: ChatMessage[];
    suggestions: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Chat CRM', href: '/ai-chat' }];
const conversations = ref<Conversation[]>([...props.conversations]);
const activeConversation = ref<Conversation | null>(props.activeConversation);
const messages = ref<ChatMessage[]>([...props.messages]);
const question = ref('');
const sending = ref(false);
const error = ref<string | null>(null);
const scrollBox = ref<HTMLElement | null>(null);

const endpoint = computed(() => (activeConversation.value ? `/ai-chat/${activeConversation.value.id}/messages` : '/ai-chat'));

const csrfToken = () => {
    const cookie = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return cookie ? decodeURIComponent(cookie) : '';
};

const scrollToBottom = async () => {
    await nextTick();
    if (scrollBox.value) {
        scrollBox.value.scrollTop = scrollBox.value.scrollHeight;
    }
};

const sendQuestion = async (value?: string) => {
    const text = (value ?? question.value).trim();
    if (!text || sending.value) return;

    sending.value = true;
    error.value = null;
    question.value = '';

    const userMessage: ChatMessage = {
        id: `local-user-${Date.now()}`,
        role: 'user',
        content: text,
    };
    const assistantMessage: ChatMessage = {
        id: `local-assistant-${Date.now()}`,
        role: 'assistant',
        content: '',
        streaming: true,
        metadata: { records: [], actions: [] },
    };

    messages.value.push(userMessage, assistantMessage);
    await scrollToBottom();

    try {
        const response = await fetch(endpoint.value, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ message: text }),
        });

        if (!response.ok) {
            throw new Error('Nao foi possivel obter resposta.');
        }

        const data = await response.json();
        activeConversation.value = data.conversation;
        messages.value[messages.value.length - 2] = data.user_message;
        messages.value[messages.value.length - 1] = {
            ...data.assistant_message,
            content: '',
            streaming: true,
        };

        if (!conversations.value.some((conversation) => conversation.id === data.conversation.id)) {
            conversations.value.unshift(data.conversation);
            router.replace(`/ai-chat/${data.conversation.id}`, { preserveState: true, preserveScroll: true });
        }

        streamAnswer(data.stream_url, data.assistant_message.content, data.result?.records ?? [], data.result?.actions ?? []);
    } catch (exception) {
        assistantMessage.streaming = false;
        assistantMessage.content = exception instanceof Error ? exception.message : 'Erro inesperado no Chat CRM.';
        error.value = assistantMessage.content;
    } finally {
        sending.value = false;
        await scrollToBottom();
    }
};

const streamAnswer = (streamUrl: string, fallbackText: string, records: RecordRow[], actions: ChatAction[]) => {
    const target = messages.value[messages.value.length - 1];

    if (!window.EventSource) {
        target.content = fallbackText;
        target.streaming = false;
        target.metadata = { records, actions };
        return;
    }

    const source = new EventSource(streamUrl);
    let received = '';

    source.addEventListener('chunk', (event) => {
        const payload = JSON.parse((event as MessageEvent).data);
        received += payload.content ?? '';
        target.content = received;
        target.metadata = { records, actions };
        scrollToBottom();
    });

    source.addEventListener('done', () => {
        source.close();
        target.content = received || fallbackText;
        target.streaming = false;
        target.metadata = { records, actions };
        scrollToBottom();
    });

    source.addEventListener('error', () => {
        source.close();
        target.content = received || fallbackText;
        target.streaming = false;
        target.metadata = { records, actions };
    });
};

const executeAction = async (action: ChatAction) => {
    if (!activeConversation.value) return;

    const payload = { ...(action.payload ?? {}) };
    if (!payload.deal_id && !payload.suggestion_id) {
        const dealId = prompt('ID do negocio para associar a acao:');
        if (!dealId) return;
        payload.deal_id = Number(dealId);
    }

    if (action.type === 'create_note' && !payload.body) {
        const body = prompt('Texto da nota:');
        if (!body) return;
        payload.body = body;
    }

    if (action.type === 'create_activity') {
        payload.title = payload.title || prompt('Titulo da atividade:', 'Follow-up comercial');
        payload.start_at = payload.start_at || new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString();
    }

    const response = await fetch(`/ai-chat/${activeConversation.value.id}/actions`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ type: action.type, payload }),
    });

    const data = await response.json();
    messages.value.push({
        id: `action-${Date.now()}`,
        role: 'assistant',
        content: data.answer_text || 'Acao concluida.',
        metadata: { actions: data.actions ?? [] },
    });
    await scrollToBottom();
};
</script>

<template>
    <Head title="Chat CRM" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-h-[calc(100vh-5rem)] flex-1 flex-col gap-4 p-4">
            <section class="flex flex-col gap-2">
                <div class="flex items-center gap-2">
                    <Bot class="size-5 text-primary" />
                    <h1 class="text-2xl font-semibold tracking-tight">Chat CRM</h1>
                </div>
                <p class="text-sm text-muted-foreground">
                    Pergunte sobre negocios, contactos, produtos e atividades. As respostas usam apenas consultas seguras do FlowCRM.
                </p>
            </section>

            <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>

            <div class="grid min-h-0 flex-1 gap-4 lg:grid-cols-[280px_1fr]">
                <ChatConversationList :conversations="conversations" :active-id="activeConversation?.id ?? null" />

                <section class="flex min-h-0 flex-col rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                    <div class="border-b p-4">
                        <SuggestedQuestions :suggestions="suggestions" @send="sendQuestion" />
                    </div>

                    <div ref="scrollBox" class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                        <div v-if="messages.length === 0" class="flex min-h-72 flex-col items-center justify-center gap-2 text-center">
                            <Bot class="size-8 text-muted-foreground" />
                            <h2 class="text-lg font-medium">Comece com uma pergunta comercial</h2>
                            <p class="max-w-md text-sm text-muted-foreground">
                                Exemplo: "Qual o volume de negocios no estado Negociacao?" ou "Qual o telefone da Maria Silva?"
                            </p>
                        </div>

                        <ChatMessageBubble
                            v-for="message in messages"
                            :key="message.id"
                            :role="message.role"
                            :content="message.content"
                            :records="message.metadata?.records ?? []"
                            :actions="message.metadata?.actions ?? []"
                            :streaming="message.streaming"
                            @execute-action="executeAction"
                        />
                    </div>

                    <form class="flex gap-2 border-t p-4" @submit.prevent="sendQuestion()">
                        <Input v-model="question" placeholder="Pergunte ao FlowCRM..." :disabled="sending" />
                        <Button type="submit" :disabled="sending || !question.trim()">
                            <SendHorizontal class="size-4" />
                            Enviar
                        </Button>
                    </form>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
