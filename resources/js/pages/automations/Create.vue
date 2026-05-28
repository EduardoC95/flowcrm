<script setup lang="ts">
import AutomationForm from '@/components/automations/AutomationForm.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

defineProps<{
    defaults: {
        activity_type: string;
        activity_title_template: string;
        activity_description_template: string;
        due_in_days: number;
        priority: string;
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
    { title: 'Nova automação', href: '/automations/create' },
];
</script>

<template>
    <Head title="Nova automação" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <section>
                <Button as-child variant="ghost" size="sm" class="-ml-3 mb-2">
                    <Link href="/automations">
                        <ArrowLeft class="size-4" />
                        Voltar
                    </Link>
                </Button>
                <h1 class="text-2xl font-semibold tracking-tight">Nova automação</h1>
                <p class="text-sm text-muted-foreground">Crie uma regra simples para negócios sem atividade.</p>
            </section>

            <AutomationForm
                submit-url="/automations"
                method="post"
                submit-label="Criar automação"
                :options="options"
                :initial="{
                    name: '',
                    description: '',
                    trigger_type: 'deal_inactivity',
                    inactivity_days: 5,
                    action_type: 'create_calendar_activity',
                    action_payload: defaults,
                    notify_owner: true,
                    active: true,
                }"
            />
        </div>
    </AppLayout>
</template>
