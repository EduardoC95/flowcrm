<script setup lang="ts">
import AISuggestionActions from '@/components/ai-suggestions/AISuggestionActions.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Bot } from 'lucide-vue-next';

const props = defineProps<{
    suggestion: {
        id: number;
        type: string;
        title: string;
        reason: string;
        suggested_action: string;
        suggested_due_at: string | null;
        priority: string;
        status: string;
        source: string | null;
        score: number;
        metadata: Record<string, unknown>;
        created_at: string | null;
        user: { id: number; name: string } | null;
        deal: {
            id: number;
            title: string;
            value: number;
            priority: string | null;
            expected_close_date: string | null;
            last_activity_at: string | null;
            url: string;
            stage: { name: string } | null;
            owner: { name: string } | null;
        } | null;
        person: { id: number; name: string; email: string | null; phone: string | null; url: string } | null;
        entity: { id: number; name: string; email: string | null; phone: string | null; url: string } | null;
        converted_calendar_event: { id: number; title: string; start_at: string | null; url: string } | null;
    };
    can: { act: boolean };
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Agente Comercial AI', href: '/ai-suggestions' },
    { title: props.suggestion.title, href: `/ai-suggestions/${props.suggestion.id}` },
];
const money = (value: number) => new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(value);
</script>

<template>
    <Head :title="suggestion.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <Button as-child variant="ghost" class="w-fit"
                ><Link href="/ai-suggestions"><ArrowLeft class="size-4" /> Voltar</Link></Button
            >

            <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <Bot class="size-5 text-primary" />
                            <h1 class="text-2xl font-semibold">{{ suggestion.title }}</h1>
                        </div>
                        <p class="mt-2 text-sm text-muted-foreground">{{ suggestion.reason }}</p>
                    </div>
                    <AISuggestionActions :suggestion-id="suggestion.id" :can-act="can.act" />
                </div>
                <div class="mt-5 grid gap-3 md:grid-cols-4">
                    <div class="rounded-md border p-3">
                        <p class="text-xs text-muted-foreground">Prioridade</p>
                        <p class="font-medium">{{ suggestion.priority }}</p>
                    </div>
                    <div class="rounded-md border p-3">
                        <p class="text-xs text-muted-foreground">Estado</p>
                        <p class="font-medium">{{ suggestion.status }}</p>
                    </div>
                    <div class="rounded-md border p-3">
                        <p class="text-xs text-muted-foreground">Score</p>
                        <p class="font-medium">{{ suggestion.score }}</p>
                    </div>
                    <div class="rounded-md border p-3">
                        <p class="text-xs text-muted-foreground">Prazo sugerido</p>
                        <p class="font-medium">{{ suggestion.suggested_due_at ?? '-' }}</p>
                    </div>
                </div>
            </section>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <h2 class="font-medium">Contexto comercial</h2>
                    <div class="mt-4 space-y-3 text-sm">
                        <p><span class="text-muted-foreground">Ação sugerida:</span> {{ suggestion.suggested_action }}</p>
                        <p><span class="text-muted-foreground">Responsavel:</span> {{ suggestion.user?.name ?? '-' }}</p>
                        <p><span class="text-muted-foreground">Origem:</span> {{ suggestion.source ?? '-' }}</p>
                        <Button v-if="suggestion.deal" as-child variant="outline"><Link :href="suggestion.deal.url">Abrir negócio</Link></Button>
                        <Button v-if="suggestion.person" as-child variant="outline"><Link :href="suggestion.person.url">Abrir pessoa</Link></Button>
                        <Button v-if="suggestion.entity" as-child variant="outline"><Link :href="suggestion.entity.url">Abrir entidade</Link></Button>
                    </div>
                </section>

                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <h2 class="font-medium">Negócio</h2>
                    <div v-if="suggestion.deal" class="mt-4 space-y-2 text-sm">
                        <p class="font-medium">{{ suggestion.deal.title }}</p>
                        <p class="text-muted-foreground">Etapa: {{ suggestion.deal.stage?.name ?? '-' }}</p>
                        <p class="text-muted-foreground">Valor: {{ money(suggestion.deal.value) }}</p>
                        <p class="text-muted-foreground">Responsavel: {{ suggestion.deal.owner?.name ?? '-' }}</p>
                        <p class="text-muted-foreground">Ultima atividade: {{ suggestion.deal.last_activity_at ?? '-' }}</p>
                        <p class="text-muted-foreground">Fecho previsto: {{ suggestion.deal.expected_close_date ?? '-' }}</p>
                    </div>
                    <p v-else class="mt-4 text-sm text-muted-foreground">Sugestão sem negócio associado.</p>
                </section>
            </div>

            <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                <h2 class="font-medium">Metadata</h2>
                <pre class="mt-4 overflow-x-auto rounded-md bg-muted p-4 text-xs">{{ JSON.stringify(suggestion.metadata, null, 2) }}</pre>
                <Button v-if="suggestion.converted_calendar_event" as-child class="mt-4" variant="outline">
                    <Link :href="suggestion.converted_calendar_event.url">Abrir atividade criada</Link>
                </Button>
            </section>
        </div>
    </AppLayout>
</template>
