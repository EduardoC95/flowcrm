<script setup lang="ts">
import ChatActions from '@/components/ai-chat/ChatActions.vue';

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

defineProps<{
    role: 'user' | 'assistant' | 'system';
    content: string;
    records?: RecordRow[];
    actions?: ChatAction[];
    streaming?: boolean;
}>();

const emit = defineEmits<{
    executeAction: [action: ChatAction];
}>();
</script>

<template>
    <div class="flex" :class="role === 'user' ? 'justify-end' : 'justify-start'">
        <div
            class="max-w-[88%] rounded-lg border px-4 py-3 text-sm leading-6 shadow-sm"
            :class="role === 'user' ? 'border-primary bg-primary text-primary-foreground' : 'border-border/70 bg-card/95'"
        >
            <p class="whitespace-pre-wrap">{{ content }}<span v-if="streaming" class="animate-pulse">|</span></p>

            <div v-if="records?.length" class="mt-3 space-y-2">
                <a
                    v-for="record in records"
                    :key="`${record.type}-${record.id}`"
                    :href="record.url || '#'"
                    class="block rounded-md border bg-background/70 px-3 py-2 text-foreground transition hover:bg-muted"
                >
                    <span class="block font-medium">{{ record.title }}</span>
                    <span v-if="record.subtitle" class="text-xs text-muted-foreground">{{ record.subtitle }}</span>
                </a>
            </div>

            <ChatActions v-if="actions?.length" class="mt-3" :actions="actions" @execute-action="emit('executeAction', $event)" />
        </div>
    </div>
</template>
