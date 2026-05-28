<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ExternalLink, Eye, Pencil, Plus, Trash2 } from 'lucide-vue-next';

interface LeadFormRow {
    id: number;
    name: string;
    slug: string;
    active: boolean;
    require_captcha: boolean;
    submissions_count: number | null;
    creator: { id: number; name: string } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{
    leadForms: {
        data: LeadFormRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    can: { create: boolean };
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Formulários de Leads', href: '/lead-forms' }];

const destroy = (form: LeadFormRow) => {
    if (confirm(`Apagar o formulário "${form.name}"?`)) {
        router.delete(`/lead-forms/${form.id}`);
    }
};
</script>

<template>
    <Head title="Formulários de Leads" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Formulários de Leads</h1>
                    <p class="text-sm text-muted-foreground">Capture leads a partir de páginas públicas incorporáveis.</p>
                </div>
                <Button v-if="can.create" as-child>
                    <Link href="/lead-forms/create">
                        <Plus class="size-4" />
                        Novo formulário
                    </Link>
                </Button>
            </section>

            <div class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div v-if="leadForms.data.length === 0" class="flex min-h-64 flex-col items-center justify-center gap-2 p-8 text-center">
                    <h2 class="text-lg font-medium">Ainda não há formulários</h2>
                    <p class="max-w-md text-sm text-muted-foreground">Crie um formulário público para gerar leads automaticamente no CRM.</p>
                    <Button v-if="can.create" as-child class="mt-2">
                        <Link href="/lead-forms/create">Criar formulário</Link>
                    </Button>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Nome</th>
                                <th class="px-4 py-3 font-medium">Slug</th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                                <th class="px-4 py-3 font-medium">Captcha</th>
                                <th class="px-4 py-3 font-medium">Submissões</th>
                                <th class="px-4 py-3 font-medium">Criado por</th>
                                <th class="px-4 py-3 text-right font-medium">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="form in leadForms.data" :key="form.id" class="border-b last:border-0">
                                <td class="px-4 py-3 font-medium">{{ form.name }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ form.slug }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full border px-2 py-1 text-xs font-medium"
                                        :class="
                                            form.active
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                : 'border-zinc-200 bg-zinc-50 text-zinc-700'
                                        "
                                    >
                                        {{ form.active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ form.require_captcha ? 'Sim' : 'Não' }}</td>
                                <td class="px-4 py-3">{{ form.submissions_count ?? 0 }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ form.creator?.name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <Button as-child variant="ghost" size="icon" title="Ver">
                                            <Link :href="`/lead-forms/${form.id}`"><Eye class="size-4" /></Link>
                                        </Button>
                                        <Button as-child variant="ghost" size="icon" title="Editar">
                                            <Link :href="`/lead-forms/${form.id}/edit`"><Pencil class="size-4" /></Link>
                                        </Button>
                                        <Button as-child variant="ghost" size="icon" title="Abrir público">
                                            <a :href="`/public/lead-forms/${form.slug}`" target="_blank" rel="noreferrer"
                                                ><ExternalLink class="size-4"
                                            /></a>
                                        </Button>
                                        <Button variant="ghost" size="icon" title="Apagar" @click="destroy(form)">
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
