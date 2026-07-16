<script setup lang="ts">
import { X } from "@lucide/vue"
import { reactiveOmit } from "@vueuse/core"
import type { DialogContentEmits, DialogContentProps } from "reka-ui"
import {
  DialogClose,
  DialogContent,
  DialogOverlay,
  DialogPortal,
  useForwardPropsEmits,
} from "reka-ui"
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

defineOptions({
  inheritAttrs: false,
})

const props = defineProps<DialogContentProps & { class?: HTMLAttributes["class"] }>()
const emits = defineEmits<DialogContentEmits>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <DialogPortal>
    <DialogOverlay
      class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-black/80  data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
    >
      <DialogContent
        :class="
          cn(
            'relative z-50 grid w-full max-w-lg my-8 gap-4 border border-border bg-background p-6 shadow-lg duration-200 sm:rounded-lg md:w-full',
            props.class,
          )
        "
        v-bind="{ ...$attrs, ...forwarded }"
        @pointer-down-outside="(event) => {
          const originalEvent = event.detail.originalEvent;
          const target = originalEvent.target as HTMLElement;
          if (originalEvent.offsetX > target.clientWidth || originalEvent.offsetY > target.clientHeight) {
            event.preventDefault();
          }
        }"
      >
        <slot />

        <DialogClose
          class="focus-visible:ring-ring absolute top-2 right-2 inline-flex size-11 items-center justify-center rounded-md transition-[color,background-color,box-shadow] duration-150 hover:bg-secondary focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden motion-reduce:transition-none"
        >
          <X class="size-4" />
          <span class="sr-only">Tutup</span>
        </DialogClose>
      </DialogContent>
    </DialogOverlay>
  </DialogPortal>
</template>
