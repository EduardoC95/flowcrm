<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link, useForm } from '@inertiajs/vue3';

interface AutomationFormData {
    name: string;
    description: string;
    trigger_type: string;
    inactivity_days: number | string;
    action_type: string;
    action_payload: {
        activity_type: string;
        activity_title_template: string;
        activity_description_template: string;
        due_in_days: number | string;
        priority: string;
    };
    notify_owner: boolean;
    active: boolean;
}

const props = defineProps<{
    submitUrl: string;
    method: 'post' | 'patch';
    submitLabel: string;
    initial: AutomationFormData;
    options: {
        trigger_types: { value: string; label: string }[];
        action_types: { value: string; label: string }[];
        activity_types: { value: string; label: string }[];
        priorities: { value: string; label: string }[];
    };
}>();

const form = useForm<AutomationFormData>({
    name: props.initial.name,
    description: props.initial.description,
    trigger_type: props.initial.trigger_type,
    inactivity_days: props.initial.inactivity_days,
    action_type: props.initial.action_type,
    action_payload: {
        activity_type: props.initial.action_payload.activity_type,
        activity_title_template: props.initial.action_payload.activity_title_template,
        activity_description_template: props.initial.action_payload.activity_description_template,
        due_in_days: props.initial.action_payload.due_in_days,
        priority: props.initial.action_payload.priority,
    },
    notify_owner: props.initial.notify_owner,
    active: props.initial.active,
});

const submit = () => {
    if (props.method === 'patch') {
        form.patch(props.submitUrl);
        return;
    }

    form.post(props.submitUrl);
};
</script>

<template>
    <form class="max-w-4xl space-y-5 rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border" @submit.prevent="submit">
        <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
                <Label for="name">Nome da automação</Label>
                <Input id="name" v-model="form.name" placeholder="Follow-up de negócios parados" />
                <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
            </div>
            <div class="space-y-2">
                <Label for="inactivity_days">Sem atividade há X dias</Label>
                <Input id="inactivity_days" v-model="form.inactivity_days" type="number" min="1" max="365" />
                <p v-if="form.errors.inactivity_days" class="text-sm text-destructive">{{ form.errors.inactivity_days }}</p>
            </div>
        </div>

        <div class="space-y-2">
            <Label for="description">Descrição</Label>
            <textarea
                id="description"
                v-model="form.description"
                rows="3"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                placeholder="Quando usar esta regra e que resultado deve gerar."
            />
            <p v-if="form.errors.description" class="text-sm text-destructive">{{ form.errors.description }}</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
                <Label for="trigger_type">Gatilho</Label>
                <select id="trigger_type" v-model="form.trigger_type" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                    <option v-for="option in options.trigger_types" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </div>
            <div class="space-y-2">
                <Label for="action_type">Ação</Label>
                <select id="action_type" v-model="form.action_type" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                    <option v-for="option in options.action_types" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </div>
        </div>

        <section class="space-y-4 rounded-md border bg-muted/20 p-4">
            <div>
                <h2 class="font-medium">Atividade a criar</h2>
                <p class="text-sm text-muted-foreground">Pode usar {deal_title}, {owner_name}, {entity_name}, {person_name} e {inactivity_days}.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <Label for="activity_type">Tipo de atividade</Label>
                    <select
                        id="activity_type"
                        v-model="form.action_payload.activity_type"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option v-for="option in options.activity_types" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>
                <div class="space-y-2">
                    <Label for="due_in_days">Prazo em dias</Label>
                    <Input id="due_in_days" v-model="form.action_payload.due_in_days" type="number" min="0" max="365" />
                </div>
            </div>

            <div class="space-y-2">
                <Label for="title_template">Título da atividade</Label>
                <Input id="title_template" v-model="form.action_payload.activity_title_template" />
            </div>

            <div class="space-y-2">
                <Label for="description_template">Descrição da atividade</Label>
                <textarea
                    id="description_template"
                    v-model="form.action_payload.activity_description_template"
                    rows="4"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
            </div>

            <div class="space-y-2">
                <Label for="priority">Prioridade</Label>
                <select
                    id="priority"
                    v-model="form.action_payload.priority"
                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                >
                    <option v-for="option in options.priorities" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </div>
        </section>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.notify_owner" type="checkbox" class="rounded border-input" />
                Notificar responsável
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.active" type="checkbox" class="rounded border-input" />
                Ativa
            </label>
        </div>

        <div class="flex justify-end gap-2">
            <Button as-child variant="outline">
                <Link href="/automations">Cancelar</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
