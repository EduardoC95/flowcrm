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
        openDeals: number;
        pipelineValue: number;
    };
    dealsByStage: {
        id: number;
        name: string;
        slug: string;
        color: string | null;
        deals_count: number;
    }[];
    upcomingDeals: {
        id: number;
        title: string;
        value: number;
        expected_close_date: string | null;
        entity: { id: number; name: string } | null;
        person: { id: number; name: string } | null;
        stage: { id: number; name: string; slug: string; color: string | null } | null;
    }[];
}>();

const shortcuts = [
    { title: 'Entidades', href: '/entities', icon: Building2, description: 'Empresas, clientes e organizações.' },
    { title: 'Pessoas', href: '/people', icon: UsersRound, description: 'Contactos e decisores associados.' },
    { title: 'Calendário', href: '/crm/calendar', icon: CalendarDays, description: 'Reuniões, tarefas e follow-ups.' },
    { title: 'Negócios', href: '/deals', icon: BriefcaseBusiness, description: 'Pipeline comercial e oportunidades.' },
];

const money = (value: number) =>
    new Intl.NumberFormat('pt-PT', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
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

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Pipeline aberto</p>
                            <p class="mt-2 text-3xl font-semibold">{{ money(stats.pipelineValue) }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-muted-foreground">Negócios abertos</p>
                            <p class="mt-2 text-3xl font-semibold">{{ stats.openDeals }}</p>
                        </div>
                    </div>
                    <div class="mt-5 space-y-2">
                        <div
                            v-for="stage in dealsByStage"
                            :key="stage.id"
                            class="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                        >
                            <span class="flex items-center gap-2">
                                <span class="size-2.5 rounded-full" :style="{ backgroundColor: stage.color ?? '#64748b' }" />
                                {{ stage.name }}
                            </span>
                            <span class="text-muted-foreground">{{ stage.deals_count }}</span>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-medium">Próximos fechos</h2>
                        <Link href="/deals-board" class="text-sm text-primary hover:underline">Ver Kanban</Link>
                    </div>
                    <div class="mt-4 space-y-3">
                        <Link
                            v-for="deal in upcomingDeals"
                            :key="deal.id"
                            :href="`/deals/${deal.id}`"
                            class="block rounded-md border p-3 text-sm transition hover:border-primary/60"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-foreground">{{ deal.title }}</p>
                                    <p class="text-muted-foreground">{{ deal.entity?.name ?? deal.person?.name ?? 'Sem cliente associado' }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium text-foreground">{{ money(deal.value) }}</p>
                                    <p class="text-muted-foreground">{{ deal.expected_close_date ?? '-' }}</p>
                                </div>
                            </div>
                        </Link>
                        <p v-if="upcomingDeals.length === 0" class="text-sm text-muted-foreground">Sem fechos previstos no pipeline aberto.</p>
                    </div>
                </section>
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
