<script setup lang="ts">
import { computed } from 'vue';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from '@lucide/vue';

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    per_page_options: number[];
    total: number;
    from: number;
    to: number;
}

const props = defineProps<{ meta: PaginationMeta }>();
const emit = defineEmits<{
    (e: 'change', payload: { page: number }): void;
    (e: 'perPage', payload: { per_page: number }): void;
}>();

// PrimeVue-style page link list with leading/trailing pages and ellipsis gaps.
const pages = computed<(number | '…')[]>(() => {
    const last = props.meta.last_page;
    const cur = props.meta.current_page;
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);

    const set = new Set<number>([1, last, cur, cur - 1, cur + 1]);
    if (cur <= 3) [2, 3, 4].forEach((n) => set.add(n));
    if (cur >= last - 2) [last - 1, last - 2, last - 3].forEach((n) => set.add(n));

    const sorted = [...set].filter((n) => n >= 1 && n <= last).sort((a, b) => a - b);
    const out: (number | '…')[] = [];
    sorted.forEach((n, i) => {
        if (i > 0 && n - sorted[i - 1] > 1) out.push('…');
        out.push(n);
    });
    return out;
});

const canPrev = computed(() => props.meta.current_page > 1);
const canNext = computed(() => props.meta.current_page < props.meta.last_page);

function go(page: number) {
    if (page >= 1 && page <= props.meta.last_page && page !== props.meta.current_page) {
        emit('change', { page });
    }
}
</script>

<template>
    <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
        <p class="text-sm text-neutral-500">
            Showing <span class="font-medium text-neutral-900">{{ meta.from }}</span>
            to <span class="font-medium text-neutral-900">{{ meta.to }}</span>
            of <span class="font-medium text-neutral-900">{{ meta.total.toLocaleString() }}</span> children
        </p>

        <div class="flex items-center gap-1.5">
            <button
                :disabled="!canPrev" @click="go(1)"
                class="flex size-9 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 bg-white text-neutral-600 transition hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-40"
                aria-label="First page"
            ><ChevronsLeft class="size-4" /></button>
            <button
                :disabled="!canPrev" @click="go(meta.current_page - 1)"
                class="flex size-9 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 bg-white text-neutral-600 transition hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-40"
                aria-label="Previous page"
            ><ChevronLeft class="size-4" /></button>

            <template v-for="(p, i) in pages" :key="i">
                <span v-if="p === '…'" class="flex size-9 items-center justify-center text-sm text-neutral-400">…</span>
                <button
                    v-else
                    @click="go(p)"
                    :class="['flex size-9 cursor-pointer items-center justify-center rounded-lg border text-sm font-medium transition',
                        p === meta.current_page
                            ? 'border-neutral-900 bg-neutral-900 text-white'
                            : 'border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50']"
                >{{ p }}</button>
            </template>

            <button
                :disabled="!canNext" @click="go(meta.current_page + 1)"
                class="flex size-9 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 bg-white text-neutral-600 transition hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-40"
                aria-label="Next page"
            ><ChevronRight class="size-4" /></button>
            <button
                :disabled="!canNext" @click="go(meta.last_page)"
                class="flex size-9 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 bg-white text-neutral-600 transition hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-40"
                aria-label="Last page"
            ><ChevronsRight class="size-4" /></button>
        </div>

        <div class="flex items-center gap-2 text-sm text-neutral-500">
            <span>Rows per page</span>
            <select
                :value="meta.per_page"
                @change="emit('perPage', { per_page: Number(($event.target as HTMLSelectElement).value) })"
                class="cursor-pointer rounded-lg border border-neutral-200 bg-white py-1.5 pl-2.5 pr-7 text-sm font-medium text-neutral-900 focus:border-neutral-300 focus:outline-none focus:ring-4 focus:ring-neutral-100"
            >
                <option v-for="opt in meta.per_page_options" :key="opt" :value="opt">{{ opt }}</option>
            </select>
        </div>
    </div>
</template>
