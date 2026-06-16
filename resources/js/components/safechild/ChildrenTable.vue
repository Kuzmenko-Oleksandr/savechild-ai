<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
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

defineProps<{ rows: ChildRow[] }>();

const initials = (name: string) =>
    name.split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase();
</script>

<template>
    <div class="-mx-2 overflow-x-auto px-2">
        <table class="w-full min-w-[760px] border-collapse text-sm">
            <thead>
                <tr class="text-left text-xs font-medium text-neutral-400">
                    <th class="pb-3 font-medium">Child</th>
                    <th class="pb-3 font-medium">Risk Level</th>
                    <th class="pb-3 font-medium">Age</th>
                    <th class="pb-3 font-medium">School</th>
                    <th class="pb-3 font-medium">Latest Event</th>
                    <th class="pb-3 font-medium">Last Updated</th>
                    <th class="pb-3"></th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in rows"
                    :key="row.id"
                    class="border-t border-neutral-100 transition hover:bg-neutral-50/60"
                >
                    <td class="py-4 pr-4">
                        <Link :href="`/children/${row.id}`" class="flex cursor-pointer items-center gap-3">
                            <Avatar class="size-10 rounded-xl">
                                <AvatarImage v-if="row.photo" :src="row.photo" :alt="row.name" class="object-cover" />
                                <AvatarFallback class="rounded-xl">{{ initials(row.name) }}</AvatarFallback>
                            </Avatar>
                            <span class="font-semibold text-neutral-900">{{ row.name }}</span>
                        </Link>
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
                        <Link :href="`/children/${row.id}`" class="inline-flex cursor-pointer text-neutral-300 transition hover:text-neutral-600">
                            <ChevronRight class="size-5" />
                        </Link>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
