<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { Monitor, Moon, Sun } from 'lucide-vue-next';

interface Props {
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    class: '',
});

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Claro' },
    { value: 'dark', Icon: Moon, label: 'Escuro' },
    { value: 'system', Icon: Monitor, label: 'Sistema' },
] as const;
</script>

<template>
    <div :class="['inline-flex gap-1 rounded-lg border border-border/70 bg-muted/70 p-1 shadow-sm', props.class]">
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            @click="updateAppearance(value)"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                appearance === value ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:bg-card/70 hover:text-foreground',
            ]"
        >
            <component :is="Icon" class="-ml-1 h-4 w-4" />
            <span class="ml-1.5 text-sm">{{ label }}</span>
        </button>
    </div>
</template>
