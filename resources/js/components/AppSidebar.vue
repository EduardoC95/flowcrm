<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { BriefcaseBusiness, Building2, CalendarDays, KanbanSquare, LayoutGrid, UsersRound } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Entidades',
        url: '/entities',
        icon: Building2,
    },
    {
        title: 'Pessoas',
        url: '/people',
        icon: UsersRound,
    },
    {
        title: 'Calendário',
        url: '/calendar',
        icon: CalendarDays,
    },
    {
        title: 'Negócios',
        url: '/deals',
        icon: BriefcaseBusiness,
    },
    {
        title: 'Pipeline',
        url: '/deals-board',
        icon: KanbanSquare,
    },
];

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <a :href="route('dashboard')">
                            <AppLogo />
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
