<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    id?: string;
    modelValue: string;
    class?: HTMLAttributes['class'];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const updateValue = (event: Event) => {
    const input = event.currentTarget as HTMLInputElement;
    const digits = input.value.replace(/\D/g, '').slice(0, 15);

    if (input.value !== digits) {
        input.value = digits;
    }

    emit('update:modelValue', digits);
};
</script>

<template>
    <div class="relative">
        <span
            class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm font-medium text-muted-foreground"
            aria-hidden="true"
        >
            Rp
        </span>
        <input
            v-bind="$attrs"
            :id="props.id"
            :value="props.modelValue"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            maxlength="15"
            :class="
                cn(
                    'h-11 w-full min-w-0 rounded-md border border-input bg-background py-1 pr-3 pl-10 text-base tabular-nums shadow-xs transition-[color,background-color,border-color,box-shadow] duration-150 outline-none selection:bg-primary selection:text-primary-foreground hover:border-foreground/30 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 motion-reduce:transition-none md:text-sm dark:bg-input/30 dark:aria-invalid:ring-destructive/40',
                    props.class,
                )
            "
            @input="updateValue"
        />
    </div>
</template>
