<script setup lang="ts">
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

interface PersonOption extends Option {
    entity_id: number | null;
    entity_name: string | null;
}

interface StageOption extends Option {
    slug: string;
}

const props = defineProps<{
    entities: Option[];
    people: PersonOption[];
    stages: StageOption[];
    owners: Option[];
    priorities: string[];
    defaults: {
        owner_id: number;
        deal_stage_id: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Negócios', href: '/deals' },
    { title: 'Novo negócio', href: '/deals/create' },
];

const form = useForm({
    title: '',
    entity_id: '',
    person_id: '',
    owner_id: props.defaults.owner_id ? String(props.defaults.owner_id) : '',
    deal_stage_id: props.defaults.deal_stage_id ? String(props.defaults.deal_stage_id) : '',
    value: '0',
    probability: '0',
    expected_close_date: '',
    priority: '',
    description: '',
});

const priorityLabels: Record<string, string> = {
    low: 'Baixa',
    medium: 'Média',
    high: 'Alta',
    urgent: 'Urgente',
};

const submit = () => form.post('/deals');
</script>

<template>
    <Head title="Novo negócio" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex h-full flex-1 flex-col gap-5 p-4" @submit.prevent="submit">
            <section>
                <h1 class="text-2xl font-semibold tracking-tight">Novo negócio</h1>
                <p class="text-sm text-muted-foreground">Regista uma oportunidade associada a uma entidade ou pessoa do tenant ativo.</p>
            </section>

            <div class="grid gap-5 rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border lg:grid-cols-2">
                <div class="space-y-2 lg:col-span-2">
                    <Label for="title">Título</Label>
                    <Input id="title" v-model="form.title" />
                    <p v-if="form.errors.title" class="text-sm text-destructive">{{ form.errors.title }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="entity_id">Entidade</Label>
                    <select id="entity_id" v-model="form.entity_id" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">Sem entidade</option>
                        <option v-for="entity in entities" :key="entity.id" :value="entity.id">{{ entity.name }}</option>
                    </select>
                    <p v-if="form.errors.entity_id" class="text-sm text-destructive">{{ form.errors.entity_id }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="person_id">Pessoa</Label>
                    <select id="person_id" v-model="form.person_id" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">Sem pessoa</option>
                        <option v-for="person in people" :key="person.id" :value="person.id">
                            {{ person.name }}{{ person.entity_name ? ` · ${person.entity_name}` : '' }}
                        </option>
                    </select>
                    <p v-if="form.errors.person_id" class="text-sm text-destructive">{{ form.errors.person_id }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="owner_id">Responsável</Label>
                    <select id="owner_id" v-model="form.owner_id" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option v-for="owner in owners" :key="owner.id" :value="owner.id">{{ owner.name }}</option>
                    </select>
                    <p v-if="form.errors.owner_id" class="text-sm text-destructive">{{ form.errors.owner_id }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="deal_stage_id">Etapa</Label>
                    <select
                        id="deal_stage_id"
                        v-model="form.deal_stage_id"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option v-for="stage in stages" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
                    </select>
                    <p v-if="form.errors.deal_stage_id" class="text-sm text-destructive">{{ form.errors.deal_stage_id }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="value">Valor</Label>
                    <Input id="value" v-model="form.value" type="number" min="0" step="0.01" />
                    <p v-if="form.errors.value" class="text-sm text-destructive">{{ form.errors.value }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="probability">Probabilidade</Label>
                    <Input id="probability" v-model="form.probability" type="number" min="0" max="100" />
                    <p v-if="form.errors.probability" class="text-sm text-destructive">{{ form.errors.probability }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="expected_close_date">Data prevista de fecho</Label>
                    <Input id="expected_close_date" v-model="form.expected_close_date" type="date" />
                    <p v-if="form.errors.expected_close_date" class="text-sm text-destructive">{{ form.errors.expected_close_date }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="priority">Prioridade</Label>
                    <select id="priority" v-model="form.priority" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">Sem prioridade</option>
                        <option v-for="priority in priorities" :key="priority" :value="priority">{{ priorityLabels[priority] ?? priority }}</option>
                    </select>
                    <p v-if="form.errors.priority" class="text-sm text-destructive">{{ form.errors.priority }}</p>
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
                    <Link href="/deals">Cancelar</Link>
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
