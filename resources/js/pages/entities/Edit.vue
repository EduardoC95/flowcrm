<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    entity: {
        id: number;
        name: string;
        vat: string | null;
        email: string | null;
        phone: string | null;
        address: string | null;
        status: string;
        notes: string | null;
    };
    statuses: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Entidades', href: '/entities' },
    { title: props.entity.name, href: `/entities/${props.entity.id}` },
    { title: 'Editar', href: `/entities/${props.entity.id}/edit` },
];

const form = useForm({
    name: props.entity.name,
    vat: props.entity.vat ?? '',
    email: props.entity.email ?? '',
    phone: props.entity.phone ?? '',
    address: props.entity.address ?? '',
    status: props.entity.status,
    notes: props.entity.notes ?? '',
});

const statusLabels: Record<string, string> = {
    active: 'Ativa',
    inactive: 'Inativa',
    lead: 'Lead',
    client: 'Cliente',
    prospect: 'Prospect',
};

const submit = () => {
    form.put(`/entities/${props.entity.id}`);
};
</script>

<template>
    <Head :title="`Editar ${entity.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <section>
                <h1 class="text-2xl font-semibold tracking-tight">Editar entidade</h1>
                <p class="text-sm text-muted-foreground">{{ entity.name }}</p>
            </section>

            <form
                class="grid max-w-3xl gap-5 rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border"
                @submit.prevent="submit"
            >
                <div class="grid gap-2">
                    <Label for="name">Nome</Label>
                    <Input id="name" v-model="form.name" required autofocus />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="vat">VAT/NIF</Label>
                        <Input id="vat" v-model="form.vat" />
                        <InputError :message="form.errors.vat" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="status">Estado</Label>
                        <select id="status" v-model="form.status" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                            <option v-for="status in statuses" :key="status" :value="status">{{ statusLabels[status] ?? status }}</option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input id="email" v-model="form.email" type="email" />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="phone">Telefone</Label>
                        <Input id="phone" v-model="form.phone" />
                        <InputError :message="form.errors.phone" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="address">Morada</Label>
                    <textarea id="address" v-model="form.address" rows="3" class="rounded-md border border-input bg-background px-3 py-2 text-sm" />
                    <InputError :message="form.errors.address" />
                </div>

                <div class="grid gap-2">
                    <Label for="notes">Notas</Label>
                    <textarea id="notes" v-model="form.notes" rows="4" class="rounded-md border border-input bg-background px-3 py-2 text-sm" />
                    <InputError :message="form.errors.notes" />
                </div>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">Guardar</Button>
                    <Button as-child type="button" variant="outline">
                        <Link :href="`/entities/${entity.id}`">Cancelar</Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
