<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, KanbanSquare, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { reactive } from 'vue';

interface Option {
    id: number;
    name: string;
}

interface PersonOption extends Option {
    entity_id: number | null;
    entity_name: string | null;
}

interface StageOption extends Option {
    slug: string;
    color: string | null;
}

interface DealRow {
    id: number;
    title: string;
    value: number;
    probability: number;
    expected_close_date: string | null;
    priority: string | null;
    entity: Option | null;
    person: Option | null;
    owner: Option | null;
    stage: StageOption | null;
    active_follow_up: {
        id: number;
        next_send_at: string | null;
        last_sent_at: string | null;
        sent_count: number;
    } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    deals: {
        data: DealRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        search: string | null;
        stage_id: number | null;
        entity_id: number | null;
        person_id: number | null;
        owner_id: number | null;
        expected_close_date_from: string | null;
        expected_close_date_to: string | null;
        min_value: number | null;
        max_value: number | null;
        sort: string;
        direction: string;
    };
    stages: StageOption[];
    entities: Option[];
    people: PersonOption[];
    owners: Option[];
    priorities: string[];
    can: {
        create: boolean;
    };
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Negócios', href: '/deals' }];

const form = reactive({
    search: props.filters.search ?? '',
    stage_id: props.filters.stage_id ? String(props.filters.stage_id) : '',
    entity_id: props.filters.entity_id ? String(props.filters.entity_id) : '',
    person_id: props.filters.person_id ? String(props.filters.person_id) : '',
    owner_id: props.filters.owner_id ? String(props.filters.owner_id) : '',
    expected_close_date_from: props.filters.expected_close_date_from ?? '',
    expected_close_date_to: props.filters.expected_close_date_to ?? '',
    min_value: props.filters.min_value ?? '',
    max_value: props.filters.max_value ?? '',
    sort: props.filters.sort ?? 'expected_close_date',
    direction: props.filters.direction ?? 'asc',
});

const priorityLabels: Record<string, string> = {
    low: 'Baixa',
    medium: 'Média',
    high: 'Alta',
    urgent: 'Urgente',
};

const priorityClass = (priority: string | null) =>
    ({
        low: 'border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-300',
        medium: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-300',
        high: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
        urgent: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300',
    })[priority ?? ''] || 'border-border bg-muted text-muted-foreground';

const money = (value: number) =>
    new Intl.NumberFormat('pt-PT', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);

const submit = () => {
    router.get('/deals', form, {
        preserveState: true,
        replace: true,
    });
};

const reset = () => {
    Object.assign(form, {
        search: '',
        stage_id: '',
        entity_id: '',
        person_id: '',
        owner_id: '',
        expected_close_date_from: '',
        expected_close_date_to: '',
        min_value: '',
        max_value: '',
        sort: 'expected_close_date',
        direction: 'asc',
    });
    submit();
};

const destroy = (deal: DealRow) => {
    if (confirm(`Apagar o negócio "${deal.title}"?`)) {
        router.delete(`/deals/${deal.id}`);
    }
};
</script>

<template>
    <Head title="Negócios" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Negócios</h1>
                    <p class="text-sm text-muted-foreground">Oportunidades comerciais com responsável, etapa, valor e previsão de fecho.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as-child variant="outline">
                        <Link href="/deals-board">
                            <KanbanSquare class="size-4" />
                            Ver Kanban
                        </Link>
                    </Button>
                    <Button v-if="can.create" as-child>
                        <Link href="/deals/create">
                            <Plus class="size-4" />
                            Novo negócio
                        </Link>
                    </Button>
                </div>
            </section>

