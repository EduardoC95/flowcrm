<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Tenant } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Bell, Bot, BriefcaseBusiness, Building2, CalendarDays, ClipboardList, MessageSquare, UsersRound, Workflow } from 'lucide-vue-next';

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
        todayEvents: number;
        pendingTasks: number;
        activeAutomations: number;
        automationActivities: number;
        leadFormsActive: number;
        leadSubmissions: number;
        aiChatConversations: number;
        aiSuggestionsPending: number;
        aiSuggestionsHighImpact: number;
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
    upcomingActivities: {
        id: number;
        title: string;
        type: string;
        start_at: string | null;
        owner: { id: number; name: string } | null;
        url: string;
    }[];
    latestNotifications: {
        id: number;
        title: string;
        body: string | null;
        type: string;
        read_at: string | null;
        created_at: string | null;
    }[];
    latestLeadSubmissions: {
        id: number;
        name: string | null;
        email: string | null;
        submitted_at: string | null;
        lead_form: { id: number; name: string } | null;
        created_deal: { id: number; title: string } | null;
        created_person: { id: number; name: string; email: string | null } | null;
    }[];
    latestAISuggestions: {
        id: number;
        title: string;
        reason: string;
        priority: string;
        score: number;
        deal: { id: number; title: string; value: number } | null;
        person: { id: number; name: string } | null;
        entity: { id: number; name: string } | null;
        url: string;
    }[];
}>();

