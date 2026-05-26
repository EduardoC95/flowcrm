<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

const props = defineProps<{
    product: {
        id: number;
        name: string;
        sku: string | null;
        description: string | null;
        unit_price: string | number;
        active: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Produtos', href: '/products' },
    { title: props.product.name, href: `/products/${props.product.id}` },
    { title: 'Editar', href: `/products/${props.product.id}/edit` },
];

const form = useForm({
    name: props.product.name,
    sku: props.product.sku ?? '',
    description: props.product.description ?? '',
    unit_price: String(props.product.unit_price ?? 0),
    active: props.product.active,
});

const submit = () => {
    form.put(`/products/${props.product.id}`);
};
</script>

<template>
    <Head :title="`Editar ${product.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <section>
                <Button as-child variant="ghost" size="sm" class="-ml-3 mb-2">
                    <Link :href="`/products/${product.id}`">
                        <ArrowLeft class="size-4" />
                        Voltar
                    </Link>
                </Button>
                <h1 class="text-2xl font-semibold tracking-tight">Editar produto</h1>
            </section>

            <form
                class="max-w-3xl space-y-4 rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border"
                @submit.prevent="submit"
            >
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="name">Nome</Label>
                        <Input id="name" v-model="form.name" />
                        <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="sku">SKU</Label>
                        <Input id="sku" v-model="form.sku" />
                        <p v-if="form.errors.sku" class="text-sm text-destructive">{{ form.errors.sku }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="unit_price">Preço unitário</Label>
                        <Input id="unit_price" v-model="form.unit_price" type="number" min="0" step="0.01" />
                        <p v-if="form.errors.unit_price" class="text-sm text-destructive">{{ form.errors.unit_price }}</p>
                    </div>
                    <label class="flex items-center gap-2 pt-8 text-sm">
                        <input v-model="form.active" type="checkbox" class="rounded border-input" />
                        Ativo
                    </label>
                </div>
                <div class="space-y-2">
                    <Label for="description">Descrição</Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="5"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                    <p v-if="form.errors.description" class="text-sm text-destructive">{{ form.errors.description }}</p>
                </div>
                <div class="flex justify-end gap-2">
                    <Button as-child variant="outline">
                        <Link :href="`/products/${product.id}`">Cancelar</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">Guardar</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
