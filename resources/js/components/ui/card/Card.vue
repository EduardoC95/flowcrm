<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div :class="cn('rounded-lg border border-border/70 bg-card/95 text-card-foreground shadow-[0_16px_40px_-28px_rgba(15,23,42,0.45)]', props.class)">
        <slot />
    </div>
</template>
