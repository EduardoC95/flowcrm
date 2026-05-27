<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Pause, Pencil, Play, Trash2 } from 'lucide-vue-next';

interface RunRow {
    id: number;
    status: string;
    result: string | null;
    ran_at: string | null;
    deal: { id: number; title: string } | null;
    calendar_event: { id: number; title: string; start_at: string | null } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    automation: {
        id: number;
        name: string;
        description: string | null;
        inactivity_days: number | null;
        action_payload: {
            activity_type: string;
            activity_title_template: string;
            activity_description_template: string;
            due_in_days: number;
            priority: string;
        };
        notify_owner: boolean;
        active: boolean;
        paused_at: string | null;
        last_run_at: string | null;
        runs_count: number | null;
        creator: { id: number; name: string } | null;
    };
    runs: {
        data: RunRow[];
        links: PaginationLink[];
        total: number;
    };
    can: {
        update: boolean;
        delete: boolean;
        pause: boolean;
        resume: boolean;
    };
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Automações', href: '/automations' },
    { title: props.automation.name, href: `/automations/${props.automation.id}` },
];

const pause = () => router.patch(`/automations/${props.automation.id}/pause`);
const resume = () => router.patch(`/automations/${props.automation.id}/resume`);
const destroy = () => {
    if (confirm(`Apagar a automação "${props.automation.name}"?`)) {
        router.delete(`/automations/${props.automation.id}`);
    }
};
</script>

<template>
    <Head :title="automation.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <section class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <Button as-child variant="ghost" size="sm" class="-ml-3 mb-2">
                        <Link href="/automations">
                            <ArrowLeft class="size-4" />
                            Voltar
                        </Link>
                    </Button>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ automation.name }}</h1>
                    <p class="text-sm text-muted-foreground">{{ automation.description ?? 'Sem descrição registada.' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="can.update" as-child>
                        <Link :href="`/automations/${automation.id}/edit`">
                            <Pencil class="size-4" />
                            Editar
                        </Link>
                    </Button>
                    <Button v-if="can.pause && automation.active && !automation.paused_at" variant="outline" @click="pause">
                        <Pause class="size-4" />
                        Pausar
                    </Button>
                    <Button v-if="can.resume && (!automation.active || automation.paused_at)" variant="outline" @click="resume">
                        <Play class="size-4" />
                        Retomar
                    </Button>
                    <Button v-if="can.delete" variant="destructive" @click="destroy">
                        <Trash2 class="size-4" />
                        Apagar
                    </Button>
                </div>
            </section>

            <div class="grid gap-4 md:grid-cols-4">
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Estado</p>
                    <p class="mt-2 text-2xl font-semibold">{{ automation.active && !automation.paused_at ? 'Ativa' : 'Pausada' }}</p>
                </section>
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Condição</p>
                    <p class="mt-2 text-2xl font-semibold">{{ automation.inactivity_days }} dias</p>
                </section>
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Execuções</p>
                    <p class="mt-2 text-2xl font-semibold">{{ automation.runs_count ?? 0 }}</p>
                </section>
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Última execução</p>
                    <p class="mt-2 text-sm font-medium">{{ automation.last_run_at ?? '-' }}</p>
                </section>
            </div>

            <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                <h2 class="font-medium">Ação configurada</h2>
                <dl class="mt-4 grid gap-3 text-sm md:grid-cols-2">
                    <div>
                        <dt class="text-muted-foreground">Tipo</dt>
                        <dd class="font-medium">{{ automation.action_payload.activity_type }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Prazo</dt>
                        <dd class="font-medium">Daqui a {{ automation.action_payload.due_in_days }} dia(s)</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Prioridade</dt>
                        <dd class="font-medium">{{ automation.action_payload.priority }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Notificação</dt>
                        <dd class="font-medium">{{ automation.notify_owner ? 'Notificar responsável' : 'Sem notificação' }}</dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-muted-foreground">Título</dt>
                        <dd class="font-medium">{{ automation.action_payload.activity_title_template }}</dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-muted-foreground">Descrição</dt>
                        <dd class="whitespace-pre-line font-medium">{{ automation.action_payload.activity_description_template }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                <h2 class="font-medium">Histórico de execuções</h2>
                <div class="mt-4 overflow-x-auto">
                    <table v-if="runs.data.length" class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Data</th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                                <th class="px-4 py-3 font-medium">Negócio</th>
                                <th class="px-4 py-3 font-medium">Atividade</th>
                                <th class="px-4 py-3 font-medium">Resultado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="run in runs.data" :key="run.id" class="border-b last:border-0">
                                <td class="px-4 py-3 text-muted-foreground">{{ run.ran_at ?? '-' }}</td>
                                <td class="px-4 py-3">{{ run.status }}</td>
                                <td class="px-4 py-3">
                                    <Link v-if="run.deal" :href="`/deals/${run.deal.id}`" class="text-primary hover:underline">{{
                                        run.deal.title
                                    }}</Link>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-4 py-3">
                                    <Link
                                        v-if="run.calendar_event"
                                        :href="`/calendar-events/${run.calendar_event.id}`"
                                        class="text-primary hover:underline"
                                    >
                                        {{ run.calendar_event.title }}
                                    </Link>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ run.result ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Esta automação ainda não foi executada.
                    </p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
