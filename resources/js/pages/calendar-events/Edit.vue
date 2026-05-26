<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Option {
    id: number;
    name?: string;
    title?: string;
    entity_name?: string | null;
}

const props = defineProps<{
    event: {
        id: number;
        title: string;
        description: string | null;
        type: string;
        status: string;
        owner_id: number;
        start_at: string | null;
        end_at: string | null;
        reminder_at: string | null;
        priority: string | null;
        location: string | null;
        eventable_type: string | null;
        eventable_id: number | null;
    };
    entities: Option[];
    people: Option[];
    deals: Option[];
    owners: Option[];
    types: string[];
    statuses: string[];
    priorities: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Calendário', href: '/calendar' },
    { title: props.event.title, href: `/calendar-events/${props.event.id}` },
    { title: 'Editar', href: `/calendar-events/${props.event.id}/edit` },
];

const form = useForm({
    title: props.event.title ?? '',
    description: props.event.description ?? '',
    type: props.event.type ?? 'task',
    status: props.event.status ?? 'pending',
    owner_id: props.event.owner_id ? String(props.event.owner_id) : '',
    start_at: props.event.start_at ?? '',
    end_at: props.event.end_at ?? '',
    reminder_at: props.event.reminder_at ?? '',
    priority: props.event.priority ?? '',
    location: props.event.location ?? '',
    eventable_type: props.event.eventable_type ?? '',
    eventable_id: props.event.eventable_id ? String(props.event.eventable_id) : '',
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

const associatedOptions = computed(() => {
    if (form.eventable_type === 'entity') {
        return props.entities.map((entity) => ({ id: entity.id, label: entity.name ?? '' }));
    }

    if (form.eventable_type === 'person') {
        return props.people.map((person) => ({
            id: person.id,
            label: `${person.name ?? ''}${person.entity_name ? ` · ${person.entity_name}` : ''}`,
        }));
    }

    if (form.eventable_type === 'deal') {
        return props.deals.map((deal) => ({ id: deal.id, label: deal.title ?? '' }));
    }

    return [];
});

const submit = () => form.put(`/calendar-events/${props.event.id}`);
</script>

<template>
    <Head :title="`Editar ${event.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex h-full flex-1 flex-col gap-5 p-4" @submit.prevent="submit">
            <section>
                <h1 class="text-2xl font-semibold tracking-tight">Editar evento</h1>
                <p class="text-sm text-muted-foreground">Atualiza agenda, associação e lembretes desta atividade.</p>
            </section>

            <div class="grid gap-5 rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border lg:grid-cols-2">
                <div class="space-y-2 lg:col-span-2">
                    <Label for="title">Título</Label>
                    <Input id="title" v-model="form.title" />
                    <p v-if="form.errors.title" class="text-sm text-destructive">{{ form.errors.title }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="type">Tipo</Label>
                    <select id="type" v-model="form.type" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option v-for="type in types" :key="type" :value="type">{{ typeLabels[type] ?? type }}</option>
                    </select>
                    <p v-if="form.errors.type" class="text-sm text-destructive">{{ form.errors.type }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="status">Estado</Label>
                    <select id="status" v-model="form.status" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option v-for="status in statuses" :key="status" :value="status">{{ statusLabels[status] ?? status }}</option>
                    </select>
                    <p v-if="form.errors.status" class="text-sm text-destructive">{{ form.errors.status }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="owner_id">Responsável</Label>
                    <select id="owner_id" v-model="form.owner_id" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option v-for="owner in owners" :key="owner.id" :value="owner.id">{{ owner.name }}</option>
                    </select>
                    <p v-if="form.errors.owner_id" class="text-sm text-destructive">{{ form.errors.owner_id }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="priority">Prioridade</Label>
                    <select id="priority" v-model="form.priority" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">Sem prioridade</option>
                        <option v-for="priority in priorities" :key="priority" :value="priority">{{ priorityLabels[priority] ?? priority }}</option>
                    </select>
                    <p v-if="form.errors.priority" class="text-sm text-destructive">{{ form.errors.priority }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="start_at">Início</Label>
                    <Input id="start_at" v-model="form.start_at" type="datetime-local" />
                    <p v-if="form.errors.start_at" class="text-sm text-destructive">{{ form.errors.start_at }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="end_at">Fim</Label>
                    <Input id="end_at" v-model="form.end_at" type="datetime-local" />
                    <p v-if="form.errors.end_at" class="text-sm text-destructive">{{ form.errors.end_at }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="reminder_at">Lembrete em</Label>
                    <Input id="reminder_at" v-model="form.reminder_at" type="datetime-local" />
                    <p v-if="form.errors.reminder_at" class="text-sm text-destructive">{{ form.errors.reminder_at }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="location">Localização</Label>
                    <Input id="location" v-model="form.location" />
                    <p v-if="form.errors.location" class="text-sm text-destructive">{{ form.errors.location }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="eventable_type">Associação</Label>
                    <select
                        id="eventable_type"
                        v-model="form.eventable_type"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                        @change="form.eventable_id = ''"
                    >
                        <option value="">Sem associação</option>
                        <option value="entity">Entidade</option>
                        <option value="person">Pessoa</option>
                        <option value="deal">Negócio</option>
                    </select>
                    <p v-if="form.errors.eventable_type" class="text-sm text-destructive">{{ form.errors.eventable_type }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="eventable_id">Registo associado</Label>
                    <select
                        id="eventable_id"
                        v-model="form.eventable_id"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                        :disabled="!form.eventable_type"
                    >
                        <option value="">Selecionar</option>
                        <option v-for="option in associatedOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <p v-if="form.errors.eventable_id" class="text-sm text-destructive">{{ form.errors.eventable_id }}</p>
                </div>

                <div class="space-y-2 lg:col-span-2">
                    <Label for="description">Descrição</Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="5"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                    <p v-if="form.errors.description" class="text-sm text-destructive">{{ form.errors.description }}</p>
                </div>
            </div>

            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">Guardar</Button>
                <Button as-child variant="outline">
                    <Link :href="`/calendar-events/${event.id}`">Cancelar</Link>
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
