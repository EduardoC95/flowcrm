<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Tenant } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    module: 'entities' | 'people' | 'calendar' | 'deals';
    tenant: Tenant | null;
}>();

const moduleLabels = {
    entities: 'Entidades',
    people: 'Pessoas',
    calendar: 'Calendário',
    deals: 'Negócios',
};

const title = computed(() => moduleLabels[props.module]);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: title.value, href: `/crm/${props.module}` },
];
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">{{ tenant?.name }}</p>
                <h1 class="mt-1 text-2xl font-semibold">{{ title }}</h1>
                <p class="mt-2 text-sm text-muted-foreground">Módulo preparado para receber a próxima camada funcional do CRM.</p>
            </section>
        </div>
    </AppLayout>
</template>
