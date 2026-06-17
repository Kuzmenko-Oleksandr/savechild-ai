<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onUnmounted, reactive, ref } from 'vue';
import { watchDebounced } from '@vueuse/core';
import { ChevronRight, Search, SlidersHorizontal } from '@lucide/vue';
import ChildrenTable, { type ChildRow } from '@/components/safechild/ChildrenTable.vue';
import Pagination, { type PaginationMeta } from '@/components/safechild/Pagination.vue';
import PillTabs from '@/components/safechild/PillTabs.vue';
import { filterPanelOpen as panelOpen } from '@/composables/usePanel';

interface Filters {
    q: string; level: string; school: string;
    sex: string[]; event: string[]; age: string[]; status: string[]; period: number | null;
}
interface Opt { value: string; label: string }
interface Options {
    schools: string[];
    events: Opt[]; sexes: Opt[]; statuses: Opt[];
    ages: string[];
    periods: { value: number; label: string }[];
}

const props = defineProps<{
    children: ChildRow[];
    pagination: PaginationMeta;
    filters: Filters;
    sort: { by: string | null; dir: string };
    filterOptions: Options;
}>();

const tabs = ['All', 'High', 'Medium', 'Low'] as const;
const active = ref<(typeof tabs)[number]>((props.filters.level as (typeof tabs)[number]) || 'All');
const search = ref(props.filters.q ?? '');

const form = reactive({
    sex: [...(props.filters.sex ?? [])] as string[],
    age: [...(props.filters.age ?? [])] as string[],
    event: [...(props.filters.event ?? [])] as string[],
    status: [...(props.filters.status ?? [])] as string[],
    period: props.filters.period ?? (null as number | null),
    school: props.filters.school ?? '',
});

const tableKey = computed(() =>
    `${props.pagination.current_page}|${props.sort.by}|${props.sort.dir}|${props.children.map((c) => c.id).join(',')}`,
);

const activeFilterCount = computed(() =>
    form.sex.length + form.age.length + form.event.length + form.status.length +
    (form.period ? 1 : 0) + (form.school ? 1 : 0),
);

onUnmounted(() => (panelOpen.value = false));

function togglePeriod(v: number) {
    form.period = form.period === v ? null : v;
    reload();
}

function reload(overrides: Record<string, unknown> = {}) {
    router.get('/children', {
        level: active.value === 'All' ? null : active.value,
        q: search.value || null,
        sex: form.sex.length ? form.sex : null,
        age: form.age.length ? form.age : null,
        event: form.event.length ? form.event : null,
        status: form.status.length ? form.status : null,
        period: form.period || null,
        school: form.school || null,
        sort: props.sort.by || null,
        dir: props.sort.dir,
        per_page: props.pagination.per_page,
        page: 1,
        ...overrides,
    }, { preserveState: true, preserveScroll: true, replace: true, only: ['children', 'pagination', 'filters', 'sort', 'filterOptions'] });
}

function selectTab(tab: (typeof tabs)[number]) {
    active.value = tab;
    reload();
}
function clearFilters() {
    form.sex = []; form.age = []; form.event = []; form.status = []; form.period = null; form.school = '';
    reload();
}
function onSort(key: string) {
    if (props.sort.by !== key) {
        reload({ sort: key, dir: 'asc' });
        return;
    }

    if (props.sort.dir === 'asc') {
        reload({ sort: key, dir: 'desc' });
        return;
    }

    reload({ sort: null, dir: null });
}

