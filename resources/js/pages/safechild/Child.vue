<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ChevronRight, GraduationCap, HeartHandshake, Shield, Sparkles } from '@lucide/vue';
import RiskBadge from '@/components/safechild/RiskBadge.vue';

interface RiskFactor { label: string; value: number; color: string }
interface EventItem {
    date: string;
    time: string;
    title: string;
    category: 'Incident' | 'Education' | 'Social';
    months: number;
    description: string;
}

const props = defineProps<{
    child: {
        id: number;
        name: string;
        riskLabel: 'High' | 'Medium' | 'Low';
        photo?: string;
        age: string;
        sex: string;
        school: string;
        grade: string;
        guardians: string;
        contact: string;
        address: string;
    };
    aiSummary: string;
    recommendations: string[];
    eventHistory: EventItem[];
    riskFactors: RiskFactor[];
}>();

const categoryIcon = { Incident: Shield, Education: GraduationCap, Social: HeartHandshake };

const periods = [
    { id: '6', label: '6 mo.', max: 6 },
    { id: '12', label: '12 mo.', max: 12 },
    { id: 'all', label: 'All', max: Infinity },
] as const;
const period = ref<(typeof periods)[number]['id']>('6');

const visibleEvents = computed(() => {
    const max = periods.find((p) => p.id === period.value)!.max;
    return props.eventHistory.filter((e) => e.months <= max);
});
</script>

<template>
    <Head :title="child.name" />

    <div class="flex flex-wrap items-start justify-between gap-3">
        <h1 class="text-3xl font-bold tracking-tight text-neutral-900 sm:text-4xl">{{ child.name }}</h1>
        <nav class="mt-1 flex flex-wrap items-center gap-2 text-sm text-neutral-400 sm:mt-3">
            <Link href="/" class="cursor-pointer hover:text-neutral-600">Home</Link>
            <ChevronRight class="size-4" />
            <Link href="/children" class="cursor-pointer hover:text-neutral-600">Children</Link>
            <ChevronRight class="size-4" />
            <span class="font-medium text-neutral-900">{{ child.name }}</span>
        </nav>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">
        <!-- Profile card -->
        <section class="rounded-2xl bg-white p-4 sm:p-6">
            <RiskBadge :level="child.riskLabel" />
            <div class="mt-4 flex flex-col gap-6 sm:flex-row">
                <img
                    :src="child.photo"
                    :alt="child.name"
                    class="h-60 w-full shrink-0 rounded-xl object-cover sm:size-52"
                />
                <dl class="grid flex-1 grid-cols-2 gap-x-8 gap-y-5 text-sm">
                    <div><dt class="font-semibold text-neutral-900">Age:</dt><dd class="text-neutral-500">{{ child.age }}</dd></div>
                    <div><dt class="font-semibold text-neutral-900">Sex:</dt><dd class="text-neutral-500">{{ child.sex }}</dd></div>
                    <div><dt class="font-semibold text-neutral-900">School:</dt><dd class="text-neutral-500">{{ child.school }}</dd></div>
                    <div><dt class="font-semibold text-neutral-900">Grade:</dt><dd class="text-neutral-500">{{ child.grade }}</dd></div>
                    <div><dt class="font-semibold text-neutral-900">Parent / Guardian:</dt><dd class="text-neutral-500">{{ child.guardians }}</dd></div>
                    <div><dt class="font-semibold text-neutral-900">Contact Number:</dt><dd class="text-neutral-500">{{ child.contact }}</dd></div>
                    <div class="col-span-2"><dt class="font-semibold text-neutral-900">Address:</dt><dd class="text-neutral-500">{{ child.address }}</dd></div>
                </dl>
            </div>
        </section>

        <!-- AI summary -->
        <section class="h-fit rounded-2xl bg-blue-50/70 p-5">
            <Sparkles class="size-6 text-blue-500" />
            <h2 class="mt-2 text-base font-semibold text-blue-600">AI Summary</h2>
            <p class="mt-1.5 text-sm leading-relaxed text-neutral-600">{{ aiSummary }}</p>
            <ul class="mt-4 space-y-2.5">
                <li v-for="(rec, i) in recommendations" :key="i" class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-md bg-blue-500 text-xs font-semibold text-white">{{ i + 1 }}</span>
                    <span class="text-sm font-medium text-neutral-800">{{ rec }}</span>
                </li>
            </ul>
        </section>

        <!-- Event history -->
        <section class="rounded-2xl bg-white p-4 sm:p-6">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-neutral-900">Event History</h2>
                <div class="inline-flex items-center gap-1 rounded-full bg-neutral-100 p-1">
                    <button
                        v-for="p in periods" :key="p.id"
                        @click="period = p.id"
                        :class="['cursor-pointer rounded-full px-3.5 py-1 text-sm font-medium transition', period === p.id ? 'bg-neutral-900 text-white' : 'text-neutral-500 hover:text-neutral-800']"
                    >{{ p.label }}</button>
                </div>
            </div>

            <ul>
                <li v-for="(ev, i) in visibleEvents" :key="i" class="flex gap-4">
                    <div class="flex flex-col items-center">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-500">
                            <component :is="categoryIcon[ev.category]" class="size-4" />
                        </span>
                        <span v-if="i < visibleEvents.length - 1" class="my-1 w-px flex-1 bg-neutral-200" />
                    </div>
                    <div class="flex-1 pb-6">
                        <div class="text-sm font-semibold text-neutral-900">{{ ev.date }}</div>
                        <div class="text-xs text-neutral-400">{{ ev.time }}</div>
                        <div class="mt-2 flex items-start justify-between gap-3">
                            <p class="font-semibold text-neutral-900">{{ ev.title }}</p>
                            <span class="shrink-0 rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-500">{{ ev.category }}</span>
                        </div>
                        <p class="mt-1 text-sm leading-relaxed text-neutral-500">{{ ev.description }}</p>
                    </div>
                </li>
            </ul>
            <p v-if="!visibleEvents.length" class="py-6 text-center text-sm text-neutral-400">No events in this period.</p>
        </section>

        <!-- Top risk factors -->
        <section class="h-fit rounded-2xl bg-white p-4 sm:p-6">
            <h2 class="text-lg font-semibold text-neutral-900">Top Risk Factors</h2>
            <p class="text-sm text-neutral-400">Number of customer based on country</p>
            <ul class="mt-6 space-y-5">
                <li v-for="(f, i) in riskFactors" :key="i">
                    <p class="mb-2 text-sm font-semibold text-neutral-900">{{ f.label }}</p>
                    <div class="flex items-center gap-3">
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-neutral-100">
                            <div class="h-full rounded-full" :class="f.color" :style="{ width: `${f.value}%` }" />
                        </div>
                        <span class="w-10 shrink-0 text-right text-sm font-semibold text-neutral-900">{{ f.value }}%</span>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
