<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Trash2 } from 'lucide-vue-next';

interface Option {
    id: number;
    name: string;
}

interface ProductDeal {
    id: number;
    quantity: number;
    unit_price: number;
    total: number;
    deal: {
        id: number;
        title: string;
        expected_close_date: string | null;
        stage: Option | null;
        owner: Option | null;
    } | null;
}

const props = defineProps<{
    product: {
        id: number;
        name: string;
        sku: string | null;
        description: string | null;
        unit_price: number;
        active: boolean;
        stats: {
            total_quantity: number;
            total_value: number;
            deals_count: number;
        };
        deals: ProductDeal[];
    };
    can: {
        update: boolean;
        delete: boolean;
    };
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Produtos', href: '/products' },
    { title: props.product.name, href: `/products/${props.product.id}` },
];

const money = (value: number) =>
    new Intl.NumberFormat('pt-PT', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);

const destroy = () => {
    if (confirm(`Apagar o produto "${props.product.name}"?`)) {
        router.delete(`/products/${props.product.id}`);
    }
};
</script>

<template>
    <Head :title="product.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <section class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <Button as-child variant="ghost" size="sm" class="-ml-3 mb-2">
                        <Link href="/products">
                            <ArrowLeft class="size-4" />
                            Voltar
                        </Link>
                    </Button>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ product.name }}</h1>
                    <p class="text-sm text-muted-foreground">{{ product.sku ?? 'Sem SKU' }} · {{ product.active ? 'Ativo' : 'Inativo' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="can.update" as-child>
                        <Link :href="`/products/${product.id}/edit`">
                            <Pencil class="size-4" />
                            Editar
                        </Link>
                    </Button>
                    <Button v-if="can.delete" variant="destructive" @click="destroy">
                        <Trash2 class="size-4" />
                        Apagar
                    </Button>
                </div>
            </section>

            <div class="grid gap-4 md:grid-cols-4">
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Preço unitário</p>
                    <p class="mt-2 text-2xl font-semibold">{{ money(product.unit_price) }}</p>
                </section>
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Quantidade total</p>
                    <p class="mt-2 text-2xl font-semibold">{{ product.stats.total_quantity }}</p>
                </section>
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Valor total</p>
                    <p class="mt-2 text-2xl font-semibold">{{ money(product.stats.total_value) }}</p>
                </section>
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Negócios</p>
                    <p class="mt-2 text-2xl font-semibold">{{ product.stats.deals_count }}</p>
                </section>
            </div>

            <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                <h2 class="font-medium">Negócios onde aparece</h2>
                <p class="mt-2 whitespace-pre-line text-sm text-muted-foreground">{{ product.description ?? 'Sem descrição registada.' }}</p>

                <div class="mt-5 overflow-x-auto">
                    <table v-if="product.deals.length" class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Negócio</th>
                                <th class="px-4 py-3 font-medium">Etapa</th>
                                <th class="px-4 py-3 font-medium">Responsável</th>
                                <th class="px-4 py-3 font-medium">Quantidade</th>
                                <th class="px-4 py-3 font-medium">Valor</th>
                                <th class="px-4 py-3 font-medium">Data prevista</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in product.deals" :key="row.id" class="border-b last:border-0">
                                <td class="px-4 py-3">
                                    <Link v-if="row.deal" :href="`/deals/${row.deal.id}`" class="font-medium text-primary hover:underline">
                                        {{ row.deal.title }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.deal?.stage?.name ?? '-' }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.deal?.owner?.name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ row.quantity }}</td>
                                <td class="px-4 py-3">{{ money(row.total) }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.deal?.expected_close_date ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Este produto ainda não aparece em negócios.
                    </p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