const shortcuts = [
    { title: 'Entidades', href: '/entities', icon: Building2, description: 'Empresas, clientes e organizações.' },
    { title: 'Pessoas', href: '/people', icon: UsersRound, description: 'Contactos e decisores associados.' },
    { title: 'Calendário', href: '/calendar', icon: CalendarDays, description: 'Reuniões, tarefas e follow-ups.' },
    { title: 'Negócios', href: '/deals', icon: BriefcaseBusiness, description: 'Pipeline comercial e oportunidades.' },
    { title: 'Chat CRM', href: '/ai-chat', icon: MessageSquare, description: 'Perguntas inteligentes sobre dados do CRM.' },
    { title: 'Agente Comercial', href: '/ai-suggestions', icon: Bot, description: 'Sugestões AI para próximos passos.' },
    { title: 'Formulários de Leads', href: '/lead-forms', icon: ClipboardList, description: 'Captação pública ligada ao CRM.' },
    { title: 'Automações', href: '/automations', icon: Workflow, description: 'Regras para negócios sem atividade.' },
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
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <section class="rounded-lg border border-border/70 bg-card/95 p-5 shadow-[0_16px_44px_-34px_rgba(15,23,42,0.6)]">
                <p class="text-sm text-muted-foreground">Tenant ativo</p>
                <h1 class="mt-1 text-2xl font-semibold text-foreground">{{ tenant?.name ?? 'Sem tenant selecionado' }}</h1>
                <p class="mt-2 text-sm text-muted-foreground">Área comercial preparada para entidades, pessoas, calendário e negócios.</p>
            </section>

            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-lg border border-border/70 bg-card/95 p-4 shadow-[0_14px_38px_-32px_rgba(15,23,42,0.5)]">
                    <p class="text-sm text-muted-foreground">Entidades</p>
                    <p class="mt-2 text-3xl font-semibold">{{ stats.entities }}</p>
                </div>
                <div class="rounded-lg border border-border/70 bg-card/95 p-4 shadow-[0_14px_38px_-32px_rgba(15,23,42,0.5)]">
                    <p class="text-sm text-muted-foreground">Pessoas</p>
                    <p class="mt-2 text-3xl font-semibold">{{ stats.people }}</p>
                </div>
                <div class="rounded-lg border border-border/70 bg-card/95 p-4 shadow-[0_14px_38px_-32px_rgba(15,23,42,0.5)]">
                    <p class="text-sm text-muted-foreground">Eventos</p>
                    <p class="mt-2 text-3xl font-semibold">{{ stats.calendarEvents }}</p>
                </div>
                <div class="rounded-lg border border-border/70 bg-card/95 p-4 shadow-[0_14px_38px_-32px_rgba(15,23,42,0.5)]">
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

            <div class="grid gap-4 lg:grid-cols-[1fr_1fr]">
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-sm text-muted-foreground">Eventos de hoje</p>
                            <p class="mt-2 text-3xl font-semibold">{{ stats.todayEvents }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Tarefas pendentes</p>
                            <p class="mt-2 text-3xl font-semibold">{{ stats.pendingTasks }}</p>
                        </div>
                    </div>
                    <Link href="/calendar" class="mt-4 inline-flex text-sm text-primary hover:underline">Abrir calendário</Link>
                </section>

                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-sm text-muted-foreground">Automações ativas</p>
                            <p class="mt-2 text-3xl font-semibold">{{ stats.activeAutomations }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Atividades por automação</p>
                            <p class="mt-2 text-3xl font-semibold">{{ stats.automationActivities }}</p>
                        </div>
                    </div>
                    <Link href="/automations" class="mt-4 inline-flex text-sm text-primary hover:underline">Gerir automações</Link>
                </section>
            </div>

            <div class="grid gap-4 lg:grid-cols-[1fr_1fr]">
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-sm text-muted-foreground">Formulários ativos</p>
                            <p class="mt-2 text-3xl font-semibold">{{ stats.leadFormsActive }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Leads recebidas</p>
                            <p class="mt-2 text-3xl font-semibold">{{ stats.leadSubmissions }}</p>
                        </div>
                    </div>
                    <Link href="/lead-forms" class="mt-4 inline-flex text-sm text-primary hover:underline">Gerir formulários</Link>
                </section>

                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-medium">Últimas leads recebidas</h2>
                        <Link href="/lead-forms" class="text-sm text-primary hover:underline">Ver formulários</Link>
                    </div>
                    <div class="mt-4 space-y-3">
                        <Link
                            v-for="submission in latestLeadSubmissions"
                            :key="submission.id"
                            :href="submission.created_deal ? `/deals/${submission.created_deal.id}` : '/lead-forms'"
                            class="block rounded-md border p-3 text-sm transition hover:border-primary/60"
                        >
                            <p class="font-medium text-foreground">{{ submission.name ?? 'Lead sem nome' }}</p>
                            <p class="text-muted-foreground">
                                {{ submission.email ?? '-' }} · {{ submission.lead_form?.name ?? 'Formulário' }} ·
                                {{ submission.submitted_at ?? '-' }}
                            </p>
                        </Link>
                        <p v-if="latestLeadSubmissions.length === 0" class="text-sm text-muted-foreground">Ainda não há submissões públicas.</p>
                    </div>
                </section>
            </div>

            <div class="grid gap-4 lg:grid-cols-[1fr_1fr]">
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-sm text-muted-foreground">Sugestões AI pendentes</p>
                            <p class="mt-2 text-3xl font-semibold">{{ stats.aiSuggestionsPending }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Ações de alto impacto</p>
                            <p class="mt-2 text-3xl font-semibold">{{ stats.aiSuggestionsHighImpact }}</p>
                        </div>
                    </div>
                    <Link href="/ai-suggestions" class="mt-4 inline-flex text-sm text-primary hover:underline">Ver Agente Comercial</Link>
                </section>

                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-medium">Top sugestões comerciais</h2>
                        <Link href="/ai-suggestions" class="text-sm text-primary hover:underline">Ver backlog</Link>
                    </div>
                    <div class="mt-4 space-y-3">
                        <Link
                            v-for="suggestion in latestAISuggestions"
                            :key="suggestion.id"
                            :href="suggestion.url"
                            class="block rounded-md border p-3 text-sm transition hover:border-primary/60"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-foreground">{{ suggestion.title }}</p>
                                    <p class="line-clamp-2 text-muted-foreground">{{ suggestion.reason }}</p>
                                </div>
                                <span class="rounded-full border px-2 py-1 text-xs">{{ suggestion.score }}</span>
                            </div>
                        </Link>
                        <p v-if="latestAISuggestions.length === 0" class="text-sm text-muted-foreground">Sem sugestões AI pendentes.</p>
                    </div>
                </section>
            </div>

            <div class="grid gap-4 lg:grid-cols-[1fr_1fr]">
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-medium">Próximas atividades</h2>
                        <Link href="/calendar-events/create" class="text-sm text-primary hover:underline">Novo evento</Link>
                    </div>
                    <div class="mt-4 space-y-3">
                        <Link
                            v-for="activity in upcomingActivities"
                            :key="activity.id"
                            :href="activity.url"
                            class="block rounded-md border p-3 text-sm transition hover:border-primary/60"
                        >
                            <p class="font-medium text-foreground">{{ activity.title }}</p>
                            <p class="text-muted-foreground">
                                {{ activity.type }} · {{ activity.start_at ?? '-' }} · {{ activity.owner?.name ?? '-' }}
                            </p>
                        </Link>
                        <p v-if="upcomingActivities.length === 0" class="text-sm text-muted-foreground">Sem atividades próximas.</p>
                    </div>
                </section>

                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="flex items-center gap-2 font-medium"><Bell class="size-4" /> Últimas notificações</h2>
                        <Link href="/notifications" class="text-sm text-primary hover:underline">Ver todas</Link>
                    </div>
                    <div class="mt-4 space-y-3">
                        <Link
                            v-for="notification in latestNotifications"
                            :key="notification.id"
                            href="/notifications"
                            class="block rounded-md border p-3 text-sm transition hover:border-primary/60"
                            :class="notification.read_at ? 'bg-background' : 'bg-primary/5'"
                        >
                            <p class="font-medium text-foreground">{{ notification.title }}</p>
                            <p class="text-muted-foreground">{{ notification.body ?? 'Sem detalhe adicional.' }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ notification.created_at ?? '-' }}</p>
                        </Link>
                        <p v-if="latestNotifications.length === 0" class="text-sm text-muted-foreground">Sem notificações internas recentes.</p>
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
