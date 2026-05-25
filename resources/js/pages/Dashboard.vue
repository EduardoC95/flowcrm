<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Tenant } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { BriefcaseBusiness, Building2, CalendarDays, UsersRound } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

defineProps<{
    tenant: Tenant | null;
    stats: {
        entities: number;
        people: number;
        calendarEvents: number;
        deals: number;
    };
}>();

const shortcuts = [
    { title: 'Entidades', href: '/crm/entities', icon: Building2, description: 'Empresas, clientes e organizações.' },
    { title: 'Pessoas', href: '/crm/people', icon: UsersRound, description: 'Contactos e decisores associados.' },
    { title: 'Calendário', href: '/crm/calendar', icon: CalendarDays, description: 'Reuniões, tarefas e follow-ups.' },
    { title: 'Negócios', href: '/crm/deals', icon: BriefcaseBusiness, description: 'Pipeline comercial e oportunidades.' },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">Tenant ativo</p>
                <h1 class="mt-1 text-2xl font-semibold text-foreground">{{ tenant?.name ?? 'Sem tenant selecionado' }}</h1>
                <p class="mt-2 text-sm text-muted-foreground">Área comercial preparada para entidades, pessoas, calendário e negócios.</p>
            </section>

            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Entidades</p>
                    <p class="mt-2 text-3xl font-semibold">{{ stats.entities }}</p>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Pessoas</p>
                    <p class="mt-2 text-3xl font-semibold">{{ stats.people }}</p>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Eventos</p>
                    <p class="mt-2 text-3xl font-semibold">{{ stats.calendarEvents }}</p>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Negócios</p>
                    <p class="mt-2 text-3xl font-semibold">{{ stats.deals }}</p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Link
                    v-for="shortcut in shortcuts"
                    :key="shortcut.title"
                    :href="shortcut.href"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-4 transition hover:border-primary/60 dark:border-sidebar-border"
                >
                    <component :is="shortcut.icon" class="size-5 text-muted-foreground" />
                    <h2 class="mt-4 font-medium text-foreground">{{ shortcut.title }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">{{ shortcut.description }}</p>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
