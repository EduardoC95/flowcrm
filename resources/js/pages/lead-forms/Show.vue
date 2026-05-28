<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Copy, ExternalLink, Pencil, Trash2 } from 'lucide-vue-next';

interface Submission {
    id: number;
    payload: Record<string, string | null>;
    name: string | null;
    email: string | null;
    source_url: string | null;
    ip_address: string | null;
    submitted_at: string | null;
    created_person: { id: number; name: string; email: string | null } | null;
    created_deal: { id: number; title: string } | null;
}

const props = defineProps<{
    leadForm: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        active: boolean;
        require_captcha: boolean;
        fields: unknown[];
        confirmation_message: string | null;
        embed: {
            public_url: string;
            iframe_embed_code: string;
            script_embed_code: string;
        };
    };
    submissions: {
        data: Submission[];
        total: number;
    };
    can: {
        update: boolean;
        delete: boolean;
    };
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Formulários de Leads', href: '/lead-forms' },
    { title: props.leadForm.name, href: `/lead-forms/${props.leadForm.id}` },
];

const copy = async (text: string) => {
    await navigator.clipboard?.writeText(text);
};

const destroy = () => {
    if (confirm(`Apagar o formulário "${props.leadForm.name}"?`)) {
        router.delete(`/lead-forms/${props.leadForm.id}`);
    }
};
</script>

<template>
    <Head :title="leadForm.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <section class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <Button as-child variant="ghost" size="sm" class="-ml-3 mb-2">
                        <Link href="/lead-forms">
                            <ArrowLeft class="size-4" />
                            Voltar
                        </Link>
                    </Button>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ leadForm.name }}</h1>
                    <p class="text-sm text-muted-foreground">{{ leadForm.description ?? 'Sem descrição.' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as-child variant="outline">
                        <a :href="leadForm.embed.public_url" target="_blank" rel="noreferrer"><ExternalLink class="size-4" /> Abrir público</a>
                    </Button>
                    <Button v-if="can.update" as-child>
                        <Link :href="`/lead-forms/${leadForm.id}/edit`"><Pencil class="size-4" /> Editar</Link>
                    </Button>
                    <Button v-if="can.delete" variant="destructive" @click="destroy"><Trash2 class="size-4" /> Apagar</Button>
                </div>
            </section>

            <div class="grid gap-4 md:grid-cols-4">
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Estado</p>
                    <p class="mt-2 text-2xl font-semibold">{{ leadForm.active ? 'Ativo' : 'Inativo' }}</p>
                </section>
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Captcha</p>
                    <p class="mt-2 text-2xl font-semibold">{{ leadForm.require_captcha ? 'Sim' : 'Não' }}</p>
                </section>
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Campos</p>
                    <p class="mt-2 text-2xl font-semibold">{{ leadForm.fields.length }}</p>
                </section>
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Submissões</p>
                    <p class="mt-2 text-2xl font-semibold">{{ submissions.total }}</p>
                </section>
            </div>

            <section class="space-y-4 rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                <h2 class="font-medium">Incorporação</h2>
                <div class="space-y-2">
                    <p class="text-sm text-muted-foreground">URL pública</p>
                    <div class="flex gap-2">
                        <code class="flex-1 rounded-md border bg-muted/40 px-3 py-2 text-sm">{{ leadForm.embed.public_url }}</code>
                        <Button type="button" variant="outline" @click="copy(leadForm.embed.public_url)"><Copy class="size-4" /> Copiar</Button>
                    </div>
                </div>
                <div class="space-y-2">
                    <p class="text-sm text-muted-foreground">Iframe</p>
                    <div class="flex gap-2">
                        <code class="flex-1 overflow-x-auto rounded-md border bg-muted/40 px-3 py-2 text-sm">{{
                            leadForm.embed.iframe_embed_code
                        }}</code>
                        <Button type="button" variant="outline" @click="copy(leadForm.embed.iframe_embed_code)"
                            ><Copy class="size-4" /> Copiar</Button
                        >
                    </div>
                </div>
                <div class="space-y-2">
                    <p class="text-sm text-muted-foreground">Script preparado</p>
                    <code class="block overflow-x-auto rounded-md border bg-muted/40 px-3 py-2 text-sm">{{ leadForm.embed.script_embed_code }}</code>
                </div>
            </section>

            <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                <h2 class="font-medium">Submissões</h2>
                <div class="mt-4 overflow-x-auto">
                    <table v-if="submissions.data.length" class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Data</th>
                                <th class="px-4 py-3 font-medium">Nome</th>
                                <th class="px-4 py-3 font-medium">Email</th>
                                <th class="px-4 py-3 font-medium">Origem</th>
                                <th class="px-4 py-3 font-medium">IP mascarado</th>
                                <th class="px-4 py-3 font-medium">Pessoa</th>
                                <th class="px-4 py-3 font-medium">Negócio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="submission in submissions.data" :key="submission.id" class="border-b last:border-0">
                                <td class="px-4 py-3 text-muted-foreground">{{ submission.submitted_at ?? '-' }}</td>
                                <td class="px-4 py-3">{{ submission.name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ submission.email ?? '-' }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ submission.source_url ?? '-' }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ submission.ip_address ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <Link
                                        v-if="submission.created_person"
                                        :href="`/people/${submission.created_person.id}`"
                                        class="text-primary hover:underline"
                                    >
                                        {{ submission.created_person.name }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3">
                                    <Link
                                        v-if="submission.created_deal"
                                        :href="`/deals/${submission.created_deal.id}`"
                                        class="text-primary hover:underline"
                                    >
                                        {{ submission.created_deal.title }}
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Ainda não há submissões para este formulário.
                    </p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
