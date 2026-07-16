<script setup lang="ts">
import type { SelectItemProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { Check } from "@lucide/vue"
import { reactiveOmit } from "@vueuse/core"
import {
  SelectItem,
  SelectItemIndicator,
  SelectItemText,
  useForwardProps,
} from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<SelectItemProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")

const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <SelectItem
    data-slot="select-item"
    v-bind="forwardedProps"
    :class="
      cn(
        'focus:bg-accent focus:text-accent-foreground data-[state=checked]:bg-primary/10 data-[state=checked]:text-primary [&_svg:not([class*=\'text-\'])]:text-muted-foreground relative flex min-h-11 w-full cursor-pointer items-center gap-2 rounded-sm py-2.5 pr-9 pl-3 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:cursor-not-allowed data-[disabled]:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=\'size-\'])]:size-4 *:[span]:last:flex *:[span]:last:items-center *:[span]:last:gap-2',
        props.class,
      )
    "
  >
    <span
      class="absolute right-3 flex size-4 items-center justify-center text-primary"
    >
      <SelectItemIndicator>
        <slot name="indicator-icon">
          <Check class="size-4" />
        </slot>
      </SelectItemIndicator>
    </span>

    <SelectItemText>
      <slot />
    </SelectItemText>
  </SelectItem>
</template>
