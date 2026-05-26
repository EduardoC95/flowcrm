<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CalendarPlus, GitMerge, Pencil, Trash2 } from 'lucide-vue-next';

interface RelatedEntity {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    status: string;
}

interface RelatedDeal {
    id: number;
    title: string;
    stage: string | { id: number; name: string; slug: string; color: string | null } | null;
    value: string | number;
    expected_close_date: string | null;
}

interface CalendarEvent {
    id: number;
    title: string;
    start_at: string | null;
    starts_at: string;
    ends_at: string | null;
    location: string | null;
}

interface ActivityLog {
    id: number;
    action: string;
    description: string | null;
    created_at: string | null;
    user: { id: number; name: string } | null;
}

interface MergeCandidate {
    id: number;
    name: string;
    email: string | null;
}

const props = defineProps<{
    person: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
        position: string | null;
        status: string;
        notes: string | null;
        created_at: string | null;
        updated_at: string | null;
        entity: RelatedEntity | null;
        deals: RelatedDeal[];
        calendar_events: CalendarEvent[];
        activity_logs: ActivityLog[];
    };
    mergeCandidates: MergeCandidate[];
    can: {
        update: boolean;
        delete: boolean;
        merge: boolean;
    };
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pessoas', href: '/people' },
    { title: props.person.name, href: `/people/${props.person.id}` },
];

const mergeForm = useForm({
    target_person_id: '',
});

const statusLabels: Record<string, string> = {
    active: 'Ativa',
    inactive: 'Inativa',
    lead: 'Lead',
    client: 'Cliente',
    prospect: 'Prospect',
};

const destroy = () => {
    if (confirm(`Apagar a pessoa "${props.person.name}"?`)) {
        router.delete(`/people/${props.person.id}`);
    }
};

const dealStageName = (deal: RelatedDeal) => (typeof deal.stage === 'string' ? deal.stage : (deal.stage?.name ?? '-'));

const merge = () => {
    if (!mergeForm.target_person_id) {
        return;
    }

    if (confirm('Fundir esta pessoa na pessoa selecionada? Esta pessoa será apagada após mover o histórico.')) {
        mergeForm.post(`/people/${props.person.id}/merge`);
    }
};
</script>

<template>
    <Head :title="person.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <section class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <Button as-child variant="ghost" class="-ml-3 mb-2">
                        <Link href="/people">
                            <ArrowLeft class="size-4" />
                            Voltar
                        </Link>
                    </Button>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ person.name }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ person.position ?? 'Sem cargo definido' }} · {{ statusLabels[person.status] ?? person.status }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as-child variant="outline">
                        <Link href="/deals/create">
                            <GitMerge class="size-4" />
                            Criar negócio
                        </Link>
                    </Button>
                    <Button as-child variant="outline">
                        <Link href="/calendar-events/create">
                            <CalendarPlus class="size-4" />
                            Criar evento
                        </Link>
                    </Button>
                    <Button v-if="can.update" as-child variant="outline">
                        <Link :href="`/people/${person.id}/edit`">
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

            <section class="grid gap-4 lg:grid-cols-[1fr_320px]">
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <h2 class="font-medium">Dados principais</h2>
                    <dl class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <dt class="text-sm text-muted-foreground">Email</dt>
                            <dd class="mt-1">{{ person.email ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-foreground">Telefone</dt>
                            <dd class="mt-1">{{ person.phone ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-foreground">Cargo/Função</dt>
                            <dd class="mt-1">{{ person.position ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-foreground">Criada em</dt>
                            <dd class="mt-1">{{ person.created_at ?? '-' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="text-sm text-muted-foreground">Notas</dt>
                            <dd class="mt-1 whitespace-pre-line">{{ person.notes ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <h2 class="font-medium">Entidade associada</h2>
                    <div v-if="person.entity" class="mt-4 rounded-md border p-3">
                        <Link :href="`/entities/${person.entity.id}`" class="font-medium text-primary hover:underline">{{ person.entity.name }}</Link>
                        <div class="mt-1 text-sm text-muted-foreground">{{ person.entity.email ?? '-' }} · {{ person.entity.phone ?? '-' }}</div>
                    </div>
                    <div v-else class="mt-4 rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        Esta pessoa ainda não está associada a uma entidade.
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-3">
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <h2 class="font-medium">Negócios associados</h2>
                    <div class="mt-4 space-y-3">
                        <div v-if="person.deals.length === 0" class="text-sm text-muted-foreground">Ainda não há negócios associados.</div>
                        <div v-for="deal in person.deals" :key="deal.id" class="rounded-md border p-3">
                            <Link :href="`/deals/${deal.id}`" class="font-medium text-primary hover:underline">{{ deal.title }}</Link>
                            <div class="text-sm text-muted-foreground">{{ dealStageName(deal) }} · {{ deal.value }} EUR</div>
                            <div class="mt-1 text-sm text-muted-foreground">Fecho previsto: {{ deal.expected_close_date ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <h2 class="font-medium">Eventos e atividades</h2>
                    <div class="mt-4 space-y-3">
                        <div v-if="person.calendar_events.length === 0" class="text-sm text-muted-foreground">Ainda não há eventos associados.</div>
                        <div v-for="event in person.calendar_events" :key="event.id" class="rounded-md border p-3">
                            <Link :href="`/calendar-events/${event.id}`" class="font-medium text-primary hover:underline">{{ event.title }}</Link>
                            <div class="text-sm text-muted-foreground">{{ event.start_at ?? event.starts_at }} · {{ event.location ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <h2 class="font-medium">Histórico</h2>
                    <div class="mt-4 space-y-3">
                        <div v-if="person.activity_logs.length === 0" class="text-sm text-muted-foreground">Sem logs registados.</div>
                        <div v-for="log in person.activity_logs" :key="log.id" class="border-b pb-3 text-sm last:border-0">
                            <div class="font-medium">{{ log.action }}</div>
                            <div class="text-muted-foreground">{{ log.description }}</div>
                            <div class="mt-1 text-xs text-muted-foreground">{{ log.created_at }} · {{ log.user?.name ?? 'Sistema' }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-if="can.merge && mergeCandidates.length > 0"
                class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border"
            >
                <h2 class="font-medium">Fundir duplicado</h2>
                <p class="mt-1 text-sm text-muted-foreground">Move negócios e eventos desta pessoa para outra e apaga esta como duplicada.</p>
                <form class="mt-4 flex max-w-xl flex-col gap-3 sm:flex-row" @submit.prevent="merge">
                    <select v-model="mergeForm.target_person_id" class="h-9 flex-1 rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">Selecionar pessoa principal</option>
                        <option v-for="candidate in mergeCandidates" :key="candidate.id" :value="candidate.id">
                            {{ candidate.name }}{{ candidate.email ? ` · ${candidate.email}` : '' }}
                        </option>
                    </select>
                    <Button type="submit" :disabled="mergeForm.processing || !mergeForm.target_person_id">Fundir</Button>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
