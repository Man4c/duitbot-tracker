<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });
defineProps<{ className?: HTMLAttributes['class'] }>();
</script>

<template>
    <img
        src="/brand/duitbot-logo.png"
        alt=""
        :class="className"
        v-bind="$attrs"
    />
</template>
