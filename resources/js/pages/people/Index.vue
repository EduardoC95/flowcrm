<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { reactive } from 'vue';

interface PersonRow {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    position: string | null;
    status: string;
    entity: { id: number; name: string } | null;
    deals_count: number;
    calendar_events_count: number;
}

interface Option {
    id: number;
    name: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    people: {
        data: PersonRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        search: string | null;
        entity_id: number | null;
        status: string | null;
        sort: string;
        direction: string;
    };
    statuses: string[];
    entities: Option[];
    can: {
        create: boolean;
    };
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pessoas', href: '/people' }];

const form = reactive({
    search: props.filters.search ?? '',
    entity_id: props.filters.entity_id ? String(props.filters.entity_id) : '',
    status: props.filters.status ?? '',
    sort: props.filters.sort ?? 'name',
    direction: props.filters.direction ?? 'asc',
});

const statusLabels: Record<string, string> = {
    active: 'Ativa',
    inactive: 'Inativa',
    lead: 'Lead',
    client: 'Cliente',
    prospect: 'Prospect',
};

const statusClass = (status: string) =>
    ({
        active: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300',
        inactive: 'border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-300',
        lead: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-300',
        client: 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900 dark:bg-violet-950 dark:text-violet-300',
        prospect: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
    })[status] || 'border-border bg-muted text-muted-foreground';

const submit = () => {
    router.get('/people', form, {
        preserveState: true,
        replace: true,
    });
};

const reset = () => {
    form.search = '';
    form.entity_id = '';
    form.status = '';
    form.sort = 'name';
    form.direction = 'asc';
    submit();
};

const destroy = (person: PersonRow) => {
    if (confirm(`Apagar a pessoa "${person.name}"?`)) {
        router.delete(`/people/${person.id}`);
    }
};
</script>

<template>
    <Head title="Pessoas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Pessoas</h1>
                    <p class="text-sm text-muted-foreground">Contactos individuais, decisores e relações comerciais.</p>
                </div>
                <Button v-if="can.create" as-child>
                    <Link href="/people/create">
                        <Plus class="size-4" />
                        Nova pessoa
                    </Link>
                </Button>
            </section>

            <form
                class="grid gap-3 rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border md:grid-cols-[1fr_180px_160px_150px_130px_auto]"
                @submit.prevent="submit"
            >
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="form.search" class="pl-9" placeholder="Pesquisar por nome, email, telefone ou cargo" />
                </div>
                <select v-model="form.entity_id" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="">Todas as entidades</option>
                    <option v-for="entity in entities" :key="entity.id" :value="entity.id">{{ entity.name }}</option>
                </select>
                <select v-model="form.status" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="">Todos os estados</option>
                    <option v-for="status in statuses" :key="status" :value="status">{{ statusLabels[status] ?? status }}</option>
                </select>
                <select v-model="form.sort" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="name">Nome</option>
                    <option value="created_at">Data de criação</option>
                </select>
                <select v-model="form.direction" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="asc">Ascendente</option>
                    <option value="desc">Descendente</option>
                </select>
                <div class="flex gap-2">
                    <Button type="submit">Filtrar</Button>
                    <Button type="button" variant="outline" @click="reset">Limpar</Button>
                </div>
            </form>

            <div class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div v-if="people.data.length === 0" class="flex min-h-64 flex-col items-center justify-center gap-2 p-8 text-center">
                    <h2 class="text-lg font-medium">Ainda não há pessoas</h2>
                    <p class="max-w-md text-sm text-muted-foreground">Cria o primeiro contacto para começar a mapear relações comerciais.</p>
                    <Button v-if="can.create" as-child class="mt-2">
                        <Link href="/people/create">Criar pessoa</Link>
                    </Button>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Nome</th>
                                <th class="px-4 py-3 font-medium">Entidade</th>
                                <th class="px-4 py-3 font-medium">Contacto</th>
                                <th class="px-4 py-3 font-medium">Cargo/Função</th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                                <th class="px-4 py-3 font-medium">Histórico</th>
                                <th class="px-4 py-3 text-right font-medium">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="person in people.data" :key="person.id" class="border-b last:border-0">
                                <td class="px-4 py-3 font-medium">{{ person.name }}</td>
                                <td class="px-4 py-3">
                                    <Link v-if="person.entity" :href="`/entities/${person.entity.id}`" class="text-primary hover:underline">
                                        {{ person.entity.name }}
                                    </Link>
                                    <span v-else class="text-muted-foreground">Sem entidade</span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    <div>{{ person.email ?? '-' }}</div>
                                    <div>{{ person.phone ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ person.position ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border px-2 py-1 text-xs font-medium" :class="statusClass(person.status)">
                                        {{ statusLabels[person.status] ?? person.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ person.deals_count }} negócios · {{ person.calendar_events_count }} eventos
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <Button as-child variant="ghost" size="icon" title="Ver">
                                            <Link :href="`/people/${person.id}`"><Eye class="size-4" /></Link>
                                        </Button>
                                        <Button as-child variant="ghost" size="icon" title="Editar">
                                            <Link :href="`/people/${person.id}/edit`"><Pencil class="size-4" /></Link>
                                        </Button>
                                        <Button variant="ghost" size="icon" title="Apagar" @click="destroy(person)">
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="people.total > 0" class="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                <span>A mostrar {{ people.from }}-{{ people.to }} de {{ people.total }}</span>
                <div class="flex flex-wrap gap-1">
                    <Button
                        v-for="link in people.links"
                        :key="link.label"
                        as-child
                        :disabled="!link.url"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                    >
                        <Link v-if="link.url" :href="link.url" v-html="link.label" />
                        <span v-else v-html="link.label" />
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
