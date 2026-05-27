<script setup lang="ts">
import LeadFormBuilder from '@/components/lead-forms/LeadFormBuilder.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

interface LeadField {
    key: string;
    label: string;
    type: string;
    required: boolean;
    placeholder: string | null;
    options?: string[];
}

const props = defineProps<{
    defaults: { fields: LeadField[] };
    fieldTypes: { value: string; label: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Formulários de Leads', href: '/lead-forms' },
    { title: 'Novo formulário', href: '/lead-forms/create' },
];
</script>

<template>
    <Head title="Novo formulário de lead" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <section>
                <Button as-child variant="ghost" size="sm" class="-ml-3 mb-2">
                    <Link href="/lead-forms">
                        <ArrowLeft class="size-4" />
                        Voltar
                    </Link>
                </Button>
                <h1 class="text-2xl font-semibold tracking-tight">Novo formulário de lead</h1>
            </section>

            <LeadFormBuilder
                submit-url="/lead-forms"
                method="post"
                submit-label="Criar formulário"
                :default-fields="defaults.fields"
                :field-types="fieldTypes"
                :initial="{
                    name: '',
                    slug: '',
                    description: '',
                    fields: defaults.fields,
                    confirmation_message: 'Obrigado. Recebemos o seu pedido e entraremos em contacto em breve.',
                    active: true,
                    require_captcha: true,
                }"
            />
        </div>
    </AppLayout>
</template>
