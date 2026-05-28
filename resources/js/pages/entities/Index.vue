<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { reactive } from 'vue';

interface EntityRow {
    id: number;
    name: string;
    vat: string | null;
    email: string | null;
    phone: string | null;
    status: string;
    people_count: number;
    deals_count: number;
    created_at: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    entities: {
        data: EntityRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        search: string | null;
        status: string | null;
        sort: string;
        direction: string;
    };
    statuses: string[];
    can: {
        create: boolean;
    };
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Entidades', href: '/entities' }];

const form = reactive({
    search: props.filters.search ?? '',
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
    router.get('/entities', form, {
        preserveState: true,
        replace: true,
    });
};

const reset = () => {
    form.search = '';
    form.status = '';
    form.sort = 'name';
    form.direction = 'asc';
    submit();
};

const destroy = (entity: EntityRow) => {
    if (confirm(`Apagar a entidade "${entity.name}"?`)) {
        router.delete(`/entities/${entity.id}`);
    }
};
</script>

<template>
    <Head title="Entidades" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Entidades</h1>
                    <p class="text-sm text-muted-foreground">Empresas, organizações e clientes do tenant ativo.</p>
                </div>
                <Button v-if="can.create" as-child>
                    <Link href="/entities/create">
                        <Plus class="size-4" />
                        Nova entidade
                    </Link>
                </Button>
            </section>

            <form
                class="grid gap-3 rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border md:grid-cols-[1fr_180px_160px_140px_auto]"
                @submit.prevent="submit"
            >
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="form.search" class="pl-9" placeholder="Pesquisar por nome, VAT, email ou telefone" />
                </div>
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
                <div v-if="entities.data.length === 0" class="flex min-h-64 flex-col items-center justify-center gap-2 p-8 text-center">
                    <h2 class="text-lg font-medium">Ainda não há entidades</h2>
                    <p class="max-w-md text-sm text-muted-foreground">
                        Cria a primeira entidade para começar a organizar contactos, negócios e histórico comercial.
                    </p>
                    <Button v-if="can.create" as-child class="mt-2">
                        <Link href="/entities/create">Criar entidade</Link>
                    </Button>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Nome</th>
                                <th class="px-4 py-3 font-medium">VAT/NIF</th>
                                <th class="px-4 py-3 font-medium">Contacto</th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                                <th class="px-4 py-3 font-medium">Pessoas</th>
                                <th class="px-4 py-3 font-medium">Negócios</th>
                                <th class="px-4 py-3 text-right font-medium">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="entity in entities.data" :key="entity.id" class="border-b last:border-0">
                                <td class="px-4 py-3 font-medium">{{ entity.name }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ entity.vat ?? '-' }}</td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    <div>{{ entity.email ?? '-' }}</div>
                                    <div>{{ entity.phone ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border px-2 py-1 text-xs font-medium" :class="statusClass(entity.status)">
                                        {{ statusLabels[entity.status] ?? entity.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ entity.people_count }}</td>
                                <td class="px-4 py-3">{{ entity.deals_count }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <Button as-child variant="ghost" size="icon" title="Ver">
                                            <Link :href="`/entities/${entity.id}`"><Eye class="size-4" /></Link>
                                        </Button>
                                        <Button as-child variant="ghost" size="icon" title="Editar">
                                            <Link :href="`/entities/${entity.id}/edit`"><Pencil class="size-4" /></Link>
                                        </Button>
                                        <Button variant="ghost" size="icon" title="Apagar" @click="destroy(entity)">
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="entities.total > 0" class="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                <span>A mostrar {{ entities.from }}-{{ entities.to }} de {{ entities.total }}</span>
                <div class="flex flex-wrap gap-1">
                    <Button
                        v-for="link in entities.links"
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
