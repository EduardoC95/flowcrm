<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, Pause, Pencil, Play, Plus, Trash2 } from 'lucide-vue-next';

interface AutomationRow {
    id: number;
    name: string;
    description: string | null;
    trigger_type: string;
    inactivity_days: number | null;
    action_type: string;
    action_payload: {
        activity_type: string;
        due_in_days: number;
        priority: string;
    };
    notify_owner: boolean;
    active: boolean;
    paused_at: string | null;
    runs_count: number | null;
    last_run_at: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{
    automations: {
        data: AutomationRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    can: {
        create: boolean;
    };
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Automações', href: '/automations' }];

const pause = (automation: AutomationRow) => router.patch(`/automations/${automation.id}/pause`);
const resume = (automation: AutomationRow) => router.patch(`/automations/${automation.id}/resume`);
const destroy = (automation: AutomationRow) => {
    if (confirm(`Apagar a automação "${automation.name}"?`)) {
        router.delete(`/automations/${automation.id}`);
    }
};
</script>

<template>
    <Head title="Automações" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Automações</h1>
                    <p class="text-sm text-muted-foreground">Regras internas para negócios sem atividade.</p>
                </div>
                <Button v-if="can.create" as-child>
                    <Link href="/automations/create">
                        <Plus class="size-4" />
                        Nova automação
                    </Link>
                </Button>
            </section>

            <div class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div v-if="automations.data.length === 0" class="flex min-h-64 flex-col items-center justify-center gap-2 p-8 text-center">
                    <h2 class="text-lg font-medium">Ainda não há automações</h2>
                    <p class="max-w-md text-sm text-muted-foreground">Crie a primeira regra para gerar atividades quando negócios ficam parados.</p>
                    <Button v-if="can.create" as-child class="mt-2">
                        <Link href="/automations/create">Criar automação</Link>
                    </Button>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Nome</th>
                                <th class="px-4 py-3 font-medium">Condição</th>
                                <th class="px-4 py-3 font-medium">Ação</th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                                <th class="px-4 py-3 font-medium">Última execução</th>
                                <th class="px-4 py-3 font-medium">Execuções</th>
                                <th class="px-4 py-3 text-right font-medium">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="automation in automations.data" :key="automation.id" class="border-b last:border-0">
                                <td class="px-4 py-3">
                                    <Link :href="`/automations/${automation.id}`" class="font-medium text-primary hover:underline">
                                        {{ automation.name }}
                                    </Link>
                                    <p class="text-xs text-muted-foreground">{{ automation.description ?? 'Sem descrição' }}</p>
                                </td>
                                <td class="px-4 py-3">Sem atividade há {{ automation.inactivity_days }} dias</td>
                                <td class="px-4 py-3">
                                    Criar {{ automation.action_payload.activity_type }} em {{ automation.action_payload.due_in_days }} dia(s)
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full border px-2 py-1 text-xs font-medium"
                                        :class="
                                            automation.active && !automation.paused_at
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                : 'border-amber-200 bg-amber-50 text-amber-700'
                                        "
                                    >
                                        {{ automation.active && !automation.paused_at ? 'Ativa' : 'Pausada' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ automation.last_run_at ?? '-' }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ automation.runs_count ?? 0 }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <Button as-child variant="ghost" size="icon" title="Ver">
                                            <Link :href="`/automations/${automation.id}`"><Eye class="size-4" /></Link>
                                        </Button>
                                        <Button as-child variant="ghost" size="icon" title="Editar">
                                            <Link :href="`/automations/${automation.id}/edit`"><Pencil class="size-4" /></Link>
                                        </Button>
                                        <Button
                                            v-if="automation.active && !automation.paused_at"
                                            variant="ghost"
                                            size="icon"
                                            title="Pausar"
                                            @click="pause(automation)"
                                        >
                                            <Pause class="size-4" />
                                        </Button>
                                        <Button v-else variant="ghost" size="icon" title="Retomar" @click="resume(automation)">
                                            <Play class="size-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon" title="Apagar" @click="destroy(automation)">
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                v-if="automations.total > 0"
                class="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
            >
                <span>A mostrar {{ automations.from }}-{{ automations.to }} de {{ automations.total }}</span>
                <div class="flex flex-wrap gap-1">
                    <Button
                        v-for="link in automations.links"
                        :key="link.label"
                        as-child
                        :disabled="!link.url"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                    >
                        <Link v-if="link.url" :href="link.url"><span v-html="link.label" /></Link>
                        <span v-else v-html="link.label" />
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
