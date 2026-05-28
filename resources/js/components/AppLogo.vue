<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex aspect-square size-9 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground shadow-sm">
        <AppLogoIcon class="size-5 fill-current" />
    </div>
    <div class="ml-2 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate font-semibold leading-none tracking-tight">FlowCRM</span>
        <span class="truncate text-xs text-sidebar-foreground/65">Sales workspace</span>
    </div>
</template>
