<script setup lang="ts">
import AISuggestionCard from '@/components/ai-suggestions/AISuggestionCard.vue';
import AISuggestionFilters from '@/components/ai-suggestions/AISuggestionFilters.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Bot, Target } from 'lucide-vue-next';

interface Suggestion {
    id: number;
    type: string;
    title: string;
    reason: string;
    suggested_action: string;
    suggested_due_at: string | null;
    priority: string;
    status: string;
    score: number;
    url: string;
    deal: { id: number; title: string; value: number; url: string } | null;
    person: { id: number; name: string; url: string } | null;
    entity: { id: number; name: string; url: string } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{
    suggestions: {
        data: Suggestion[];
        links: PaginationLink[];
        total: number;
    };
    filters: Record<string, string | number | null>;
    summary: {
        pending: number;
        urgent: number;
        impacted_value: number;
        converted_this_week: number;
    };
    options: {
        types: string[];
        priorities: string[];
        statuses: string[];
        users: { id: number; name: string }[];
    };
    canViewAll: boolean;
    canAct: boolean;
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Agente Comercial AI', href: '/ai-suggestions' }];
const money = (value: number) => new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(value);
</script>

<template>
    <Head title="Agente Comercial AI" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <section class="flex flex-col gap-2">
                <div class="flex items-center gap-2">
                    <Bot class="size-5 text-primary" />
                    <h1 class="text-2xl font-semibold tracking-tight">Agente Comercial AI</h1>
                </div>
                <p class="text-sm text-muted-foreground">Sugestoes proativas para avancar negocios com o proximo passo de maior valor.</p>
            </section>

            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Pendentes</p>
                    <p class="mt-2 text-3xl font-semibold">{{ summary.pending }}</p>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Urgentes</p>
                    <p class="mt-2 text-3xl font-semibold">{{ summary.urgent }}</p>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Valor impactado</p>
                    <p class="mt-2 text-2xl font-semibold">{{ money(summary.impacted_value) }}</p>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Convertidas esta semana</p>
                    <p class="mt-2 text-3xl font-semibold">{{ summary.converted_this_week }}</p>
                </div>
            </div>

            <AISuggestionFilters :filters="filters" :options="options" :can-view-all="canViewAll" />

            <div
                v-if="suggestions.data.length === 0"
                class="flex min-h-64 flex-col items-center justify-center gap-2 rounded-lg border border-dashed p-8 text-center"
            >
                <Target class="size-8 text-muted-foreground" />
                <h2 class="text-lg font-medium">Sem sugestoes neste filtro</h2>
                <p class="max-w-md text-sm text-muted-foreground">O agente vai alimentar este backlog com analise diaria e eventos recentes.</p>
                <Button as-child variant="outline" class="mt-2"><Link href="/ai-chat">Perguntar ao Chat CRM</Link></Button>
            </div>

            <div v-else class="space-y-3">
                <AISuggestionCard v-for="suggestion in suggestions.data" :key="suggestion.id" :suggestion="suggestion" :can-act="canAct" />
            </div>

            <div v-if="suggestions.links.length > 3" class="flex flex-wrap gap-2">
                <Button v-for="link in suggestions.links" :key="link.label" as-child variant="outline" size="sm" :disabled="!link.url">
                    <Link v-if="link.url" :href="link.url" v-html="link.label" />
                    <span v-else v-html="link.label" />
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
