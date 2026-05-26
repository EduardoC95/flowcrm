<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CalendarPlus, Eye } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

interface Option {
    id: number;
    name: string;
}

interface CalendarEvent {
    id: number;
    title: string;
    start_at: string | null;
    end_at: string | null;
    type: string;
    status: string;
    priority: string | null;
    owner: Option | null;
    associated: { id: number; type: string; name: string; url: string | null } | null;
    url: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    events: {
        data: CalendarEvent[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    feed: CalendarEvent[];
    filters: {
        type: string | null;
        status: string | null;
        owner_id: number | null;
        date_from: string | null;
        date_to: string | null;
        associated_type: string | null;
    };
    types: string[];
    statuses: string[];
    priorities: string[];
    owners: Option[];
    can: {
        create: boolean;
    };
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Calendário', href: '/calendar' }];
const viewMode = ref<'month' | 'week' | 'day'>('month');

const form = reactive({
    type: props.filters.type ?? '',
    status: props.filters.status ?? '',
    owner_id: props.filters.owner_id ? String(props.filters.owner_id) : '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    associated_type: props.filters.associated_type ?? '',
});

const typeLabels: Record<string, string> = {
    task: 'Tarefa',
    call: 'Chamada',
    meeting: 'Reunião',
    note: 'Nota',
    reminder: 'Lembrete',
};

const statusLabels: Record<string, string> = {
    pending: 'Pendente',
    completed: 'Concluído',
    cancelled: 'Cancelado',
};

const priorityLabels: Record<string, string> = {
    low: 'Baixa',
    medium: 'Média',
    high: 'Alta',
    urgent: 'Urgente',
};

const badgeClass = (value: string | null) =>
    ({
        task: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-300',
        call: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300',
        meeting: 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900 dark:bg-violet-950 dark:text-violet-300',
        note: 'border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-300',
        reminder: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
        completed: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300',
        cancelled: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300',
        urgent: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300',
    })[value ?? ''] || 'border-border bg-muted text-muted-foreground';

const groupedEvents = computed(() => {
    const limit = viewMode.value === 'day' ? 1 : viewMode.value === 'week' ? 7 : 31;
    const groups = new Map<string, CalendarEvent[]>();

    props.feed.forEach((event) => {
        const day = event.start_at?.slice(0, 10) ?? 'Sem data';
        groups.set(day, [...(groups.get(day) ?? []), event]);
    });

    return Array.from(groups.entries())
        .sort(([a], [b]) => a.localeCompare(b))
        .slice(0, limit)
        .map(([date, events]) => ({ date, events }));
});

const submit = () => {
    router.get('/calendar', form, {
        preserveState: true,
        replace: true,
    });
};

const reset = () => {
    Object.assign(form, {
        type: '',
        status: '',
        owner_id: '',
        date_from: '',
        date_to: '',
        associated_type: '',
    });
    submit();
};
</script>

<template>
    <Head title="Calendário" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Calendário</h1>
                    <p class="text-sm text-muted-foreground">Eventos, reuniões, tarefas, chamadas e lembretes comerciais.</p>
                </div>
                <Button v-if="can.create" as-child>
                    <Link href="/calendar-events/create">
                        <CalendarPlus class="size-4" />
                        Novo evento
                    </Link>
                </Button>
            </section>

            <form
                class="grid gap-3 rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border xl:grid-cols-7"
                @submit.prevent="submit"
            >
                <select v-model="form.type" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="">Todos os tipos</option>
                    <option v-for="type in types" :key="type" :value="type">{{ typeLabels[type] ?? type }}</option>
                </select>
                <select v-model="form.status" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="">Todos os estados</option>
                    <option v-for="status in statuses" :key="status" :value="status">{{ statusLabels[status] ?? status }}</option>
                </select>
                <select v-model="form.owner_id" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="">Todos os responsáveis</option>
                    <option v-for="owner in owners" :key="owner.id" :value="owner.id">{{ owner.name }}</option>
                </select>
                <select v-model="form.associated_type" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="">Todas as associações</option>
                    <option value="entity">Entidade</option>
                    <option value="person">Pessoa</option>
                    <option value="deal">Negócio</option>
                </select>
                <Input v-model="form.date_from" type="date" />
                <Input v-model="form.date_to" type="date" />
                <div class="flex gap-2">
                    <Button type="submit">Filtrar</Button>
                    <Button type="button" variant="outline" @click="reset">Limpar</Button>
                </div>
            </form>

            <div class="flex flex-wrap gap-2">
                <Button :variant="viewMode === 'month' ? 'default' : 'outline'" size="sm" @click="viewMode = 'month'">Mês</Button>
                <Button :variant="viewMode === 'week' ? 'default' : 'outline'" size="sm" @click="viewMode = 'week'">Semana</Button>
                <Button :variant="viewMode === 'day' ? 'default' : 'outline'" size="sm" @click="viewMode = 'day'">Dia</Button>
            </div>

            <section class="grid gap-4 xl:grid-cols-[2fr_1fr]">
                <div class="space-y-4">
                    <div
                        v-if="groupedEvents.length === 0"
                        class="flex min-h-64 flex-col items-center justify-center rounded-lg border border-dashed p-8 text-center"
                    >
                        <h2 class="text-lg font-medium">Sem eventos para os filtros atuais</h2>
                        <p class="max-w-md text-sm text-muted-foreground">Ajusta o intervalo ou cria uma nova atividade comercial.</p>
                    </div>

                    <div
                        v-for="group in groupedEvents"
                        :key="group.date"
                        class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                    >
                        <h2 class="font-medium">{{ group.date }}</h2>
                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <Link
                                v-for="event in group.events"
                                :key="event.id"
                                :href="event.url"
                                class="rounded-md border p-3 text-sm transition hover:border-primary/60"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium text-foreground">{{ event.title }}</p>
                                        <p class="text-muted-foreground">{{ event.start_at ?? '-' }}</p>
                                    </div>
                                    <Eye class="size-4 text-muted-foreground" />
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span class="rounded-full border px-2 py-1 text-xs font-medium" :class="badgeClass(event.type)">
                                        {{ typeLabels[event.type] ?? event.type }}
                                    </span>
                                    <span class="rounded-full border px-2 py-1 text-xs font-medium" :class="badgeClass(event.status)">
                                        {{ statusLabels[event.status] ?? event.status }}
                                    </span>
                                    <span
                                        v-if="event.priority"
                                        class="rounded-full border px-2 py-1 text-xs font-medium"
                                        :class="badgeClass(event.priority)"
                                    >
                                        {{ priorityLabels[event.priority] ?? event.priority }}
                                    </span>
                                </div>
                                <p class="mt-3 text-muted-foreground">
                                    {{ event.associated?.name ?? 'Sem associação' }} · {{ event.owner?.name ?? '-' }}
                                </p>
                            </Link>
                        </div>
                    </div>
                </div>

                <aside class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <h2 class="font-medium">Lista</h2>
                    <div class="mt-4 space-y-3">
                        <Link
                            v-for="event in events.data"
                            :key="event.id"
                            :href="event.url"
                            class="block rounded-md border p-3 text-sm hover:border-primary/60"
                        >
                            <p class="font-medium text-foreground">{{ event.title }}</p>
                            <p class="text-muted-foreground">{{ event.start_at ?? '-' }}</p>
                        </Link>
                        <p v-if="events.data.length === 0" class="text-sm text-muted-foreground">Sem eventos na listagem.</p>
                    </div>
                </aside>
            </section>
        </div>
    </AppLayout>
</template>
