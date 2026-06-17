<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ level: 'High' | 'Medium' | 'Low'; label?: string }>();

const classes = computed(() => ({
    High: 'bg-red-500 text-white',
    Medium: 'bg-amber-400 text-amber-950',
    Low: 'bg-emerald-500 text-white',
}[props.level]));

const displayLabel = computed(() => props.label ?? props.level.toUpperCase());
</script>

<template>
    <span :class="['inline-flex w-fit items-center rounded-full px-2.5 py-1 text-xs font-semibold', classes]">
        {{ displayLabel }}
    </span>
</template>
