<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { CheckCheck } from 'lucide-vue-next';

interface NotificationRow {
    id: number;
    title: string;
    body: string | null;
    type: string;
    read_at: string | null;
    created_at: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{
    notifications: {
        data: NotificationRow[];
        links: PaginationLink[];
        total: number;
    };
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notificações', href: '/notifications' }];

const markAsRead = (notification: NotificationRow) => router.patch(`/notifications/${notification.id}/read`);
const markAllAsRead = () => router.patch('/notifications/read-all');
</script>

<template>
    <Head title="Notificações" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Notificações</h1>
                    <p class="text-sm text-muted-foreground">Alertas internos criados por automações e fluxos comerciais.</p>
                </div>
                <Button variant="outline" @click="markAllAsRead">
                    <CheckCheck class="size-4" />
                    Marcar todas como lidas
                </Button>
            </section>

            <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                <div v-if="notifications.data.length" class="space-y-3">
                    <article
                        v-for="notification in notifications.data"
                        :key="notification.id"
                        class="rounded-md border p-4 text-sm"
                        :class="notification.read_at ? 'bg-background' : 'bg-primary/5'"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="font-medium">{{ notification.title }}</p>
                                <p class="mt-1 text-muted-foreground">{{ notification.body ?? 'Sem detalhe adicional.' }}</p>
                                <p class="mt-2 text-xs text-muted-foreground">{{ notification.created_at ?? '-' }} · {{ notification.type }}</p>
                            </div>
                            <Button v-if="!notification.read_at" variant="outline" size="sm" @click="markAsRead(notification)"
                                >Marcar como lida</Button
                            >
                        </div>
                    </article>
                </div>
                <p v-else class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">Sem notificações internas.</p>
            </section>
        </div>
    </AppLayout>
</template>
