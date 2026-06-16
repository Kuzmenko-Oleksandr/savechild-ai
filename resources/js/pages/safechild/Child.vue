<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, Sparkles } from '@lucide/vue';

interface RiskFactor { label: string; value: number; color: string }
interface Recommendation { title: string; description: string }

defineProps<{
    child: {
        id: number;
        name: string;
        riskLabel: string;
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
    recommendations: Recommendation[];
    riskFactors: RiskFactor[];
}>();
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
            <span class="inline-flex w-fit items-center rounded-full bg-red-500 px-3 py-1 text-sm font-semibold text-white">
                {{ child.riskLabel }}
            </span>
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
        <section class="h-fit rounded-2xl border border-blue-500/60 bg-white p-4 sm:p-5">
            <div class="rounded-xl bg-blue-50/70 p-4">
                <h2 class="flex items-center gap-2 text-base font-semibold text-neutral-900">
                    <Sparkles class="size-5 text-blue-500" /> AI Summary
                </h2>
                <p class="mt-2 text-sm leading-relaxed text-neutral-600">{{ aiSummary }}</p>
            </div>
            <ul class="mt-3 space-y-3">
                <li v-for="(rec, i) in recommendations" :key="i" class="flex gap-3">
                    <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-500">
                        <Sparkles class="size-4" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-neutral-900">{{ rec.title }}</p>
                        <p class="text-sm text-neutral-400">{{ rec.description }}</p>
                    </div>
                </li>
            </ul>
        </section>

        <!-- Top risk factors -->
        <section class="rounded-2xl bg-white p-4 sm:p-6">
            <h2 class="text-lg font-semibold text-neutral-900">Top Risk Factors</h2>
            <p class="text-sm text-neutral-400">Number of customer based on country</p>
            <ul class="mt-6 space-y-4">
                <li v-for="(f, i) in riskFactors" :key="i" class="flex items-center gap-4 text-sm">
                    <span class="w-32 shrink-0 text-neutral-900 sm:w-36">{{ f.label }}</span>
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-neutral-100">
                        <div class="h-full rounded-full" :class="f.color" :style="{ width: `${f.value}%` }" />
                    </div>
                    <span class="w-10 shrink-0 text-right font-semibold text-neutral-900">{{ f.value }}%</span>
                </li>
            </ul>
        </section>
    </div>
</template>
