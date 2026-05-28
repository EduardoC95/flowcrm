<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ExternalLink, PlusCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface ChatAction {
    type: string;
    label: string;
    url?: string;
    requires_confirmation?: boolean;
    payload?: Record<string, unknown>;
}

const props = defineProps<{
    actions: ChatAction[];
}>();

const emit = defineEmits<{
    executeAction: [action: ChatAction];
}>();

const openActions = computed(() => props.actions.filter((action) => action.type === 'open_record'));
const writeActions = computed(() => props.actions.filter((action) => action.type !== 'open_record'));
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <Button v-for="action in openActions" :key="`${action.type}-${action.label}`" as-child size="sm" variant="outline">
            <a :href="action.url || '#'">
                <ExternalLink class="size-4" />
                {{ action.label }}
            </a>
        </Button>
        <Button
            v-for="action in writeActions"
            :key="`${action.type}-${action.label}`"
            size="sm"
            variant="outline"
            @click="emit('executeAction', action)"
        >
            <PlusCircle class="size-4" />
            {{ action.label }}
        </Button>
    </div>
</template>
