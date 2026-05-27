<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Option {
    id: number;
    name: string;
}

const props = defineProps<{
    dealId: number;
    owners: Option[];
    canCreate: boolean;
    defaultOwnerId: number | null;
    defaultPriority: string | null;
}>();

const form = useForm({
    type: 'note',
    title: '',
    body: '',
    description: '',
    start_at: '',
    end_at: '',
    owner_id: props.defaultOwnerId ? String(props.defaultOwnerId) : '',
    priority: props.defaultPriority ?? 'medium',
});

const isNote = computed(() => form.type === 'note');

const submit = () => {
    form.post(`/deals/${props.dealId}/quick-activities`, {
        preserveScroll: true,
        only: ['deal', 'timeline', 'timelineFilters', 'activityOwners', 'can', 'productOptions', 'flash'],
        onSuccess: () => {
            form.reset('title', 'body', 'description', 'start_at', 'end_at');
            form.type = 'note';
            form.owner_id = props.defaultOwnerId ? String(props.defaultOwnerId) : '';
            form.priority = props.defaultPriority ?? 'medium';
        },
    });
};
</script>

<template>
    <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
        <h2 class="font-medium">Atividade rápida</h2>
        <p class="mt-1 text-sm text-muted-foreground">Regista notas, tarefas, chamadas, reuniões e lembretes sem sair do negócio.</p>

        <form v-if="canCreate" class="mt-4 space-y-4" @submit.prevent="submit">
            <div class="grid gap-3 md:grid-cols-2">
                <div class="space-y-2">
                    <Label for="quick_type">Tipo</Label>
                    <select id="quick_type" v-model="form.type" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option value="note">Nota</option>
                        <option value="task">Tarefa</option>
                        <option value="call">Chamada</option>
                        <option value="meeting">Reunião</option>
                        <option value="reminder">Lembrete</option>
                    </select>
                    <p v-if="form.errors.type" class="text-sm text-destructive">{{ form.errors.type }}</p>
                </div>
                <div v-if="!isNote" class="space-y-2">
                    <Label for="quick_title">Título</Label>
                    <Input id="quick_title" v-model="form.title" />
                    <p v-if="form.errors.title" class="text-sm text-destructive">{{ form.errors.title }}</p>
                </div>
            </div>

            <div class="space-y-2">
                <Label for="quick_body">{{ isNote ? 'Nota' : 'Descrição' }}</Label>
                <textarea
                    id="quick_body"
                    v-model="form.body"
                    rows="4"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
                <p v-if="form.errors.body" class="text-sm text-destructive">{{ form.errors.body }}</p>
                <p v-if="form.errors.description" class="text-sm text-destructive">{{ form.errors.description }}</p>
            </div>

            <div v-if="!isNote" class="grid gap-3 md:grid-cols-2">
                <div class="space-y-2">
                    <Label for="quick_start">Data/hora</Label>
                    <Input id="quick_start" v-model="form.start_at" type="datetime-local" />
                    <p v-if="form.errors.start_at" class="text-sm text-destructive">{{ form.errors.start_at }}</p>
                </div>
                <div class="space-y-2">
                    <Label for="quick_end">Fim</Label>
                    <Input id="quick_end" v-model="form.end_at" type="datetime-local" />
                    <p v-if="form.errors.end_at" class="text-sm text-destructive">{{ form.errors.end_at }}</p>
                </div>
                <div class="space-y-2">
                    <Label for="quick_owner">Responsável</Label>
                    <select id="quick_owner" v-model="form.owner_id" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">Selecionar responsável</option>
                        <option v-for="owner in owners" :key="owner.id" :value="owner.id">{{ owner.name }}</option>
                    </select>
                    <p v-if="form.errors.owner_id" class="text-sm text-destructive">{{ form.errors.owner_id }}</p>
                </div>
                <div class="space-y-2">
                    <Label for="quick_priority">Prioridade</Label>
                    <select id="quick_priority" v-model="form.priority" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option value="low">Baixa</option>
                        <option value="medium">Média</option>
                        <option value="high">Alta</option>
                        <option value="urgent">Urgente</option>
                    </select>
                    <p v-if="form.errors.priority" class="text-sm text-destructive">{{ form.errors.priority }}</p>
                </div>
            </div>

            <Button type="submit" :disabled="form.processing" class="w-full">Adicionar atividade</Button>
        </form>

        <p v-else class="mt-4 rounded-md border border-dashed p-4 text-sm text-muted-foreground">
            O teu perfil pode consultar a cronologia, mas não criar atividades rápidas.
        </p>
    </section>
</template>
