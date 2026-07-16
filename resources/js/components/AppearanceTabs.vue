<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const;
</script>

<template>
    <div
        class="inline-flex gap-1 rounded-lg bg-muted p-1"
        role="group"
        aria-label="Tema tampilan"
    >
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            type="button"
            @click="updateAppearance(value)"
            :aria-pressed="appearance === value"
            :class="[
                'flex min-h-11 items-center rounded-md px-3.5 transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                appearance === value
                    ? 'bg-background text-foreground shadow-xs'
                    : 'text-muted-foreground hover:bg-background/70 hover:text-foreground',
            ]"
        >
            <component :is="Icon" class="-ml-1 h-4 w-4" />
            <span class="ml-1.5 text-sm">{{ label }}</span>
        </button>
    </div>
</template>
