<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps<{
    filters: Record<string, string | number | null>;
    options: {
        types: string[];
        priorities: string[];
        statuses: string[];
        users: { id: number; name: string }[];
    };
    canViewAll: boolean;
}>();

const form = reactive({ ...props.filters });
const apply = () => router.get('/ai-suggestions', form, { preserveState: true, preserveScroll: true });
</script>

<template>
    <form class="grid gap-3 rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border md:grid-cols-5" @submit.prevent="apply">
        <select v-model="form.status" class="rounded-md border border-input bg-background px-3 py-2 text-sm">
            <option :value="null">Estado</option>
            <option v-for="status in options.statuses" :key="status" :value="status">{{ status }}</option>
        </select>
        <select v-model="form.priority" class="rounded-md border border-input bg-background px-3 py-2 text-sm">
            <option :value="null">Prioridade</option>
            <option v-for="priority in options.priorities" :key="priority" :value="priority">{{ priority }}</option>
        </select>
        <select v-model="form.type" class="rounded-md border border-input bg-background px-3 py-2 text-sm">
            <option :value="null">Tipo</option>
            <option v-for="type in options.types" :key="type" :value="type">{{ type }}</option>
        </select>
        <select v-if="canViewAll" v-model="form.user_id" class="rounded-md border border-input bg-background px-3 py-2 text-sm">
            <option :value="null">Responsavel</option>
            <option v-for="user in options.users" :key="user.id" :value="user.id">{{ user.name }}</option>
        </select>
        <Input v-else v-model="form.deal_id" placeholder="ID do negócio" />
        <Button type="submit">Filtrar</Button>
    </form>
</template>
