<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Bell, CalendarDays, CheckSquare, Circle, History, Mail, Package, Paperclip, Phone, Settings, StickyNote, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import TimelineItemDetails from './TimelineItemDetails.vue';

interface TimelineItem {
    id: string;
    source_type: string;
    type: string;
    title: string;
    description: string | null;
    occurred_at: string | null;
    user_name: string | null;
    badge_label: string;
    icon: string;
    metadata: Record<string, unknown>;
    details: Record<string, unknown>;
}

const props = defineProps<{
    dealId: number;
    items: TimelineItem[];
}>();

const items = ref<TimelineItem[]>(props.items);
const selectedItem = ref<TimelineItem | null>(null);
const loading = ref(false);
const filters = ref({
    type: 'all',
    date_from: '',
    date_to: '',
    search: '',
});

watch(
    () => props.items,
    (next) => {
        items.value = next;
    },
);

const typeFilters = [
    { label: 'Todos', value: 'all' },
    { label: 'Emails', value: 'email' },
    { label: 'Atividades', value: 'activity' },
    { label: 'Notas', value: 'note' },
    { label: 'Propostas', value: 'proposal' },
    { label: 'Follow-ups', value: 'follow_up' },
    { label: 'Alterações', value: 'change' },
    { label: 'Produtos', value: 'product' },
];

const iconMap = {
    bell: Bell,
    calendar: CalendarDays,
    'check-square': CheckSquare,
    history: History,
    mail: Mail,
    package: Package,
    paperclip: Paperclip,
    phone: Phone,
    settings: Settings,
    'sticky-note': StickyNote,
    users: Users,
};

const activeType = computed(() => filters.value.type);

const iconFor = (icon: string) => iconMap[icon as keyof typeof iconMap] ?? Circle;

const fetchTimeline = async () => {
    loading.value = true;
    const params = new URLSearchParams();

    Object.entries(filters.value).forEach(([key, value]) => {
        if (value) {
            params.set(key, value);
        }
    });

    try {
        const response = await fetch(`/deals/${props.dealId}/timeline?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });

        if (response.ok) {
            const data = await response.json();
            items.value = data.items ?? [];
        }
    } finally {
        loading.value = false;
    }
};

const setType = (type: string) => {
    filters.value.type = type;
    fetchTimeline();
};

const reset = () => {
    filters.value = {
        type: 'all',
        date_from: '',
        date_to: '',
        search: '',
    };
    fetchTimeline();
};
</script>

<template>
    <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="font-medium">Cronologia do negócio</h2>
                <p class="text-sm text-muted-foreground">Interações, emails, propostas, atividades, notas e alterações num único painel.</p>
            </div>
            <span v-if="loading" class="text-sm text-muted-foreground">A atualizar...</span>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <Button
                v-for="filter in typeFilters"
                :key="filter.value"
                type="button"
                size="sm"
                :variant="activeType === filter.value ? 'default' : 'outline'"
                @click="setType(filter.value)"
            >
                {{ filter.label }}
            </Button>
        </div>

        <form class="mt-4 grid gap-3 md:grid-cols-[160px_160px_1fr_auto_auto]" @submit.prevent="fetchTimeline">
            <Input v-model="filters.date_from" type="date" />
            <Input v-model="filters.date_to" type="date" />
            <Input v-model="filters.search" placeholder="Pesquisar na cronologia" />
            <Button type="submit">Filtrar</Button>
            <Button type="button" variant="outline" @click="reset">Limpar</Button>
        </form>

        <div class="mt-5 space-y-3">
            <article v-for="item in items" :key="item.id" class="rounded-lg border p-4">
                <div class="flex gap-3">
                    <div class="mt-1 flex size-9 shrink-0 items-center justify-center rounded-full bg-muted">
                        <component :is="iconFor(item.icon)" class="size-4" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full border px-2 py-1 text-xs font-medium">{{ item.badge_label }}</span>
                            <span class="text-xs text-muted-foreground">{{ item.occurred_at ?? '-' }}</span>
                            <span v-if="item.user_name" class="text-xs text-muted-foreground">· {{ item.user_name }}</span>
                        </div>
                        <h3 class="mt-2 font-medium">{{ item.title }}</h3>
                        <p v-if="item.description" class="mt-1 line-clamp-2 text-sm text-muted-foreground">{{ item.description }}</p>
                    </div>
                    <Button type="button" variant="outline" size="sm" @click="selectedItem = item">Ver detalhes</Button>
                </div>
            </article>

            <div v-if="items.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                Ainda não há interações para mostrar com estes filtros.
            </div>
        </div>

        <TimelineItemDetails :item="selectedItem" @close="selectedItem = null" />
    </section>
</template>
