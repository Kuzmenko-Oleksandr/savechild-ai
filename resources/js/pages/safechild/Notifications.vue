<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import Pagination, { type PaginationMeta } from '@/components/safechild/Pagination.vue';
import PillTabs from '@/components/safechild/PillTabs.vue';

interface NotifItem {
    id: number;
    childId: number;
    status: 'check_it_out' | 'in_progress' | 'resolved';
    title: string;
    body: string;
    created: string;
    read: boolean;
    child?: { id: number; name: string; photo?: string };
}

const props = defineProps<{ items: NotifItem[]; pagination: PaginationMeta; tab: string }>();

const tabs = [
    { value: 'all', label: 'All' },
    { value: 'check_it_out', label: 'CHECK IT OUT' },
    { value: 'in_progress', label: 'IN PROCESS' },
    { value: 'done', label: 'RESOLVED' },
];

const badge: Record<string, { cls: string; label: string }> = {
    check_it_out: { cls: 'bg-red-500 text-white', label: 'CHECK IT OUT' },
    in_progress: { cls: 'bg-sky-500 text-white', label: 'IN PROCESS' },
    resolved: { cls: 'bg-emerald-500 text-white', label: 'RESOLVED' },
};

function selectTab(id: string) {
    router.get('/notifications', { status: id === 'all' ? null : id }, { preserveScroll: true, preserveState: true, replace: true });
}
function goPage(page: number) {
    router.get('/notifications', { status: props.tab === 'all' ? null : props.tab, page }, { preserveScroll: true, preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Notifications" />

    <div class="flex flex-wrap items-start justify-between gap-3">
        <h1 class="text-3xl font-bold tracking-tight text-neutral-900 sm:text-4xl">Notifications</h1>
        <nav class="mt-1 flex items-center gap-2 text-sm text-neutral-400 sm:mt-3">
            <Link href="/" class="cursor-pointer hover:text-neutral-600">Home</Link>
            <ChevronRight class="size-4" />
            <span class="font-medium text-neutral-900">Notifications</span>
        </nav>
    </div>

    <div class="mt-6">
        <PillTabs :tabs="tabs" :model-value="tab" @update:model-value="selectTab" />
    </div>

    <div class="mt-5 space-y-4">
        <article v-for="n in items" :key="n.id" class="rounded-2xl bg-white p-5">
            <div class="flex items-start justify-between">
                <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', badge[n.status].cls]">{{ badge[n.status].label }}</span>
                <div class="flex items-center gap-2 text-xs text-neutral-400">
                    {{ n.created }}
                    <span v-if="!n.read" class="size-2 rounded-full bg-blue-500" />
                </div>
            </div>
            <h2 class="mt-3 font-semibold text-neutral-900">{{ n.title }}</h2>
            <p class="mt-1 line-clamp-3 text-sm leading-relaxed text-neutral-500">{{ n.body }}</p>
            <Link
                :href="`/children/${n.childId}`"
                class="mt-3 inline-flex cursor-pointer items-center gap-1 rounded-lg border border-neutral-200 px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50"
            >More details <ChevronRight class="size-4" /></Link>
        </article>
    </div>
    <p v-if="!items.length" class="mt-5 rounded-2xl bg-white py-12 text-center text-sm text-neutral-400">No notifications.</p>

    <div v-if="pagination.total" class="mt-6">
        <Pagination :meta="pagination" @change="({ page }) => goPage(page)" @per-page="() => {}" />
    </div>
</template>
