<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <AppLogoIcon class="aspect-square size-full shrink-0 object-cover" />
    </div>
    <div
        class="ml-1 flex min-w-0 flex-1 items-baseline overflow-hidden text-left leading-none whitespace-nowrap group-data-[collapsible=icon]:hidden"
        aria-label="DuitBot Tracker"
    >
        <span
            class="bg-gradient-to-r from-cyan-600 via-teal-500 to-emerald-400 bg-clip-text text-[19px] font-black tracking-tight text-transparent dark:from-cyan-400 dark:via-teal-300 dark:to-emerald-300"
            >DuitBot</span
        ><span
            class="ml-1.5 text-[19px] font-bold tracking-tight text-slate-900 dark:text-slate-100"
            >Tracker</span
        >
    </div>
</template>
