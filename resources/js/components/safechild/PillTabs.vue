<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue';

interface Tab { value: string; label: string }

const props = defineProps<{ modelValue: string; tabs: Tab[]; uppercase?: boolean }>();
const emit = defineEmits<{ (e: 'update:modelValue', v: string): void }>();

const activeValue = ref(props.modelValue);
const btns = ref<HTMLElement[]>([]);
const indicator = ref({ left: 0, width: 0, ready: false });

function move() {
    const i = props.tabs.findIndex((t) => t.value === activeValue.value);
    const el = btns.value[i];
    if (el) indicator.value = { left: el.offsetLeft, width: el.offsetWidth, ready: true };
}

function select(value: string) {
    activeValue.value = value;
    nextTick(move);
    emit('update:modelValue', value);
}

onMounted(() => nextTick(move));
watch(() => props.modelValue, (value) => {
    activeValue.value = value;
    nextTick(move);
});
watch(() => props.tabs.map((t) => t.value).join(), () => {
    if (!props.tabs.some((t) => t.value === activeValue.value)) {
        activeValue.value = props.modelValue;
    }
    nextTick(move);
});
</script>

<template>
    <div class="relative inline-flex items-center gap-1 rounded-full bg-white p-1">
        <span
            v-show="indicator.ready"
            class="absolute bottom-1 left-0 top-1 rounded-full bg-neutral-900 transition-[transform,width] duration-300 ease-out"
            :style="{ transform: `translateX(${indicator.left}px)`, width: indicator.width + 'px' }"
        />
        <button
            v-for="(t, i) in tabs" :key="t.value"
            :ref="(el) => (btns[i] = el as HTMLElement)"
            @click="select(t.value)"
            class="relative z-10 cursor-pointer rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
            :class="activeValue === t.value ? 'text-white' : 'text-neutral-500 hover:text-neutral-800'"
        >{{ uppercase ? t.label.toUpperCase() : t.label }}</button>
    </div>
</template>
