<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { watchDebounced } from '@vueuse/core';
import { ChevronRight, SlidersHorizontal } from '@lucide/vue';
import ChildrenTable, { type ChildRow } from '@/components/safechild/ChildrenTable.vue';
import Pagination, { type PaginationMeta } from '@/components/safechild/Pagination.vue';
import { searchQuery } from '@/composables/useSearch';

const props = defineProps<{
    children: ChildRow[];
    pagination: PaginationMeta;
    filters: { q: string; level: string };
}>();

const tabs = ['All', 'High', 'Medium', 'Low'] as const;
const active = ref<(typeof tabs)[number]>((props.filters.level as (typeof tabs)[number]) || 'All');

// Keep the shared header search box in sync with the server-applied filter.
searchQuery.value = props.filters.q ?? '';

// Server-side reload with the current filter set; partial reload keeps it snappy.
function reload(overrides: Record<string, string | number | null>) {
    router.get('/children', {
        level: active.value === 'All' ? null : active.value,
        q: searchQuery.value || null,
        per_page: props.pagination.per_page,
        page: 1,
        ...overrides,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['children', 'pagination', 'filters'],
    });
}

function selectTab(tab: (typeof tabs)[number]) {
    active.value = tab;
    reload({ page: 1 });
}

// Debounced search — fires only when the query actually differs from the server's.
watchDebounced(
    searchQuery,
    (q) => {
        if ((q || '') !== (props.filters.q || '')) reload({ page: 1 });
    },
    { debounce: 350 },
);
</script>

<template>
    <Head title="Children" />

    <div class="flex flex-wrap items-start justify-between gap-3">
        <h1 class="text-3xl font-bold tracking-tight text-neutral-900 sm:text-4xl">Children</h1>
        <nav class="mt-1 flex items-center gap-2 text-sm text-neutral-400 sm:mt-3">
            <Link href="/" class="cursor-pointer hover:text-neutral-600">Home</Link>
            <ChevronRight class="size-4" />
            <span class="font-medium text-neutral-900">Children</span>
        </nav>
    </div>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 sm:mt-8">
        <div class="inline-flex items-center gap-1 rounded-full bg-white p-1">
            <button
                v-for="tab in tabs" :key="tab"
                @click="selectTab(tab)"
                :class="['cursor-pointer rounded-full px-4 py-1.5 text-sm font-medium transition', active === tab ? 'bg-neutral-900 text-white' : 'text-neutral-500 hover:text-neutral-800']"
            >{{ tab }}</button>
        </div>
        <button class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-neutral-200 bg-white px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50">
            <SlidersHorizontal class="size-4" /> Filter
        </button>
    </div>

    <section class="mt-5 rounded-2xl bg-white p-4 sm:p-6">
        <ChildrenTable :rows="children" />
        <p v-if="!children.length" class="py-10 text-center text-sm text-neutral-400">No children match your search.</p>
        <div v-if="pagination.total" class="mt-6 border-t border-neutral-100 pt-5">
            <Pagination
                :meta="pagination"
                @change="({ page }) => reload({ page })"
                @per-page="({ per_page }) => reload({ per_page, page: 1 })"
            />
        </div>
    </section>
</template>