            <form
                class="grid gap-3 rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border xl:grid-cols-6"
                @submit.prevent="submit"
            >
                <div class="relative xl:col-span-2">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="form.search" class="pl-9" placeholder="Pesquisar por título" />
                </div>
                <select v-model="form.stage_id" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="">Todas as etapas</option>
                    <option v-for="stage in stages" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
                </select>
                <select v-model="form.entity_id" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="">Todas as entidades</option>
                    <option v-for="entity in entities" :key="entity.id" :value="entity.id">{{ entity.name }}</option>
                </select>
                <select v-model="form.person_id" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="">Todas as pessoas</option>
                    <option v-for="person in people" :key="person.id" :value="person.id">{{ person.name }}</option>
                </select>
                <select v-model="form.owner_id" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="">Todos os responsáveis</option>
                    <option v-for="owner in owners" :key="owner.id" :value="owner.id">{{ owner.name }}</option>
                </select>
                <Input v-model="form.expected_close_date_from" type="date" />
                <Input v-model="form.expected_close_date_to" type="date" />
                <Input v-model="form.min_value" type="number" min="0" step="0.01" placeholder="Valor mínimo" />
                <Input v-model="form.max_value" type="number" min="0" step="0.01" placeholder="Valor máximo" />
                <select v-model="form.sort" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="expected_close_date">Data prevista</option>
                    <option value="title">Título</option>
                    <option value="value">Valor</option>
                    <option value="created_at">Data de criação</option>
                </select>
                <select v-model="form.direction" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="asc">Ascendente</option>
                    <option value="desc">Descendente</option>
                </select>
                <div class="flex gap-2">
                    <Button type="submit">Filtrar</Button>
                    <Button type="button" variant="outline" @click="reset">Limpar</Button>
                </div>
            </form>

            <div class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div v-if="deals.data.length === 0" class="flex min-h-64 flex-col items-center justify-center gap-2 p-8 text-center">
                    <h2 class="text-lg font-medium">Ainda não há negócios</h2>
                    <p class="max-w-md text-sm text-muted-foreground">Cria a primeira oportunidade para alimentar o pipeline comercial.</p>
                    <Button v-if="can.create" as-child class="mt-2">
                        <Link href="/deals/create">Criar negócio</Link>
                    </Button>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Negócio</th>
                                <th class="px-4 py-3 font-medium">Etapa</th>
                                <th class="px-4 py-3 font-medium">Cliente</th>
                                <th class="px-4 py-3 font-medium">Responsável</th>
                                <th class="px-4 py-3 font-medium">Valor</th>
                                <th class="px-4 py-3 font-medium">Previsão</th>
                                <th class="px-4 py-3 text-right font-medium">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="deal in deals.data" :key="deal.id" class="border-b last:border-0">
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ deal.title }}</div>
                                    <span
                                        class="mt-1 inline-flex rounded-full border px-2 py-1 text-xs font-medium"
                                        :class="priorityClass(deal.priority)"
                                    >
                                        {{ deal.priority ? priorityLabels[deal.priority] : 'Sem prioridade' }}
                                    </span>
                                    <span
                                        v-if="deal.active_follow_up"
                                        class="ml-2 mt-1 inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700"
                                    >
                                        Follow-up: {{ deal.active_follow_up.next_send_at ?? 'ativo' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border px-2 py-1 text-xs font-medium">
                                        {{ deal.stage?.name ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    <Link v-if="deal.entity" :href="`/entities/${deal.entity.id}`" class="text-primary hover:underline">{{
                                        deal.entity.name
                                    }}</Link>
                                    <Link v-else-if="deal.person" :href="`/people/${deal.person.id}`" class="text-primary hover:underline">{{
                                        deal.person.name
                                    }}</Link>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ deal.owner?.name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ money(deal.value) }}</div>
                                    <div class="text-xs text-muted-foreground">{{ deal.probability }}%</div>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ deal.expected_close_date ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <Button as-child variant="ghost" size="icon" title="Ver">
                                            <Link :href="`/deals/${deal.id}`"><Eye class="size-4" /></Link>
                                        </Button>
                                        <Button as-child variant="ghost" size="icon" title="Editar">
                                            <Link :href="`/deals/${deal.id}/edit`"><Pencil class="size-4" /></Link>
                                        </Button>
                                        <Button variant="ghost" size="icon" title="Apagar" @click="destroy(deal)">
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="deals.total > 0" class="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                <span>A mostrar {{ deals.from }}-{{ deals.to }} de {{ deals.total }}</span>
                <div class="flex flex-wrap gap-1">
                    <Button
                        v-for="link in deals.links"
                        :key="link.label"
                        as-child
                        :disabled="!link.url"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                    >
                        <Link v-if="link.url" :href="link.url" v-html="link.label" />
                        <span v-else v-html="link.label" />
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
