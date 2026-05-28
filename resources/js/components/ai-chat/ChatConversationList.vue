<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import { MessageSquare, Plus } from 'lucide-vue-next';

interface Conversation {
    id: number;
    title: string | null;
    last_message_at: string | null;
    url: string;
}

defineProps<{
    conversations: Conversation[];
    activeId: number | null;
}>();
</script>

<template>
    <aside class="flex min-h-0 flex-col rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
        <div class="flex items-center justify-between border-b p-3">
            <div class="flex items-center gap-2 text-sm font-semibold">
                <MessageSquare class="size-4" />
                Conversas
            </div>
            <Button as-child variant="ghost" size="icon" title="Nova conversa">
                <Link href="/ai-chat"><Plus class="size-4" /></Link>
            </Button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-2">
            <div v-if="conversations.length === 0" class="p-4 text-sm text-muted-foreground">Ainda nao ha conversas guardadas.</div>
            <Link
                v-for="conversation in conversations"
                :key="conversation.id"
                :href="conversation.url"
                class="block rounded-md px-3 py-2 text-sm transition hover:bg-muted"
                :class="conversation.id === activeId ? 'bg-muted font-medium' : ''"
            >
                <span class="line-clamp-2">{{ conversation.title || 'Conversa sem titulo' }}</span>
                <span class="mt-1 block text-xs text-muted-foreground">{{ conversation.last_message_at || 'Sem data' }}</span>
            </Link>
        </div>
    </aside>
</template>