watchDebounced(search, (v) => {
    if ((v || '') !== (props.filters.q || '')) reload();
}, { debounce: 350 });
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

    <!-- Page search -->
    <div class="relative mt-6">
        <Search class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-neutral-400" />
        <input
            v-model="search"
            type="text"
            placeholder="Search child"
            class="h-12 w-full rounded-full border border-neutral-200 bg-white pl-11 pr-4 text-sm text-neutral-700 placeholder:text-neutral-400 focus:border-neutral-300 focus:outline-none focus:ring-4 focus:ring-neutral-100"
        />
    </div>

    <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
        <PillTabs :tabs="tabs.map((t) => ({ value: t, label: t }))" :model-value="active" uppercase @update:model-value="(v) => selectTab(v as any)" />

        <button
            @click="panelOpen = !panelOpen"
            class="inline-flex cursor-pointer items-center gap-2 rounded-full border bg-white px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50"
            :class="(activeFilterCount || panelOpen) ? 'border-neutral-900' : 'border-neutral-200'"
        >
            <SlidersHorizontal class="size-4" /> Filter
            <span v-if="activeFilterCount" class="flex size-5 items-center justify-center rounded-full bg-neutral-900 text-xs font-semibold text-white">{{ activeFilterCount }}</span>
        </button>
    </div>

    <!-- Full-width filter panel -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
            <div v-if="panelOpen" class="fixed inset-0 top-[72px] z-40 bg-black/20" @click="panelOpen = false" />
        </Transition>
        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 -translate-y-3" leave-active-class="transition duration-150 ease-in" leave-to-class="opacity-0 -translate-y-3">
        <div v-if="panelOpen" class="fixed left-0 right-0 top-[72px] z-50 border-t border-neutral-100 bg-white shadow-xl">
            <div class="mx-auto max-w-[1320px] px-6 py-7">
                <div class="grid grid-cols-2 gap-x-8 gap-y-6 sm:grid-cols-3 lg:grid-cols-6">
                    <div>
                        <p class="mb-3 text-xs font-medium text-neutral-400">Sex</p>
                        <label v-for="s in filterOptions.sexes" :key="s.value" class="mb-2.5 flex cursor-pointer items-center gap-2.5 text-sm text-neutral-700">
                            <input type="checkbox" :value="s.value" v-model="form.sex" @change="reload()" class="size-[18px] cursor-pointer rounded accent-neutral-900" /> {{ s.label }}
                        </label>
                    </div>
                    <div>
                        <p class="mb-3 text-xs font-medium text-neutral-400">Age</p>
                        <label v-for="a in filterOptions.ages" :key="a" class="mb-2.5 flex cursor-pointer items-center gap-2.5 text-sm text-neutral-700">
                            <input type="checkbox" :value="a" v-model="form.age" @change="reload()" class="size-[18px] cursor-pointer rounded accent-neutral-900" /> {{ a }} years
                        </label>
                    </div>
                    <div>
                        <p class="mb-3 text-xs font-medium text-neutral-400">Status</p>
                        <label v-for="s in filterOptions.statuses" :key="s.value" class="mb-2.5 flex cursor-pointer items-center gap-2.5 text-sm text-neutral-700">
                            <input type="checkbox" :value="s.value" v-model="form.status" @change="reload()" class="size-[18px] cursor-pointer rounded accent-neutral-900" /> {{ s.label }}
                        </label>
                    </div>
                    <div>
                        <p class="mb-3 text-xs font-medium text-neutral-400">Event type</p>
                        <label v-for="e in filterOptions.events" :key="e.value" class="mb-2.5 flex cursor-pointer items-center gap-2.5 text-sm text-neutral-700">
                            <input type="checkbox" :value="e.value" v-model="form.event" @change="reload()" class="size-[18px] shrink-0 cursor-pointer rounded accent-neutral-900" /> {{ e.label }}
                        </label>
                    </div>
                    <div>
                        <p class="mb-3 text-xs font-medium text-neutral-400">Period</p>
                        <label v-for="p in filterOptions.periods" :key="p.value" class="mb-2.5 flex cursor-pointer items-center gap-2.5 text-sm text-neutral-700">
                            <input type="checkbox" :checked="form.period === p.value" @change="togglePeriod(p.value)" class="size-[18px] cursor-pointer rounded accent-neutral-900" /> {{ p.label }}
                        </label>
                    </div>
                    <div>
                        <p class="mb-3 text-xs font-medium text-neutral-400">School</p>
                        <select v-model="form.school" @change="reload()" class="w-full cursor-pointer rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-sm focus:border-neutral-300 focus:outline-none focus:ring-4 focus:ring-neutral-100">
                            <option value="">Select option</option>
                            <option v-for="s in filterOptions.schools" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>
                </div>
                <div v-if="activeFilterCount" class="mt-5 flex justify-end">
                    <button @click="clearFilters" class="cursor-pointer text-sm font-medium text-neutral-500 hover:text-neutral-800">Clear all</button>
                </div>
            </div>
        </div>
        </Transition>
    </Teleport>

    <section class="mt-5 rounded-2xl bg-white p-4 sm:p-6">
        <Transition mode="out-in"
            enter-active-class="transition-opacity duration-150 ease-out" enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-100 ease-in" leave-to-class="opacity-0">
            <div :key="tableKey">
                <ChildrenTable :rows="children" sortable :sort="sort" @sort="onSort" />
                <p v-if="!children.length" class="py-10 text-center text-sm text-neutral-400">No children match your filters.</p>
            </div>
        </Transition>
        <div v-if="pagination.total" class="mt-6 border-t border-neutral-100 pt-5">
            <Pagination :meta="pagination" @change="({ page }) => reload({ page })" @per-page="({ per_page }) => reload({ per_page, page: 1 })" />
        </div>
    </section>
</template>
