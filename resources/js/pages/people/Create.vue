<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

interface Option {
    id: number;
    name: string;
}

defineProps<{
    statuses: string[];
    entities: Option[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pessoas', href: '/people' },
    { title: 'Nova', href: '/people/create' },
];

const form = useForm({
    name: '',
    entity_id: '',
    email: '',
    phone: '',
    position: '',
    status: 'active',
    notes: '',
});

const statusLabels: Record<string, string> = {
    active: 'Ativa',
    inactive: 'Inativa',
    lead: 'Lead',
    client: 'Cliente',
    prospect: 'Prospect',
};

const submit = () => {
    form.post('/people');
};
</script>

<template>
    <Head title="Nova pessoa" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <section>
                <h1 class="text-2xl font-semibold tracking-tight">Nova pessoa</h1>
                <p class="text-sm text-muted-foreground">Cria um contacto individual no tenant ativo.</p>
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
                        <Label for="entity_id">Entidade associada</Label>
                        <select id="entity_id" v-model="form.entity_id" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">Sem entidade</option>
                            <option v-for="entity in entities" :key="entity.id" :value="entity.id">{{ entity.name }}</option>
                        </select>
                        <InputError :message="form.errors.entity_id" />
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
                    <Label for="position">Cargo/Função</Label>
                    <Input id="position" v-model="form.position" />
                    <InputError :message="form.errors.position" />
                </div>

                <div class="grid gap-2">
                    <Label for="notes">Notas</Label>
                    <textarea id="notes" v-model="form.notes" rows="4" class="rounded-md border border-input bg-background px-3 py-2 text-sm" />
                    <InputError :message="form.errors.notes" />
                </div>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">Guardar</Button>
                    <Button as-child type="button" variant="outline">
                        <Link href="/people">Cancelar</Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
