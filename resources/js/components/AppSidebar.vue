<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import {
    BarChart3,
    Bell,
    Bot,
    BriefcaseBusiness,
    Building2,
    CalendarDays,
    ClipboardList,
    KanbanSquare,
    LayoutGrid,
    MessageSquare,
    Package,
    UsersRound,
    Workflow,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Chat CRM',
        url: '/ai-chat',
        icon: MessageSquare,
    },
    {
        title: 'Agente Comercial',
        url: '/ai-suggestions',
        icon: Bot,
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
    {
        title: 'Produtos',
        url: '/products',
        icon: Package,
    },
    {
        title: 'Formulários de Leads',
        url: '/lead-forms',
        icon: ClipboardList,
    },
    {
        title: 'Estatísticas de Produtos',
        url: '/product-stats',
        icon: BarChart3,
    },
    {
        title: 'Automações',
        url: '/automations',
        icon: Workflow,
    },
    {
        title: 'Notificações',
        url: '/notifications',
        icon: Bell,
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
