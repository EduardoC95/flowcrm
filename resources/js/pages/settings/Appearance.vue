<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Definições de aparência',
        href: '/settings/appearance',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Definições de aparência" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Definições de aparência" description="Atualize o aspeto visual da sua conta" />
                <AppearanceTabs />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
