<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { onClickOutside } from '@vueuse/core';
import { AlertTriangle, Calendar, Check, ChevronRight, Info, OctagonAlert, RefreshCw, Search, Users } from '@lucide/vue';
import ChildrenTable, { type ChildRow } from '@/components/safechild/ChildrenTable.vue';

interface Stat {
    key: string;
    label: string;
    value: string;
    delta?: string;
    trend?: 'up' | 'down';
}
interface TodayItem { title: string; description: string }
interface TrendRange { id: string; range: string; labels: string[]; points: number[] }

const props = defineProps<{
    district: string;
    asOf: string;
    stats: Stat[];
    children: ChildRow[];
    today: TodayItem[];
    trends: TrendRange[];
}>();

const cardStyles: Record<string, { card: string; icon: string; label: string }> = {
    all: { card: 'bg-white', icon: 'bg-neutral-900 text-white', label: 'text-neutral-500' },
    low: { card: 'bg-emerald-50', icon: 'bg-emerald-500 text-white', label: 'text-emerald-600' },
    medium: { card: 'bg-amber-50', icon: 'bg-amber-400 text-white', label: 'text-amber-600' },
    high: { card: 'bg-red-50', icon: 'bg-red-500 text-white', label: 'text-red-500' },
};
const cardIcon: Record<string, unknown> = { all: Users, low: Info, medium: AlertTriangle, high: OctagonAlert };

// ---- High-risk card search (client-side over the shown rows) ----
const search = ref('');
const filteredChildren = computed(() => {
    const q = search.value.trim().toLowerCase();
    return q ? props.children.filter((c) => c.name.toLowerCase().includes(q) || c.school.toLowerCase().includes(q)) : props.children;
});

// ---- Trend range selection ----
const selectedId = ref(props.trends[0]?.id);
const selected = computed(() => props.trends.find((t) => t.id === selectedId.value) ?? props.trends[0]);
const dropdownOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);
onClickOutside(dropdownRef, () => (dropdownOpen.value = false));
function pickRange(id: string) {
    selectedId.value = id;
    dropdownOpen.value = false;
}

// ---- Trend chart geometry ----
const W = 620, H = 260, PAD_L = 36, PAD_R = 8, PAD_T = 16, PAD_B = 28;
const maxY = 1000;
const innerW = W - PAD_L - PAD_R;
const innerH = H - PAD_T - PAD_B;

const coords = computed(() =>
    selected.value.points.map((p, i) => {
        const x = PAD_L + (selected.value.points.length === 1 ? 0 : (innerW * i) / (selected.value.points.length - 1));
        const y = PAD_T + innerH * (1 - Math.min(p, maxY) / maxY);
        return [x, y] as const;
    }),
);
const linePath = computed(() => coords.value.map(([x, y], i) => `${i ? 'L' : 'M'}${x},${y}`).join(' '));
const areaPath = computed(() => {
    if (!coords.value.length) return '';
    const first = coords.value[0], last = coords.value[coords.value.length - 1];
    return `${linePath.value} L${last[0]},${PAD_T + innerH} L${first[0]},${PAD_T + innerH} Z`;
});
const gridLines = [0, 200, 400, 600, 800, 1000];
const yPos = (v: number) => PAD_T + innerH * (1 - v / maxY);
</script>

<template>
    <Head title="Dashboard" />

    <h1 class="text-3xl font-bold tracking-tight text-neutral-900 sm:text-4xl">{{ district }}</h1>
    <p class="mt-1 flex items-center gap-1.5 text-sm text-neutral-400">
        Dashboard as of {{ asOf }}
        <button class="cursor-pointer transition hover:text-neutral-600"><RefreshCw class="size-3.5" /></button>
    </p>

    <!-- Stat cards -->
    <div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="s in stats" :key="s.key" :class="['rounded-2xl p-5', cardStyles[s.key].card]">
            <span :class="['flex size-11 items-center justify-center rounded-xl', cardStyles[s.key].icon]">
                <component :is="cardIcon[s.key]" class="size-5" />
            </span>
            <p :class="['mt-5 text-sm font-medium leading-tight', cardStyles[s.key].label]">{{ s.label }}</p>
            <div class="mt-2 flex items-end justify-between gap-3">
                <span class="text-3xl font-bold text-neutral-900">{{ s.value }}</span>
                <span
                    v-if="s.delta"
                    :class="['rounded-full px-2 py-0.5 text-xs font-semibold', s.trend === 'up' ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white']"
                >
                    {{ s.trend === 'up' ? '↑' : '↓' }} {{ s.delta }}
                </span>
            </div>
        </div>
    </div>

    <!-- Children at high risk -->
    <section class="mt-6 rounded-2xl bg-white p-4 sm:p-6">
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-neutral-900">Children at High Risk</h2>
            <Link href="/children" class="inline-flex cursor-pointer items-center gap-1 rounded-full border border-neutral-200 px-3.5 py-1.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50">
                See all <ChevronRight class="size-4" />
            </Link>
        </div>
