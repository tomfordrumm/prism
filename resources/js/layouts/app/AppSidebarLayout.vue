<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import TenantOnboardingModal from '@/components/TenantOnboardingModal.vue';
import { usePage } from '@inertiajs/vue3';
import type { BreadcrumbItemType } from '@/types';
import { computed } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const showTenantModal = computed(
    () => (page.props.tenancy?.needsTenant ?? false) && Boolean(page.props.auth?.user),
);
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <TenantOnboardingModal :open="showTenantModal" />
    </AppShell>
</template>
