<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle2, LoaderCircle } from 'lucide-vue-next';

interface LeadField {
    key: string;
    label: string;
    type: string;
    required: boolean;
    placeholder: string | null;
    options?: string[];
}

const props = defineProps<{
    leadForm: {
        name: string;
        slug: string;
        description: string | null;
        fields: LeadField[];
        require_captcha: boolean;
        captcha_driver: string;
        turnstile_site_key: string | null;
    };
    submitted: boolean;
    confirmationMessage?: string;
}>();

const initialData = props.leadForm.fields.reduce(
    (carry, field) => ({
        ...carry,
        [field.key]: '',
    }),
    {
        source_url: document.referrer || window.location.href,
        captcha_token: '',
    } as Record<string, string>,
);

const form = useForm(initialData);

const submit = () => {
    form.post(`/public/lead-forms/${props.leadForm.slug}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="leadForm.name" />

    <main class="min-h-screen bg-muted/30 px-4 py-8 text-foreground">
        <section class="mx-auto max-w-2xl rounded-lg border bg-card p-6 shadow-sm">
            <div v-if="submitted" class="flex min-h-80 flex-col items-center justify-center text-center">
                <CheckCircle2 class="size-12 text-emerald-600" />
                <h1 class="mt-4 text-2xl font-semibold">Pedido recebido</h1>
                <p class="mt-2 max-w-md text-sm text-muted-foreground">
                    {{ confirmationMessage ?? 'Obrigado. Recebemos o seu pedido e entraremos em contacto em breve.' }}
                </p>
            </div>

            <template v-else>
                <header>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ leadForm.name }}</h1>
                    <p v-if="leadForm.description" class="mt-2 text-sm text-muted-foreground">{{ leadForm.description }}</p>
                </header>

                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div v-for="field in leadForm.fields" :key="field.key" class="space-y-2">
                        <label :for="field.key" class="text-sm font-medium">
                            {{ field.label }}
                            <span v-if="field.required" class="text-destructive">*</span>
                        </label>

                        <textarea
                            v-if="field.type === 'textarea'"
                            :id="field.key"
                            v-model="form[field.key]"
                            :placeholder="field.placeholder ?? ''"
                            rows="5"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                        <select
                            v-else-if="field.type === 'select'"
                            :id="field.key"
                            v-model="form[field.key]"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">Selecionar</option>
                            <option v-for="option in field.options ?? []" :key="option" :value="option">{{ option }}</option>
                        </select>
                        <Input
                            v-else
                            :id="field.key"
                            v-model="form[field.key]"
                            :type="field.type === 'email' ? 'email' : field.type === 'phone' ? 'tel' : 'text'"
                            :placeholder="field.placeholder ?? ''"
                        />

                        <p v-if="form.errors[field.key]" class="text-sm text-destructive">{{ form.errors[field.key] }}</p>
                    </div>

                    <div v-if="leadForm.require_captcha" class="rounded-md border border-dashed bg-muted/30 p-3 text-sm text-muted-foreground">
                        <template v-if="leadForm.captcha_driver === 'turnstile' && leadForm.turnstile_site_key">
                            Captcha Turnstile configurado. O token será validado no servidor quando o widget estiver integrado no site.
                        </template>
                        <template v-else> Proteção anti-spam ativa em modo local/teste. </template>
                        <p v-if="form.errors.captcha_token" class="mt-2 text-sm text-destructive">{{ form.errors.captcha_token }}</p>
                    </div>

                    <Button type="submit" class="w-full" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                        Enviar
                    </Button>
                </form>
            </template>
        </section>
    </main>
</template>
