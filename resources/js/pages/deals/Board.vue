<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from 'lucide-vue-next';
import { reactive, ref } from 'vue';

interface Option {
    id: number;
    name: string;
}

interface DealCard {
    id: number;
    title: string;
    value: number;
    probability: number;
    expected_close_date: string | null;
    priority: string | null;
    entity: Option | null;
    person: Option | null;
    owner: Option | null;
}

interface StageColumn {
    id: number;
    name: string;
    slug: string;
    color: string | null;
    deals_count: number;
    total_value: number;
    deals: DealCard[];
}

const props = defineProps<{
    stages: StageColumn[];
    filters: {
        owner_id: number | null;
        expected_close_date_from: string | null;
        expected_close_date_to: string | null;
        min_value: number | null;
        max_value: number | null;
    };
    owners: Option[];
    can: {
        move: boolean;
        create: boolean;
    };
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Negócios', href: '/deals' },
    { title: 'Pipeline', href: '/deals-board' },
];

const form = reactive({
    owner_id: props.filters.owner_id ? String(props.filters.owner_id) : '',
    expected_close_date_from: props.filters.expected_close_date_from ?? '',
    expected_close_date_to: props.filters.expected_close_date_to ?? '',
    min_value: props.filters.min_value ?? '',
    max_value: props.filters.max_value ?? '',
});

const flash = ref('');
const draggedDealId = ref<number | null>(null);

const money = (value: number) =>
    new Intl.NumberFormat('pt-PT', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);

const priorityLabels: Record<string, string> = {
    low: 'Baixa',
    medium: 'Média',
    high: 'Alta',
    urgent: 'Urgente',
};

const submit = () => {
    router.get('/deals-board', form, {
        preserveState: true,
        replace: true,
    });
};

const reset = () => {
    Object.assign(form, {
        owner_id: '',
        expected_close_date_from: '',
        expected_close_date_to: '',
        min_value: '',
        max_value: '',
    });
    submit();
};

const dragStart = (event: DragEvent, deal: DealCard) => {
    if (!props.can.move) {
        return;
    }

    draggedDealId.value = deal.id;
    event.dataTransfer?.setData('text/plain', String(deal.id));
    event.dataTransfer?.setDragImage(event.currentTarget as Element, 20, 20);
};

const dropOnStage = (stage: StageColumn) => {
    const dealId = draggedDealId.value;

    if (!props.can.move || !dealId) {
        return;
    }

    flash.value = '';
    router.patch(
        `/deals/${dealId}/move-stage`,
        { deal_stage_id: stage.id },
        {
            preserveScroll: true,
            onSuccess: () => {
                flash.value = 'Negócio movido com sucesso.';
                draggedDealId.value = null;
            },
            onError: () => {
                flash.value = 'Não foi possível mover o negócio.';
                draggedDealId.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="Pipeline Kanban" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div
                v-if="page.props.flash.success || flash"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
            >
                {{ flash || page.props.flash.success }}
            </div>

            <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Pipeline Kanban</h1>
                    <p class="text-sm text-muted-foreground">Arrasta negócios entre etapas para atualizar o pipeline comercial.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as-child variant="outline">
                        <Link href="/deals">
                            <ArrowLeft class="size-4" />
                            Listagem
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
                class="grid gap-3 rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border md:grid-cols-[180px_160px_160px_150px_150px_auto]"
                @submit.prevent="submit"
            >
                <select v-model="form.owner_id" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="">Todos os responsáveis</option>
                    <option v-for="owner in owners" :key="owner.id" :value="owner.id">{{ owner.name }}</option>
                </select>
                <Input v-model="form.expected_close_date_from" type="date" />
                <Input v-model="form.expected_close_date_to" type="date" />
                <Input v-model="form.min_value" type="number" min="0" step="0.01" placeholder="Valor mínimo" />
                <Input v-model="form.max_value" type="number" min="0" step="0.01" placeholder="Valor máximo" />
                <div class="flex gap-2">
                    <Button type="submit">Filtrar</Button>
                    <Button type="button" variant="outline" @click="reset">Limpar</Button>
                </div>
            </form>

            <div class="grid min-h-[32rem] gap-4 overflow-x-auto pb-2 xl:grid-cols-6">
                <section
                    v-for="stage in stages"
                    :key="stage.id"
                    class="flex min-w-72 flex-col rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                    @dragover.prevent
                    @drop.prevent="dropOnStage(stage)"
                >
                    <header class="border-b p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="size-3 shrink-0 rounded-full" :style="{ backgroundColor: stage.color ?? '#64748b' }" />
                                <h2 class="truncate font-medium">{{ stage.name }}</h2>
                            </div>
                            <span class="rounded-full bg-muted px-2 py-1 text-xs text-muted-foreground">{{ stage.deals_count }}</span>
                        </div>
                        <p class="mt-2 text-sm font-semibold">{{ money(stage.total_value) }}</p>
                    </header>

                    <div class="flex flex-1 flex-col gap-3 p-3">
                        <article
                            v-for="deal in stage.deals"
                            :key="deal.id"
                            :draggable="can.move"
                            class="rounded-lg border bg-background p-3 shadow-sm transition hover:border-primary/50"
                            :class="{ 'cursor-grab active:cursor-grabbing': can.move }"
                            @dragstart="dragStart($event, deal)"
                        >
                            <Link :href="`/deals/${deal.id}`" class="font-medium text-foreground hover:underline">{{ deal.title }}</Link>
                            <div class="mt-3 grid gap-2 text-xs text-muted-foreground">
                                <div class="flex items-center justify-between gap-2">
                                    <span>{{ money(deal.value) }}</span>
                                    <span>{{ deal.probability }}%</span>
                                </div>
                                <div>{{ deal.entity?.name ?? deal.person?.name ?? 'Sem cliente associado' }}</div>
                                <div>Responsável: {{ deal.owner?.name ?? '-' }}</div>
                                <div>Fecho: {{ deal.expected_close_date ?? '-' }}</div>
                                <div>Prioridade: {{ deal.priority ? priorityLabels[deal.priority] : '-' }}</div>
                            </div>
                        </article>

                        <div
                            v-if="stage.deals.length === 0"
                            class="flex min-h-32 items-center justify-center rounded-lg border border-dashed text-center text-sm text-muted-foreground"
                        >
                            Sem negócios nesta etapa
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
