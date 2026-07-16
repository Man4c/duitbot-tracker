<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const isDashboard = computed(() => page.url.startsWith('/dashboard'));
</script>

<template>
    <header
        class="h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
        :class="isDashboard ? 'hidden lg:flex' : 'flex'"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
    </header>
</template>
