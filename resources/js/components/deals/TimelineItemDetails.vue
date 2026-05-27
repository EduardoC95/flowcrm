<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { X } from 'lucide-vue-next';

defineProps<{
    item: {
        title: string;
        badge_label: string;
        occurred_at: string | null;
        user_name: string | null;
        metadata: Record<string, unknown>;
        details: Record<string, unknown>;
    } | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const formatValue = (value: unknown): string => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value, null, 2);
    }

    return String(value);
};
</script>

<template>
    <div v-if="item" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-lg border bg-background p-5 shadow-lg">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <span class="inline-flex rounded-full border px-2 py-1 text-xs font-medium">{{ item.badge_label }}</span>
                    <h2 class="mt-3 text-lg font-semibold">{{ item.title }}</h2>
                    <p class="text-sm text-muted-foreground">{{ item.occurred_at ?? '-' }} · {{ item.user_name ?? 'Sistema' }}</p>
                </div>
                <Button variant="ghost" size="icon" @click="emit('close')">
                    <X class="size-4" />
                </Button>
            </div>

            <div class="mt-5 space-y-4">
                <section v-if="Object.keys(item.metadata ?? {}).length" class="rounded-md border p-3">
                    <h3 class="text-sm font-medium">Metadados</h3>
                    <dl class="mt-3 grid gap-3 text-sm md:grid-cols-2">
                        <div v-for="(value, key) in item.metadata" :key="key">
                            <dt class="text-muted-foreground">{{ key }}</dt>
                            <dd class="mt-1 whitespace-pre-wrap">{{ formatValue(value) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-md border p-3">
                    <h3 class="text-sm font-medium">Detalhes</h3>
                    <dl class="mt-3 grid gap-3 text-sm">
                        <div v-for="(value, key) in item.details" :key="key">
                            <dt class="text-muted-foreground">{{ key }}</dt>
                            <dd class="mt-1 whitespace-pre-wrap">{{ formatValue(value) }}</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </div>
    </div>
</template>