<!--        <div class="relative mb-4">-->
<!--            <Search class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-neutral-400" />-->
<!--            <input-->
<!--                v-model="search"-->
<!--                type="text"-->
<!--                placeholder="Search child"-->
<!--                class="h-11 w-full rounded-full border border-neutral-200 bg-neutral-50/60 pl-11 pr-4 text-sm text-neutral-700 placeholder:text-neutral-400 focus:border-neutral-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-neutral-100"-->
<!--            />-->
<!--        </div>-->
        <ChildrenTable :rows="filteredChildren" />
    </section>

    <!-- Today + Trend -->
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)]">
        <section class="rounded-2xl bg-white p-4 sm:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-neutral-900">Today</h2>
                <Link href="/children" class="inline-flex cursor-pointer items-center gap-1 rounded-full border border-neutral-200 px-3.5 py-1.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50">
                    See all <ChevronRight class="size-4" />
                </Link>
            </div>
            <ul class="space-y-5">
                <li v-for="(item, i) in today" :key="i" class="flex gap-3">
                    <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-neutral-500">
                        <Info class="size-4" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-neutral-900">{{ item.title }}</p>
                        <p class="text-sm text-neutral-400">{{ item.description }}</p>
                    </div>
                </li>
            </ul>
        </section>

        <section class="rounded-2xl bg-white p-4 sm:p-6">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-neutral-900">High-Risk Children Trend</h2>
                    <p class="text-sm text-neutral-400">Change in the number of high-risk children over time</p>
                </div>
                <div ref="dropdownRef" class="relative">
                    <button
                        @click="dropdownOpen = !dropdownOpen"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-neutral-200 px-3.5 py-1.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50"
                    >
                        <Calendar class="size-4" /> {{ selected.range }}
                    </button>
                    <div
                        v-if="dropdownOpen"
                        class="absolute right-0 z-20 mt-2 w-52 overflow-hidden rounded-xl border border-neutral-200 bg-white py-1 shadow-lg"
                    >
                        <button
                            v-for="t in trends" :key="t.id"
                            @click="pickRange(t.id)"
                            class="flex w-full cursor-pointer items-center justify-between px-4 py-2 text-sm text-neutral-700 transition hover:bg-neutral-50"
                        >
                            {{ t.range }}
                            <Check v-if="t.id === selectedId" class="size-4 text-neutral-900" />
                        </button>
                    </div>
                </div>
            </div>
            <svg :viewBox="`0 0 ${W} ${H}`" class="w-full" preserveAspectRatio="xMidYMid meet">
                <defs>
                    <linearGradient id="trendFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="rgb(239 68 68)" stop-opacity="0.18" />
                        <stop offset="100%" stop-color="rgb(239 68 68)" stop-opacity="0" />
                    </linearGradient>
                </defs>
                <g>
                    <line
                        v-for="v in gridLines" :key="v"
                        :x1="PAD_L" :x2="W - PAD_R" :y1="yPos(v)" :y2="yPos(v)"
                        stroke="rgb(243 244 246)" stroke-width="1"
                    />
                    <text
                        v-for="v in gridLines" :key="`l${v}`"
                        :x="PAD_L - 8" :y="yPos(v) + 3" text-anchor="end"
                        class="fill-neutral-300 text-[10px]"
                    >{{ v.toLocaleString() }}</text>
                </g>
                <path :key="'area-' + selected.id" :d="areaPath" fill="url(#trendFill)" class="trend-area" />
                <path :key="'line-' + selected.id" :d="linePath" fill="none" stroke="rgb(239 68 68)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" pathLength="1" class="trend-line" />
                <text
                    v-for="(lbl, i) in selected.labels" :key="lbl"
                    :x="PAD_L + (innerW * i) / (selected.labels.length - 1)" :y="H - 6" text-anchor="middle"
                    class="fill-neutral-400 text-[10px]"
                >{{ lbl }}</text>
            </svg>
        </section>
    </div>
</template>

<style scoped>
/* Line "draws" itself in on load and whenever the range changes (path is re-keyed). */
.trend-line {
    stroke-dasharray: 1;
    animation: trend-draw 1s ease forwards;
}
@keyframes trend-draw {
    from { stroke-dashoffset: 1; }
    to { stroke-dashoffset: 0; }
}
.trend-area {
    animation: trend-fade 1.1s ease forwards;
}
@keyframes trend-fade {
    from { opacity: 0; }
    to { opacity: 1; }
}
</style>
