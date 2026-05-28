<script setup lang="ts">
import { Button } from '@/components/ui/button';

defineProps<{
    suggestions: string[];
}>();

const emit = defineEmits<{
    send: [question: string];
}>();
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <Button v-for="suggestion in suggestions" :key="suggestion" type="button" variant="outline" size="sm" @click="emit('send', suggestion)">
            {{ suggestion }}
        </Button>
    </div>
</template>
