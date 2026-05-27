<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';

interface LeadField {
    key: string;
    label: string;
    type: string;
    required: boolean;
    placeholder: string | null;
    options?: string[];
}

interface LeadFormData {
    name: string;
    slug: string;
    description: string;
    fields: LeadField[];
    confirmation_message: string;
    active: boolean;
    require_captcha: boolean;
}

const props = defineProps<{
    submitUrl: string;
    method: 'post' | 'patch';
    submitLabel: string;
    initial: LeadFormData;
    defaultFields: LeadField[];
    fieldTypes: { value: string; label: string }[];
}>();

const form = useForm<LeadFormData>({
    name: props.initial.name,
    slug: props.initial.slug,
    description: props.initial.description,
    fields: props.initial.fields.map((field) => ({ ...field, options: field.options ?? [] })),
    confirmation_message: props.initial.confirmation_message,
    active: props.initial.active,
    require_captcha: props.initial.require_captcha,
});

const addField = () => {
    form.fields.push({
        key: `campo_${form.fields.length + 1}`,
        label: 'Novo campo',
        type: 'text',
        required: false,
        placeholder: '',
        options: [],
    });
};

const removeField = (index: number) => {
    form.fields.splice(index, 1);
};

const resetFields = () => {
    form.fields = props.defaultFields.map((field) => ({ ...field, options: field.options ?? [] }));
};

const submit = () => {
    if (props.method === 'patch') {
        form.patch(props.submitUrl);
        return;
    }

    form.post(props.submitUrl);
};
</script>

<template>
    <form class="max-w-5xl space-y-5 rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border" @submit.prevent="submit">
        <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
                <Label for="name">Nome</Label>
                <Input id="name" v-model="form.name" placeholder="Pedido de contacto" />
                <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
            </div>
            <div class="space-y-2">
                <Label for="slug">Slug público</Label>
                <Input id="slug" v-model="form.slug" placeholder="pedido-contacto" />
                <p v-if="form.errors.slug" class="text-sm text-destructive">{{ form.errors.slug }}</p>
            </div>
        </div>

        <div class="space-y-2">
            <Label for="description">Descrição</Label>
            <textarea
                id="description"
                v-model="form.description"
                rows="3"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
            />
        </div>

        <div class="space-y-2">
            <Label for="confirmation_message">Mensagem de confirmação</Label>
            <textarea
                id="confirmation_message"
                v-model="form.confirmation_message"
                rows="3"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
            />
        </div>

        <div class="flex flex-wrap gap-4">
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.active" type="checkbox" class="rounded border-input" />
                Ativo
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.require_captcha" type="checkbox" class="rounded border-input" />
                Captcha obrigatório
            </label>
        </div>

        <section class="space-y-4 rounded-md border bg-muted/20 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-medium">Campos do formulário</h2>
                    <p class="text-sm text-muted-foreground">Configure apenas os dados que quer recolher no site público.</p>
                </div>
                <div class="flex gap-2">
                    <Button type="button" variant="outline" @click="resetFields">Repor recomendados</Button>
                    <Button type="button" @click="addField">
                        <Plus class="size-4" />
                        Adicionar campo
                    </Button>
                </div>
            </div>

            <article v-for="(field, index) in form.fields" :key="index" class="grid gap-3 rounded-md border bg-background p-3 md:grid-cols-12">
                <div class="space-y-2 md:col-span-3">
                    <Label>Etiqueta</Label>
                    <Input v-model="field.label" />
                </div>
                <div class="space-y-2 md:col-span-2">
                    <Label>Chave</Label>
                    <Input v-model="field.key" />
                </div>
                <div class="space-y-2 md:col-span-2">
                    <Label>Tipo</Label>
                    <select v-model="field.type" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option v-for="type in fieldTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                </div>
                <div class="space-y-2 md:col-span-3">
                    <Label>Placeholder</Label>
                    <Input v-model="field.placeholder" />
                </div>
                <label class="flex items-end gap-2 pb-2 text-sm md:col-span-1">
                    <input v-model="field.required" type="checkbox" class="rounded border-input" />
                    Obrig.
                </label>
                <div class="flex items-end md:col-span-1">
                    <Button type="button" variant="ghost" size="icon" title="Remover" @click="removeField(index)">
                        <Trash2 class="size-4" />
                    </Button>
                </div>
                <div v-if="field.type === 'select'" class="space-y-2 md:col-span-12">
                    <Label>Opções separadas por vírgula</Label>
                    <Input
                        :model-value="(field.options ?? []).join(', ')"
                        @update:model-value="
                            field.options = String($event)
                                .split(',')
                                .map((item) => item.trim())
                                .filter(Boolean)
                        "
                    />
                </div>
            </article>
            <p v-if="form.errors.fields" class="text-sm text-destructive">{{ form.errors.fields }}</p>
        </section>

        <div class="flex justify-end gap-2">
            <Button as-child variant="outline">
                <Link href="/lead-forms">Cancelar</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
