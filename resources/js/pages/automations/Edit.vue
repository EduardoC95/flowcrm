<script setup lang="ts">
import AutomationForm from '@/components/automations/AutomationForm.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

const props = defineProps<{
    automation: {
        id: number;
        name: string;
        description: string | null;
        trigger_type: string;
        inactivity_days: number;
        action_type: string;
        action_payload: {
            activity_type: string;
            activity_title_template: string;
            activity_description_template: string;
            due_in_days: number;
            priority: string;
        };
        notify_owner: boolean;
        active: boolean;
    };
    options: {
        trigger_types: { value: string; label: string }[];
        action_types: { value: string; label: string }[];
        activity_types: { value: string; label: string }[];
        priorities: { value: string; label: string }[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Automações', href: '/automations' },
    { title: props.automation.name, href: `/automations/${props.automation.id}` },
    { title: 'Editar', href: `/automations/${props.automation.id}/edit` },
];
</script>

<template>
    <Head :title="`Editar ${automation.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <section>
                <Button as-child variant="ghost" size="sm" class="-ml-3 mb-2">
                    <Link :href="`/automations/${automation.id}`">
                        <ArrowLeft class="size-4" />
                        Voltar
                    </Link>
                </Button>
                <h1 class="text-2xl font-semibold tracking-tight">Editar automação</h1>
            </section>

            <AutomationForm
                :submit-url="`/automations/${automation.id}`"
                method="patch"
                submit-label="Guardar alterações"
                :options="options"
                :initial="{
                    ...automation,
                    description: automation.description ?? '',
                }"
            />
        </div>
    </AppLayout>
</template>
