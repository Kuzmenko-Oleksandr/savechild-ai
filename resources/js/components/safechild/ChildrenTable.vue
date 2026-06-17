<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronRight, ChevronsUpDown, ChevronUp, ChevronDown } from '@lucide/vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import RiskBadge from './RiskBadge.vue';

export interface ChildRow {
    id: number;
    name: string;
    level: 'High' | 'Medium' | 'Low';
    age: string;
    school: string;
    event: string;
    eventDate: string;
    updated: string;
    photo?: string;
}

const props = defineProps<{
    rows: ChildRow[];
    sortable?: boolean;
    sort?: { by: string | null; dir: string };
}>();

const emit = defineEmits<{ (e: 'sort', key: string): void }>();

const columns: { key: string; label: string }[] = [
    { key: 'child', label: 'Child' },
    { key: 'risk', label: 'Risk Level' },
    { key: 'age', label: 'Age' },
    { key: 'school', label: 'School' },
    { key: 'event', label: 'Latest Event' },
    { key: 'updated', label: 'Last Updated' },
];

const initials = (name: string) =>
    name.split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase();

function openChild(row: ChildRow) {
    router.visit(`/children/${row.id}`);
}
</script>

<template>
    <div class="-mx-2 overflow-x-auto px-2">
        <table class="w-full min-w-[760px] border-collapse text-sm">
            <thead>
                <tr class="text-left text-xs font-medium text-neutral-400">
                    <th v-for="col in columns" :key="col.key" class="pb-3 font-medium">
                        <button
                            v-if="sortable"
                            @click="emit('sort', col.key)"
                            class="inline-flex cursor-pointer items-center gap-1 transition hover:text-neutral-700"
                            :class="{ 'text-neutral-700': sort?.by === col.key }"
                        >
                            {{ col.label }}
                            <ChevronUp v-if="sort?.by === col.key && sort?.dir === 'asc'" class="size-3.5" />
                            <ChevronDown v-else-if="sort?.by === col.key && sort?.dir === 'desc'" class="size-3.5" />
                            <ChevronsUpDown v-else class="size-3.5 opacity-40" />
                        </button>
                        <span v-else>{{ col.label }}</span>
                    </th>
                    <th class="pb-3"></th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in rows"
                    :key="row.id"
                    class="group cursor-pointer border-t border-neutral-100 transition hover:bg-neutral-50/60 focus:bg-neutral-50 focus:outline-none"
                    tabindex="0"
                    @click="openChild(row)"
                    @keydown.enter.prevent="openChild(row)"
                    @keydown.space.prevent="openChild(row)"
                >
                    <td class="py-4 pr-4">
                        <div class="flex items-center gap-3">
                            <Avatar class="size-10 rounded-xl">
                                <AvatarImage v-if="row.photo" :src="row.photo" :alt="row.name" class="object-cover" />
                                <AvatarFallback class="rounded-xl">{{ initials(row.name) }}</AvatarFallback>
                            </Avatar>
                            <span class="font-semibold text-neutral-900">{{ row.name }}</span>
                        </div>
                    </td>
                    <td class="py-4 pr-4"><RiskBadge :level="row.level" /></td>
                    <td class="py-4 pr-4 text-neutral-700">{{ row.age }}</td>
                    <td class="py-4 pr-4 text-neutral-700">{{ row.school }}</td>
                    <td class="py-4 pr-4">
                        <div class="text-neutral-900">{{ row.event }}</div>
                        <div class="text-xs text-neutral-400">{{ row.eventDate }}</div>
                    </td>
                    <td class="py-4 pr-4 text-neutral-700">{{ row.updated }}</td>
                    <td class="py-4 text-right">
                        <span class="inline-flex text-neutral-300 transition group-hover:text-neutral-600">
                            <ChevronRight class="size-5" />
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
