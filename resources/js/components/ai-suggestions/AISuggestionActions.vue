<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { Archive, CalendarPlus, Check, Clock, X } from 'lucide-vue-next';

const props = defineProps<{
    suggestionId: number;
    canAct?: boolean;
}>();

const accept = () => router.patch(`/ai-suggestions/${props.suggestionId}/accept`);
const archive = () => router.patch(`/ai-suggestions/${props.suggestionId}/archive`);
const ignore = () => router.patch(`/ai-suggestions/${props.suggestionId}/ignore`);
const postpone = () => {
    const value = prompt('Adiar ate quando? Use formato YYYY-MM-DD HH:mm');
    if (value) {
        router.patch(`/ai-suggestions/${props.suggestionId}/postpone`, { postponed_until: value });
    }
};
const convert = () => {
    if (confirm('Converter esta sugestao numa atividade de calendario?')) {
        router.post(`/ai-suggestions/${props.suggestionId}/convert-to-activity`);
    }
};
</script>

<template>
    <div v-if="canAct" class="flex flex-wrap gap-2">
        <Button size="sm" variant="outline" @click="accept"><Check class="size-4" /> Aceitar</Button>
        <Button size="sm" @click="convert"><CalendarPlus class="size-4" /> Converter</Button>
        <Button size="sm" variant="outline" @click="postpone"><Clock class="size-4" /> Adiar</Button>
        <Button size="sm" variant="outline" @click="archive"><Archive class="size-4" /> Arquivar</Button>
        <Button size="sm" variant="ghost" @click="ignore"><X class="size-4" /> Ignorar</Button>
    </div>
</template>
