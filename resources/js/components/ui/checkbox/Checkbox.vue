<script setup lang="ts">
import { Check } from "@lucide/vue"
import { reactiveOmit } from "@vueuse/core"
import type { CheckboxRootEmits, CheckboxRootProps } from "reka-ui"
import { CheckboxIndicator, CheckboxRoot, useForwardPropsEmits } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<CheckboxRootProps & { class?: HTMLAttributes["class"] }>()
const emits = defineEmits<CheckboxRootEmits>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <CheckboxRoot
    v-slot="slotProps"
    data-slot="checkbox"
    v-bind="forwarded"
    :class="
      cn('peer border-input data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-5 shrink-0 rounded-[5px] border shadow-xs transition-[color,background-color,border-color,box-shadow] duration-150 outline-none hover:border-foreground/40 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none',
         props.class)"
  >
    <CheckboxIndicator
      data-slot="checkbox-indicator"
      class="grid place-content-center text-current transition-none"
    >
      <slot v-bind="slotProps">
        <Check class="size-4" />
      </slot>
    </CheckboxIndicator>
  </CheckboxRoot>
</template>
