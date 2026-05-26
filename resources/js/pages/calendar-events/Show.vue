<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Pencil, Trash2, XCircle } from 'lucide-vue-next';

interface Option {
    id: number;
    name: string;
}

interface ActivityLog {
    id: number;
    action: string;
    description: string | null;
    created_at: string | null;
    user: Option | null;
}

const props = defineProps<{
    event: {
        id: number;
        title: string;
        description: string | null;
        type: string;
        status: string;
        priority: string | null;
        start_at: string | null;
        end_at: string | null;
        location: string | null;
        reminder_at: string | null;
        reminder_sent_at: string | null;
        owner: Option | null;
        associated: { id: number; type: string; name: string; url: string | null } | null;
        activity_logs: ActivityLog[];
    };
    can: {
        update: boolean;
        delete: boolean;
        complete: boolean;
        cancel: boolean;
    };
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Calendário', href: '/calendar' },
    { title: props.event.title, href: `/calendar-events/${props.event.id}` },
];

const typeLabels: Record<string, string> = {
    task: 'Tarefa',
    call: 'Chamada',
    meeting: 'Reunião',
    note: 'Nota',
    reminder: 'Lembrete',
};

const statusLabels: Record<string, string> = {
    pending: 'Pendente',
    completed: 'Concluído',
    cancelled: 'Cancelado',
};

const priorityLabels: Record<string, string> = {
    low: 'Baixa',
    medium: 'Média',
    high: 'Alta',
    urgent: 'Urgente',
};

const destroy = () => {
    if (confirm(`Apagar o evento "${props.event.title}"?`)) {
        router.delete(`/calendar-events/${props.event.id}`);
    }
};

const complete = () => router.patch(`/calendar-events/${props.event.id}/complete`);
const cancel = () => router.patch(`/calendar-events/${props.event.id}/cancel`);
</script>

<template>
    <Head :title="event.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <section class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <Button as-child variant="ghost" size="sm" class="-ml-3 mb-2">
                        <Link href="/calendar">
                            <ArrowLeft class="size-4" />
                            Voltar
                        </Link>
                    </Button>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ event.title }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ typeLabels[event.type] ?? event.type }} · {{ statusLabels[event.status] ?? event.status }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="can.complete" variant="outline" @click="complete">
                        <CheckCircle2 class="size-4" />
                        Concluir
                    </Button>
                    <Button v-if="can.cancel" variant="outline" @click="cancel">
                        <XCircle class="size-4" />
                        Cancelar
                    </Button>
                    <Button v-if="can.update" as-child>
                        <Link :href="`/calendar-events/${event.id}/edit`">
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
                    <h2 class="font-medium">Dados principais</h2>
                    <dl class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <dt class="text-sm text-muted-foreground">Início</dt>
                            <dd class="mt-1">{{ event.start_at ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-foreground">Fim</dt>
                            <dd class="mt-1">{{ event.end_at ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-foreground">Responsável</dt>
                            <dd class="mt-1">{{ event.owner?.name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-foreground">Prioridade</dt>
                            <dd class="mt-1">{{ event.priority ? priorityLabels[event.priority] : '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-foreground">Localização</dt>
                            <dd class="mt-1">{{ event.location ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-foreground">Lembrete</dt>
                            <dd class="mt-1">{{ event.reminder_at ?? '-' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="text-sm text-muted-foreground">Descrição</dt>
                            <dd class="mt-1 whitespace-pre-line">{{ event.description ?? '-' }}</dd>
                        </div>
                    </dl>
                </section>

                <aside class="space-y-4">
                    <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                        <h2 class="font-medium">Registo associado</h2>
                        <Link
                            v-if="event.associated?.url"
                            :href="event.associated.url"
                            class="mt-3 block rounded-md border p-3 text-sm text-primary hover:underline"
                        >
                            {{ event.associated.name }}
                        </Link>
                        <p v-else class="mt-3 text-sm text-muted-foreground">Sem associação.</p>
                    </section>

                    <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                        <h2 class="font-medium">Lembrete</h2>
                        <p class="mt-2 text-sm text-muted-foreground">Enviado em: {{ event.reminder_sent_at ?? 'Ainda não enviado' }}</p>
                    </section>
                </aside>
            </div>

            <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                <h2 class="font-medium">Histórico/Logs</h2>
                <div class="mt-4 space-y-3">
                    <div v-for="log in event.activity_logs" :key="log.id" class="rounded-md border p-3 text-sm">
                        <p class="font-medium">{{ log.action }}</p>
                        <p class="text-muted-foreground">{{ log.description ?? '-' }}</p>
                        <p class="text-xs text-muted-foreground">{{ log.created_at }} · {{ log.user?.name ?? 'Sistema' }}</p>
                    </div>
                    <p v-if="event.activity_logs.length === 0" class="text-sm text-muted-foreground">Ainda não há histórico registado.</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
