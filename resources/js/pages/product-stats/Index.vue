<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Download, Search } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

interface Option {
    id: number;
    name: string;
}

interface ProductStatsRow {
    product_id: number;
    product_name: string;
    sku: string | null;
    total_quantity: number;
    total_value: number;
    deals_count: number;
    average_value_per_deal: number;
}

const props = defineProps<{
    rows: ProductStatsRow[];
    summary: {
        products_count: number;
        total_quantity: number;
        total_value: number;
        deals_count: number;
    };
    filters: {
        date_from: string | null;
        date_to: string | null;
        deal_stage_id: number | null;
        owner_id: number | null;
        sort: string;
    };
    stages: Option[];
    owners: Option[];
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Estatísticas de Produtos', href: '/product-stats' }];

const form = reactive({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    deal_stage_id: props.filters.deal_stage_id ? String(props.filters.deal_stage_id) : '',
    owner_id: props.filters.owner_id ? String(props.filters.owner_id) : '',
    sort: props.filters.sort ?? 'value',
});

const exportUrl = computed(() => {
    const params = new URLSearchParams();

    Object.entries(form).forEach(([key, value]) => {
        if (value !== '') {
            params.set(key, String(value));
        }
    });

    return `/product-stats/export?${params.toString()}`;
});

const money = (value: number) =>
    new Intl.NumberFormat('pt-PT', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);

const submit = () => {
    router.get('/product-stats', form, {
        preserveState: true,
        replace: true,
    });
};

const reset = () => {
    Object.assign(form, {
        date_from: '',
        date_to: '',
        deal_stage_id: '',
        owner_id: '',
        sort: 'value',
    });
    submit();
};
</script>

<template>
    <Head title="Estatísticas de Produtos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Estatísticas de Produtos</h1>
                    <p class="text-sm text-muted-foreground">Produtos agregados por negócios criados no período selecionado.</p>
                </div>
                <Button as-child variant="outline">
                    <a :href="exportUrl">
                        <Download class="size-4" />
                        Exportar CSV
                    </a>
                </Button>
            </section>

            <div class="grid gap-4 md:grid-cols-4">
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Produtos analisados</p>
                    <p class="mt-2 text-2xl font-semibold">{{ summary.products_count }}</p>
                </section>
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Quantidade total</p>
                    <p class="mt-2 text-2xl font-semibold">{{ summary.total_quantity }}</p>
                </section>
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Valor total</p>
                    <p class="mt-2 text-2xl font-semibold">{{ money(summary.total_value) }}</p>
                </section>
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Negócios com produtos</p>
                    <p class="mt-2 text-2xl font-semibold">{{ summary.deals_count }}</p>
                </section>
            </div>

            <form
                class="grid gap-3 rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border md:grid-cols-[160px_160px_180px_180px_160px_auto]"
                @submit.prevent="submit"
            >
                <Input v-model="form.date_from" type="date" />
                <Input v-model="form.date_to" type="date" />
                <select v-model="form.deal_stage_id" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="">Todas as etapas</option>
                    <option v-for="stage in stages" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
                </select>
                <select v-model="form.owner_id" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="">Todos os responsáveis</option>
                    <option v-for="owner in owners" :key="owner.id" :value="owner.id">{{ owner.name }}</option>
                </select>
                <select v-model="form.sort" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="value">Ordenar por valor</option>
                    <option value="quantity">Ordenar por quantidade</option>
                </select>
                <div class="flex gap-2">
                    <Button type="submit">
                        <Search class="size-4" />
                        Filtrar
                    </Button>
                    <Button type="button" variant="outline" @click="reset">Limpar</Button>
                </div>
            </form>

            <section class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div v-if="rows.length === 0" class="flex min-h-64 flex-col items-center justify-center gap-2 p-8 text-center">
                    <h2 class="text-lg font-medium">Sem produtos para analisar</h2>
                    <p class="max-w-md text-sm text-muted-foreground">Associa produtos aos negócios ou ajusta os filtros para ver estatísticas.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Produto</th>
                                <th class="px-4 py-3 font-medium">SKU</th>
                                <th class="px-4 py-3 font-medium">Quantidade total</th>
                                <th class="px-4 py-3 font-medium">Valor total</th>
                                <th class="px-4 py-3 font-medium">Nº de negócios</th>
                                <th class="px-4 py-3 font-medium">Média por negócio</th>
                                <th class="px-4 py-3 text-right font-medium">Detalhe</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.product_id" class="border-b last:border-0">
                                <td class="px-4 py-3 font-medium">{{ row.product_name }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.sku ?? '-' }}</td>
                                <td class="px-4 py-3">{{ row.total_quantity }}</td>
                                <td class="px-4 py-3">{{ money(row.total_value) }}</td>
                                <td class="px-4 py-3">{{ row.deals_count }}</td>
                                <td class="px-4 py-3">{{ money(row.average_value_per_deal) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Button as-child variant="ghost" size="sm">
                                        <Link :href="`/products/${row.product_id}`">Ver detalhe</Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
