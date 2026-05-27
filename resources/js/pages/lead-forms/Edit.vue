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
    leadForm: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        fields: LeadField[];
        confirmation_message: string | null;
        active: boolean;
        require_captcha: boolean;
    };
    fieldTypes: { value: string; label: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Formulários de Leads', href: '/lead-forms' },
    { title: props.leadForm.name, href: `/lead-forms/${props.leadForm.id}` },
    { title: 'Editar', href: `/lead-forms/${props.leadForm.id}/edit` },
];

const defaultFields: LeadField[] = [
    { key: 'name', label: 'Nome', type: 'text', required: true, placeholder: 'O seu nome' },
    { key: 'email', label: 'Email', type: 'email', required: true, placeholder: 'email@empresa.pt' },
    { key: 'phone', label: 'Telefone', type: 'phone', required: false, placeholder: '+351 ...' },
    { key: 'company', label: 'Empresa', type: 'text', required: false, placeholder: 'Nome da empresa' },
    { key: 'message', label: 'Mensagem', type: 'textarea', required: false, placeholder: 'Como podemos ajudar?' },
];
</script>

<template>
    <Head :title="`Editar ${leadForm.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <section>
                <Button as-child variant="ghost" size="sm" class="-ml-3 mb-2">
                    <Link :href="`/lead-forms/${leadForm.id}`">
                        <ArrowLeft class="size-4" />
                        Voltar
                    </Link>
                </Button>
                <h1 class="text-2xl font-semibold tracking-tight">Editar formulário</h1>
            </section>

            <LeadFormBuilder
                :submit-url="`/lead-forms/${leadForm.id}`"
                method="patch"
                submit-label="Guardar alterações"
                :default-fields="defaultFields"
                :field-types="fieldTypes"
                :initial="{
                    ...leadForm,
                    description: leadForm.description ?? '',
                    confirmation_message: leadForm.confirmation_message ?? '',
                }"
            />
        </div>
    </AppLayout>
</template>
