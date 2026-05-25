<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Trash2 } from 'lucide-vue-next';

interface RelatedPerson {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    position: string | null;
    job_title: string | null;
}

interface RelatedDeal {
    id: number;
    title: string;
    stage: string | { id: number; name: string; slug: string; color: string | null } | null;
    value: string | number;
    expected_close_date: string | null;
}

interface ActivityLog {
    id: number;
    action: string;
    description: string | null;
    created_at: string | null;
    user: { id: number; name: string } | null;
}

interface CalendarEvent {
    id: number;
    title: string;
    starts_at: string;
    ends_at: string | null;
    location: string | null;
}

const props = defineProps<{
    entity: {
        id: number;
        name: string;
        vat: string | null;
        email: string | null;
        phone: string | null;
        address: string | null;
        status: string;
        notes: string | null;
        created_at: string | null;
        updated_at: string | null;
        people: RelatedPerson[];
        deals: RelatedDeal[];
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
    { title: 'Entidades', href: '/entities' },
    { title: props.entity.name, href: `/entities/${props.entity.id}` },
];

const statusLabels: Record<string, string> = {
    active: 'Ativa',
    inactive: 'Inativa',
    lead: 'Lead',
    client: 'Cliente',
    prospect: 'Prospect',
};

const destroy = () => {
    if (confirm(`Apagar a entidade "${props.entity.name}"?`)) {
        router.delete(`/entities/${props.entity.id}`);
    }
};

const dealStageName = (deal: RelatedDeal) => (typeof deal.stage === 'string' ? deal.stage : (deal.stage?.name ?? '-'));
</script>

<template>
    <Head :title="entity.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <section class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <Button as-child variant="ghost" class="-ml-3 mb-2">
                        <Link href="/entities">
                            <ArrowLeft class="size-4" />
                            Voltar
                        </Link>
                    </Button>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ entity.name }}</h1>
                    <p class="text-sm text-muted-foreground">{{ statusLabels[entity.status] ?? entity.status }}</p>
                </div>
                <div class="flex gap-2">
                    <Button v-if="can.update" as-child variant="outline">
                        <Link :href="`/entities/${entity.id}/edit`">
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
                            <dt class="text-sm text-muted-foreground">VAT/NIF</dt>
                            <dd class="mt-1">{{ entity.vat ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-foreground">Email</dt>
                            <dd class="mt-1">{{ entity.email ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-foreground">Telefone</dt>
                            <dd class="mt-1">{{ entity.phone ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-foreground">Criada em</dt>
                            <dd class="mt-1">{{ entity.created_at ?? '-' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="text-sm text-muted-foreground">Morada</dt>
                            <dd class="mt-1 whitespace-pre-line">{{ entity.address ?? '-' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="text-sm text-muted-foreground">Notas</dt>
                            <dd class="mt-1 whitespace-pre-line">{{ entity.notes ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <h2 class="font-medium">Histórico</h2>
                    <div class="mt-4 space-y-3">
                        <div v-if="entity.activity_logs.length === 0" class="text-sm text-muted-foreground">Sem logs registados.</div>
                        <div v-for="log in entity.activity_logs" :key="log.id" class="border-b pb-3 text-sm last:border-0">
                            <div class="font-medium">{{ log.action }}</div>
                            <div class="text-muted-foreground">{{ log.description }}</div>
                            <div class="mt-1 text-xs text-muted-foreground">{{ log.created_at }} · {{ log.user?.name ?? 'Sistema' }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-3">
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <h2 class="font-medium">Pessoas associadas</h2>
                    <div class="mt-4 space-y-3">
                        <div v-if="entity.people.length === 0" class="text-sm text-muted-foreground">Ainda não há pessoas associadas.</div>
                        <div v-for="person in entity.people" :key="person.id" class="rounded-md border p-3">
                            <Link :href="`/people/${person.id}`" class="font-medium text-primary hover:underline">{{ person.name }}</Link>
                            <div class="text-sm text-muted-foreground">{{ person.position ?? person.job_title ?? '-' }}</div>
                            <div class="mt-1 text-sm text-muted-foreground">{{ person.email ?? '-' }} · {{ person.phone ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <h2 class="font-medium">Negócios associados</h2>
                    <div class="mt-4 space-y-3">
                        <div v-if="entity.deals.length === 0" class="text-sm text-muted-foreground">Ainda não há negócios associados.</div>
                        <div v-for="deal in entity.deals" :key="deal.id" class="rounded-md border p-3">
                            <Link :href="`/deals/${deal.id}`" class="font-medium text-primary hover:underline">{{ deal.title }}</Link>
                            <div class="text-sm text-muted-foreground">{{ dealStageName(deal) }} · {{ deal.value }} EUR</div>
                            <div class="mt-1 text-sm text-muted-foreground">Fecho previsto: {{ deal.expected_close_date ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <h2 class="font-medium">Eventos associados</h2>
                    <div class="mt-4 space-y-3">
                        <div v-if="entity.calendar_events.length === 0" class="text-sm text-muted-foreground">Ainda não há eventos associados.</div>
                        <div v-for="event in entity.calendar_events" :key="event.id" class="rounded-md border p-3">
                            <div class="font-medium">{{ event.title }}</div>
                            <div class="text-sm text-muted-foreground">{{ event.starts_at }} · {{ event.location ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
