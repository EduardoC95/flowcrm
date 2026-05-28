<script setup lang="ts">
import AISuggestionActions from '@/components/ai-suggestions/AISuggestionActions.vue';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, BadgeAlert } from 'lucide-vue-next';

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

defineProps<{
    suggestion: Suggestion;
    canAct?: boolean;
}>();
</script>

<template>
    <article class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <BadgeAlert class="size-4 text-primary" />
                    <Link :href="suggestion.url" class="font-semibold text-primary hover:underline">{{ suggestion.title }}</Link>
                    <span class="rounded-full border px-2 py-0.5 text-xs">{{ suggestion.priority }}</span>
                    <span class="rounded-full border px-2 py-0.5 text-xs">score {{ suggestion.score }}</span>
                    <span class="rounded-full border px-2 py-0.5 text-xs">{{ suggestion.status }}</span>
                </div>
                <p class="text-sm text-muted-foreground">{{ suggestion.reason }}</p>
                <p class="text-sm font-medium">Acao sugerida: {{ suggestion.suggested_action }}</p>
                <p class="text-xs text-muted-foreground">Data sugerida: {{ suggestion.suggested_due_at ?? '-' }}</p>
                <div class="flex flex-wrap gap-2 text-sm">
                    <Button v-if="suggestion.deal" as-child variant="outline" size="sm">
                        <Link :href="suggestion.deal.url">Negocio: {{ suggestion.deal.title }}</Link>
                    </Button>
                    <Button v-if="suggestion.person" as-child variant="outline" size="sm">
                        <Link :href="suggestion.person.url">Pessoa: {{ suggestion.person.name }}</Link>
                    </Button>
                    <Button v-if="suggestion.entity" as-child variant="outline" size="sm">
                        <Link :href="suggestion.entity.url">Entidade: {{ suggestion.entity.name }}</Link>
                    </Button>
                </div>
            </div>
            <div class="flex shrink-0 flex-col items-start gap-2 lg:items-end">
                <Button as-child variant="ghost" size="sm">
                    <Link :href="suggestion.url">Ver detalhe <ArrowRight class="size-4" /></Link>
                </Button>
                <AISuggestionActions :suggestion-id="suggestion.id" :can-act="canAct" />
            </div>
        </div>
    </article>
</template>
