<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { reactive } from 'vue';

interface ProductRow {
    id: number;
    name: string;
    sku: string | null;
    unit_price: number;
    active: boolean;
    deal_products_count: number | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    products: {
        data: ProductRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        search: string | null;
        active: string | null;
        sort: string;
        direction: string;
    };
    can: {
        create: boolean;
    };
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Produtos', href: '/products' }];

const form = reactive({
    search: props.filters.search ?? '',
    active: props.filters.active ?? '',
    sort: props.filters.sort ?? 'name',
    direction: props.filters.direction ?? 'asc',
});

const money = (value: number) =>
    new Intl.NumberFormat('pt-PT', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);

const submit = () => {
    router.get('/products', form, {
        preserveState: true,
        replace: true,
    });
};

const reset = () => {
    Object.assign(form, {
        search: '',
        active: '',
        sort: 'name',
        direction: 'asc',
    });
    submit();
};

const destroy = (product: ProductRow) => {
    if (confirm(`Apagar o produto "${product.name}"?`)) {
        router.delete(`/products/${product.id}`);
    }
};
</script>

<template>
    <Head title="Produtos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Produtos</h1>
                    <p class="text-sm text-muted-foreground">Catálogo comercial para associar produtos aos negócios.</p>
                </div>
                <Button v-if="can.create" as-child>
                    <Link href="/products/create">
                        <Plus class="size-4" />
                        Novo produto
                    </Link>
                </Button>
            </section>

            <form
                class="grid gap-3 rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border md:grid-cols-5"
                @submit.prevent="submit"
            >
                <div class="relative md:col-span-2">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="form.search" class="pl-9" placeholder="Pesquisar por nome, SKU ou descrição" />
                </div>
                <select v-model="form.active" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="">Todos</option>
                    <option value="active">Ativos</option>
                    <option value="inactive">Inativos</option>
                </select>
                <select v-model="form.sort" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="name">Nome</option>
                    <option value="created_at">Data de criação</option>
                </select>
                <div class="flex gap-2">
                    <Button type="submit">Filtrar</Button>
                    <Button type="button" variant="outline" @click="reset">Limpar</Button>
                </div>
            </form>

            <div class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div v-if="products.data.length === 0" class="flex min-h-64 flex-col items-center justify-center gap-2 p-8 text-center">
                    <h2 class="text-lg font-medium">Ainda não há produtos</h2>
                    <p class="max-w-md text-sm text-muted-foreground">Cria produtos para medir presença e valor nos negócios.</p>
                    <Button v-if="can.create" as-child class="mt-2">
                        <Link href="/products/create">Criar produto</Link>
                    </Button>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Produto</th>
                                <th class="px-4 py-3 font-medium">SKU</th>
                                <th class="px-4 py-3 font-medium">Preço</th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                                <th class="px-4 py-3 font-medium">Linhas em negócios</th>
                                <th class="px-4 py-3 text-right font-medium">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="product in products.data" :key="product.id" class="border-b last:border-0">
                                <td class="px-4 py-3 font-medium">{{ product.name }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ product.sku ?? '-' }}</td>
                                <td class="px-4 py-3">{{ money(product.unit_price) }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full border px-2 py-1 text-xs font-medium"
                                        :class="
                                            product.active
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                : 'border-zinc-200 bg-zinc-50 text-zinc-700'
                                        "
                                    >
                                        {{ product.active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ product.deal_products_count ?? 0 }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <Button as-child variant="ghost" size="icon" title="Ver">
                                            <Link :href="`/products/${product.id}`"><Eye class="size-4" /></Link>
                                        </Button>
                                        <Button as-child variant="ghost" size="icon" title="Editar">
                                            <Link :href="`/products/${product.id}/edit`"><Pencil class="size-4" /></Link>
                                        </Button>
                                        <Button variant="ghost" size="icon" title="Apagar" @click="destroy(product)">
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="products.total > 0" class="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                <span>A mostrar {{ products.from }}-{{ products.to }} de {{ products.total }}</span>
                <div class="flex flex-wrap gap-1">
                    <Button
                        v-for="link in products.links"
                        :key="link.label"
                        as-child
                        :disabled="!link.url"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                    >
                        <Link v-if="link.url" :href="link.url"><span v-html="link.label" /></Link>
                        <span v-else v-html="link.label" />
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
