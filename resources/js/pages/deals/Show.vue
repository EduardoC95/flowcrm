<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Trash2 } from 'lucide-vue-next';

interface Option {
    id: number;
    name: string;
    email?: string | null;
    phone?: string | null;
    position?: string | null;
}

interface Stage {
    id: number;
    name: string;
    slug: string;
    color: string | null;
    is_won: boolean;
    is_lost: boolean;
}

interface ActivityLog {
    id: number;
    action: string;
    description: string | null;
    created_at: string | null;
    user: Option | null;
}

interface CalendarEvent {
    id: number;
    title: string;
    type: string;
    status: string;
    starts_at: string | null;
    ends_at: string | null;
    location: string | null;
}

const props = defineProps<{
    deal: {
        id: number;
        title: string;
        value: number;
        probability: number;
        expected_close_date: string | null;
        priority: string | null;
        description: string | null;
        last_activity_at: string | null;
        created_at: string | null;
        updated_at: string | null;
        entity: Option | null;
        person: Option | null;
        owner: Option | null;
        stage: Stage | null;
        calendar_events: CalendarEvent[];
        activity_logs: ActivityLog[];
    };
    can: {
        update: boolean;
        delete: boolean;
    };
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Negócios', href: '/deals' },
    { title: props.deal.title, href: `/deals/${props.deal.id}` },
];

const priorityLabels: Record<string, string> = {
    low: 'Baixa',
    medium: 'Média',
    high: 'Alta',
    urgent: 'Urgente',
};

const money = (value: number) =>
    new Intl.NumberFormat('pt-PT', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);

const destroy = () => {
    if (confirm(`Apagar o negócio "${props.deal.title}"?`)) {
        router.delete(`/deals/${props.deal.id}`);
    }
};
</script>

<template>
    <Head :title="deal.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <section class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <Button as-child variant="ghost" size="sm" class="-ml-3 mb-2">
                        <Link href="/deals">
                            <ArrowLeft class="size-4" />
                            Voltar
                        </Link>
                    </Button>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ deal.title }}</h1>
                    <p class="text-sm text-muted-foreground">Histórico, etapa, contactos e valores do negócio.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="can.update" as-child>
                        <Link :href="`/deals/${deal.id}/edit`">
                            <Pencil class="size-4" />
                            Editar
                        </Link>
                    </Button>
                    <Button v-if="can.delete" variant="destructive" @click="destroy">
                        <Trash2 class="size-4" />
                        Apagar
                    </Button>
                </div>
            </section>

            <div class="grid gap-4 xl:grid-cols-[2fr_1fr]">
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex rounded-full border px-2 py-1 text-xs font-medium">
                            {{ deal.stage?.name ?? 'Sem etapa' }}
                        </span>
                        <span class="inline-flex rounded-full border px-2 py-1 text-xs font-medium">
                            {{ deal.priority ? priorityLabels[deal.priority] : 'Sem prioridade' }}
                        </span>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Valor</p>
                            <p class="mt-1 text-2xl font-semibold">{{ money(deal.value) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Probabilidade</p>
                            <p class="mt-1 text-2xl font-semibold">{{ deal.probability }}%</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Data prevista</p>
                            <p class="mt-1 text-2xl font-semibold">{{ deal.expected_close_date ?? '-' }}</p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <p class="text-sm font-medium">Descrição</p>
                        <p class="mt-2 whitespace-pre-line text-sm text-muted-foreground">{{ deal.description ?? 'Sem descrição registada.' }}</p>
                    </div>
                </section>

                <aside class="space-y-4">
                    <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                        <h2 class="font-medium">Relações</h2>
                        <div class="mt-4 space-y-3 text-sm">
                            <div>
                                <p class="text-muted-foreground">Entidade</p>
                                <Link v-if="deal.entity" :href="`/entities/${deal.entity.id}`" class="text-primary hover:underline">{{
                                    deal.entity.name
                                }}</Link>
                                <span v-else>Sem entidade</span>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Pessoa</p>
                                <Link v-if="deal.person" :href="`/people/${deal.person.id}`" class="text-primary hover:underline">{{
                                    deal.person.name
                                }}</Link>
                                <span v-else>Sem pessoa</span>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Responsável</p>
                                <span>{{ deal.owner?.name ?? '-' }}</span>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                        <h2 class="font-medium">Proposta</h2>
                        <p class="mt-2 text-sm text-muted-foreground">Área reservada para documentos, versões e aprovação comercial.</p>
                    </section>
                </aside>
            </div>

            <div class="grid gap-4 xl:grid-cols-3">
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <h2 class="font-medium">Eventos/Atividades</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="event in deal.calendar_events" :key="event.id" class="rounded-md border p-3 text-sm">
                            <Link :href="`/calendar-events/${event.id}`" class="font-medium text-primary hover:underline">{{ event.title }}</Link>
                            <p class="text-muted-foreground">{{ event.starts_at ?? '-' }} · {{ event.location ?? 'Sem localização' }}</p>
                        </div>
                        <p v-if="deal.calendar_events.length === 0" class="text-sm text-muted-foreground">Ainda não há eventos associados.</p>
                    </div>
                </section>

                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <h2 class="font-medium">Histórico/Logs</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="log in deal.activity_logs" :key="log.id" class="rounded-md border p-3 text-sm">
                            <p class="font-medium">{{ log.action }}</p>
                            <p class="text-muted-foreground">{{ log.description ?? '-' }}</p>
                            <p class="text-xs text-muted-foreground">{{ log.created_at }} · {{ log.user?.name ?? 'Sistema' }}</p>
                        </div>
                        <p v-if="deal.activity_logs.length === 0" class="text-sm text-muted-foreground">Ainda não há histórico registado.</p>
                    </div>
                </section>

                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <h2 class="font-medium">Cronologia e Atividades Rápidas</h2>
                    <div class="mt-4 space-y-3 text-sm text-muted-foreground">
                        <p>Última atividade: {{ deal.last_activity_at ?? '-' }}</p>
                        <Button as-child variant="outline" class="w-full justify-start">
                            <Link href="/calendar-events/create">Preparar evento</Link>
                        </Button>
                        <Button as-child variant="outline" class="w-full justify-start">
                            <Link href="/deals-board">Ver no pipeline</Link>
                        </Button>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
