<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Calendar,
    Check,
    ChevronRight,
    Info,
    OctagonAlert,
    RefreshCw,
    Users,
} from '@lucide/vue';
import { onClickOutside } from '@vueuse/core';
import { computed, onMounted, ref } from 'vue';

interface Stat {
    key: string;
    label: string;
    value: string;
    delta?: string;
    trend?: 'up' | 'down';
}
interface TodayItem {
    title: string;
    description: string;
}
interface TrendRange {
    id: string;
    range: string;
    labels: string[];
    points: number[];
}
interface Worker {
    name: string;
    role: string;
    percent: number;
    color: string;
    photo?: string | null;
    initials?: string;
}

const avatarColors = [
    'bg-blue-100 text-blue-600',
    'bg-amber-100 text-amber-700',
    'bg-emerald-100 text-emerald-700',
    'bg-purple-100 text-purple-600',
    'bg-rose-100 text-rose-600',
];
function getInitials(name: string) {
    return name
        .split(' ')
        .map((s) => s[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}
interface Analytics {
    total: number;
    checkItOut: number;
    inProgress: number;
    resolved: number;
}

const props = defineProps<{
    district: string;
    asOf: string;
    admin: { name: string; photo?: string };
    stats: Stat[];
    analytics: Analytics;
    efficiency: Worker[];
    today: TodayItem[];
    trends: TrendRange[];
}>();

const cardStyles: Record<
    string,
    { card: string; icon: string; label: string }
> = {
    all: {
        card: 'bg-white',
        icon: 'bg-neutral-900 text-white',
        label: 'text-neutral-500',
    },
    low: {
        card: 'bg-emerald-50',
        icon: 'bg-emerald-500 text-white',
        label: 'text-emerald-600',
    },
    medium: {
        card: 'bg-amber-50',
        icon: 'bg-amber-400 text-white',
        label: 'text-amber-600',
    },
    high: {
        card: 'bg-red-50',
        icon: 'bg-red-500 text-white',
        label: 'text-red-500',
    },
};
const cardIcon: Record<string, unknown> = {
    all: Users,
    low: Info,
    medium: AlertTriangle,
    high: OctagonAlert,
};

// ===== Analytics donut =====
const DONUT_SIZE = 310;
const DONUT_STROKE = 30;
const DONUT_HOVER_STROKE = 40;
const DONUT_CENTER = DONUT_SIZE / 2;
const DONUT_R = (DONUT_SIZE - DONUT_HOVER_STROKE) / 2;
const DONUT_C = 2 * Math.PI * DONUT_R;
const DONUT_GAP = 10;
const DONUT_START_ANGLE = 40.27;
const hoveredDonutKey = ref<string | null>(null);

const donutSegments = computed(() => {
    const total = Math.max(
        1,
        props.analytics.checkItOut +
            props.analytics.inProgress +
            props.analytics.resolved,
    );

    return [
        {
            key: 'in_progress',
            value: props.analytics.inProgress,
            color: '#38bdf8',
            mutedColor: '#bae6fd',
            label: 'In progress',
        },
        {
            key: 'check_it_out',
            value: props.analytics.checkItOut,
            color: '#ef4444',
            mutedColor: '#fecaca',
            label: 'Check it out',
        },
        {
            key: 'resolved',
            value: props.analytics.resolved,
            color: '#22c55e',
            mutedColor: '#bbf7d0',
            label: 'Resolved',
        },
    ].map((s) => ({ ...s, pct: s.value / total }));
});

// Animate donut: each segment's dashoffset goes from full to its target on mount.
const donutAnim = ref(false);
const donutOffsets = computed(() => {
    let acc = 0;

    return donutSegments.value.map((s) => {
        const len = DONUT_C * s.pct;
        const visibleLen = Math.max(0, len - DONUT_GAP);
        const dashOn = visibleLen;
        const dashOff = DONUT_C - visibleLen;
        const dashOffset = donutAnim.value ? 0 : visibleLen;
        // Rotation so segments lay sequentially with visible white gaps.
        const rotation = (acc / DONUT_C) * 360 + DONUT_START_ANGLE;
        acc += len;

        return {
            dashOn,
            dashOff,
            dashOffset,
            rotation,
            color: s.color,
            displayColor:
                hoveredDonutKey.value && hoveredDonutKey.value !== s.key
                    ? s.mutedColor
                    : s.color,
            key: s.key,
            label: s.label,
            pct: s.pct,
        };
    });
});

const activeDonutSegment = computed(() =>
    donutSegments.value.find(
        (segment) => segment.key === hoveredDonutKey.value,
    ),
);

const animatedTotal = ref(0);
const efficiencyAnim = ref<boolean>(false);

function animateNumber(target: number, durationMs = 1200) {
    const start = performance.now();
    const startVal = animatedTotal.value;
    function step(now: number) {
        const t = Math.min(1, (now - start) / durationMs);
        const eased = 1 - Math.pow(1 - t, 3);
        animatedTotal.value = Math.round(
            startVal + (target - startVal) * eased,
        );

        if (t < 1) {
            requestAnimationFrame(step);
        }
    }
    requestAnimationFrame(step);
}

onMounted(() => {
    // Kick off donut draw + count-up on next paint
    requestAnimationFrame(() => {
        donutAnim.value = true;
        animateNumber(props.analytics.total);
    });
    // Staggered progress bars
    setTimeout(() => (efficiencyAnim.value = true), 250);
});

// ===== Trend range selection =====
const selectedId = ref(props.trends[0]?.id);
const selected = computed(
    () =>
        props.trends.find((t) => t.id === selectedId.value) ?? props.trends[0],
);
const dropdownOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);
onClickOutside(dropdownRef, () => (dropdownOpen.value = false));
function pickRange(id: string) {
    selectedId.value = id;
    dropdownOpen.value = false;
}

const W = 620,
    H = 260,
    PAD_L = 36,
    PAD_R = 8,
    PAD_T = 16,
    PAD_B = 28;
const maxY = 1000;
const innerW = W - PAD_L - PAD_R;
const innerH = H - PAD_T - PAD_B;
const coords = computed(() =>
    selected.value.points.map((p, i) => {
        const x =
            PAD_L +
            (selected.value.points.length === 1
                ? 0
                : (innerW * i) / (selected.value.points.length - 1));
        const y = PAD_T + innerH * (1 - Math.min(p, maxY) / maxY);

        return [x, y] as const;
    }),
);
const linePath = computed(() =>
    coords.value.map(([x, y], i) => `${i ? 'L' : 'M'}${x},${y}`).join(' '),
);
const areaPath = computed(() => {
    if (!coords.value.length) {
        return '';
    }

    const first = coords.value[0],
        last = coords.value[coords.value.length - 1];

    return `${linePath.value} L${last[0]},${PAD_T + innerH} L${first[0]},${PAD_T + innerH} Z`;
});
const gridLines = [0, 200, 400, 600, 800, 1000];
const yPos = (v: number) => PAD_T + innerH * (1 - v / maxY);
</script>

<template>
    <Head title="Main admin" />

    <h1 class="text-3xl font-bold tracking-tight text-neutral-900 sm:text-4xl">
        {{ district }}
    </h1>
    <p class="mt-1 flex items-center gap-1.5 text-sm text-neutral-400">
        Dashboard as of {{ asOf }}
        <button class="cursor-pointer transition hover:text-neutral-600">
            <RefreshCw class="size-3.5" />
        </button>
    </p>

    <!-- Stat cards -->
    <div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div
            v-for="s in stats"
            :key="s.key"
            :class="['rounded-2xl p-5', cardStyles[s.key].card]"
        >
            <span
                :class="[
                    'flex size-11 items-center justify-center rounded-xl',
                    cardStyles[s.key].icon,
                ]"
            >
                <component :is="cardIcon[s.key]" class="size-5" />
            </span>
            <p
                :class="[
                    'mt-5 text-sm leading-tight font-medium',
                    cardStyles[s.key].label,
                ]"
            >
                {{ s.label }}
            </p>
            <div class="mt-2 flex items-end justify-between gap-3">
                <span class="text-3xl font-bold text-neutral-900">{{
                    s.value
                }}</span>
                <span
                    v-if="s.delta"
                    :class="[
                        'rounded-full px-2 py-0.5 text-xs font-semibold',
                        s.trend === 'up'
                            ? 'bg-emerald-500 text-white'
                            : 'bg-red-500 text-white',
                    ]"
                >
                    {{ s.trend === 'up' ? '↑' : '↓' }} {{ s.delta }}
                </span>
            </div>
        </div>
    </div>

    <!-- Analytics + Case closing efficiency -->
    <div
        class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)]"
    >
        <!-- Analytics donut -->
        <section class="rounded-2xl bg-white p-4 sm:p-6">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-neutral-900">
                    Analytics
                </h2>
                <button
                    class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-neutral-200 px-3.5 py-1.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50"
                >
                    <Calendar class="size-4" /> 05 Feb - 06 March
                </button>
            </div>

            <div class="flex flex-col items-center">
                <div class="relative mt-7">
                    <svg
                        :viewBox="`0 0 ${DONUT_SIZE} ${DONUT_SIZE}`"
                        class="size-[310px]"
                    >
                        <!-- Animated segments -->
                        <circle
                            v-for="(seg, i) in donutOffsets"
                            :key="i"
                            :cx="DONUT_CENTER"
                            :cy="DONUT_CENTER"
                            :r="DONUT_R"
                            fill="none"
                            :stroke="seg.displayColor"
                            :stroke-width="
                                hoveredDonutKey === seg.key
                                    ? DONUT_HOVER_STROKE
                                    : DONUT_STROKE
                            "
                            stroke-linecap="butt"
                            :stroke-dasharray="`${seg.dashOn} ${seg.dashOff}`"
                            :stroke-dashoffset="seg.dashOffset"
                            :transform="`rotate(${seg.rotation} ${DONUT_CENTER} ${DONUT_CENTER})`"
                            class="donut-seg"
                            tabindex="0"
                            @mouseenter="hoveredDonutKey = seg.key"
                            @mouseleave="hoveredDonutKey = null"
                            @focus="hoveredDonutKey = seg.key"
                            @blur="hoveredDonutKey = null"
                        />
                    </svg>
                    <div
                        class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center"
                    >
                        <span class="text-3xl font-bold text-neutral-950">
                            {{
                                activeDonutSegment
                                    ? `${Math.round(activeDonutSegment.pct * 100)}%`
                                    : animatedTotal.toLocaleString()
                            }}
                        </span>
                        <span
                            class="mt-1 text-center text-base font-medium text-neutral-500"
                            >{{
                                activeDonutSegment
                                    ? activeDonutSegment.label
                                    : 'Cases'
                            }}</span
                        >
                    </div>
                </div>

                <ul
                    class="mt-8 flex w-full flex-wrap items-center justify-center gap-x-3 gap-y-3 text-base font-medium text-[#44445f]"
                >
                    <li class="flex items-center gap-2">
                        <span class="size-5 rounded-md bg-red-500" /> Check it
                        out
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="size-5 rounded-md bg-sky-400" /> In
                        progress
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="size-5 rounded-md bg-emerald-500" />
                        Resolved
                    </li>
                </ul>
            </div>
        </section>

        <!-- Case closing efficiency -->
        <section class="rounded-2xl bg-white p-4 sm:p-6">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-neutral-900">
                    Case closing efficiency
                </h2>
                <Link
                    href="/children"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-full border border-neutral-200 px-3.5 py-1.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50"
                >
                    See all <ChevronRight class="size-4" />
                </Link>
            </div>

            <ul class="space-y-5">
                <li
                    v-for="(w, i) in efficiency"
                    :key="w.name"
                    class="flex items-center gap-4"
                >
                    <img
                        v-if="w.photo"
                        :src="w.photo"
                        :alt="w.name"
                        class="size-12 shrink-0 rounded-xl object-cover"
                    />
                    <span
                        v-else
                        :class="[
                            'flex size-12 shrink-0 items-center justify-center rounded-xl text-sm font-semibold',
                            avatarColors[i % avatarColors.length],
                        ]"
                    >
                        {{ w.initials || getInitials(w.name) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-neutral-900">
                            {{ w.name }}
                        </p>
                        <p class="text-xs text-neutral-400">{{ w.role }}</p>
                        <div class="mt-2 flex items-center gap-3">
                            <div
                                class="h-2 flex-1 overflow-hidden rounded-full bg-neutral-100"
                            >
                                <div
                                    class="h-full rounded-full transition-[width] duration-1000 ease-out"
                                    :class="w.color"
                                    :style="{
                                        width: efficiencyAnim
                                            ? `${w.percent}%`
                                            : '0%',
                                        transitionDelay: `${i * 120}ms`,
                                    }"
                                />
                            </div>
                            <span
                                class="w-10 shrink-0 text-right text-sm font-semibold text-neutral-900"
                                >{{ w.percent }}%</span
                            >
                        </div>
                    </div>
                </li>
            </ul>
        </section>
    </div>

    <!-- Today + Trend -->
    <div
        class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)]"
    >
        <section class="rounded-2xl bg-white p-4 sm:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-neutral-900">Today</h2>
                <Link
                    href="/children"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-full border border-neutral-200 px-3.5 py-1.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50"
                >
                    See all <ChevronRight class="size-4" />
                </Link>
            </div>
            <ul class="space-y-5">
                <li v-for="(item, i) in today" :key="i" class="flex gap-3">
                    <span
                        class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-neutral-500"
                    >
                        <Info class="size-4" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-neutral-900">
                            {{ item.title }}
                        </p>
                        <p class="text-sm text-neutral-400">
                            {{ item.description }}
                        </p>
                    </div>
                </li>
            </ul>
        </section>

        <section class="rounded-2xl bg-white p-4 sm:p-6">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-neutral-900">
                        High-Risk Children Trend
                    </h2>
                    <p class="text-sm text-neutral-400">
                        Change in the number of high-risk children over time
                    </p>
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
                            v-for="t in trends"
                            :key="t.id"
                            @click="pickRange(t.id)"
                            class="flex w-full cursor-pointer items-center justify-between px-4 py-2 text-sm text-neutral-700 transition hover:bg-neutral-50"
                        >
                            {{ t.range }}
                            <Check
                                v-if="t.id === selectedId"
                                class="size-4 text-neutral-900"
                            />
                        </button>
                    </div>
                </div>
            </div>
            <svg
                :viewBox="`0 0 ${W} ${H}`"
                class="w-full"
                preserveAspectRatio="xMidYMid meet"
            >
                <defs>
                    <linearGradient
                        id="trendFillAdmin"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop
                            offset="0%"
                            stop-color="rgb(239 68 68)"
                            stop-opacity="0.18"
                        />
                        <stop
                            offset="100%"
                            stop-color="rgb(239 68 68)"
                            stop-opacity="0"
                        />
                    </linearGradient>
                </defs>
                <g>
                    <line
                        v-for="v in gridLines"
                        :key="v"
                        :x1="PAD_L"
                        :x2="W - PAD_R"
                        :y1="yPos(v)"
                        :y2="yPos(v)"
                        stroke="rgb(243 244 246)"
                        stroke-width="1"
                    />
                    <text
                        v-for="v in gridLines"
                        :key="`l${v}`"
                        :x="PAD_L - 8"
                        :y="yPos(v) + 3"
                        text-anchor="end"
                        class="fill-neutral-300 text-[10px]"
                    >
                        {{ v.toLocaleString() }}
                    </text>
                </g>
                <path
                    :key="'area-' + selected.id"
                    :d="areaPath"
                    fill="url(#trendFillAdmin)"
                    class="trend-area"
                />
                <path
                    :key="'line-' + selected.id"
                    :d="linePath"
                    fill="none"
                    stroke="rgb(239 68 68)"
                    stroke-width="2"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                    pathLength="1"
                    class="trend-line"
                />
                <text
                    v-for="(lbl, i) in selected.labels"
                    :key="lbl"
                    :x="PAD_L + (innerW * i) / (selected.labels.length - 1)"
                    :y="H - 6"
                    text-anchor="middle"
                    class="fill-neutral-400 text-[10px]"
                >
                    {{ lbl }}
                </text>
            </svg>
        </section>
    </div>
</template>

<style scoped>
.donut-seg {
    cursor: pointer;
    outline: none;
    transition:
        stroke-dashoffset 1.1s cubic-bezier(0.22, 1, 0.36, 1),
        stroke 160ms ease,
        stroke-width 180ms ease;
}
.trend-line {
    stroke-dasharray: 1;
    animation: trend-draw 1s ease forwards;
}
@keyframes trend-draw {
    from {
        stroke-dashoffset: 1;
    }
    to {
        stroke-dashoffset: 0;
    }
}
.trend-area {
    animation: trend-fade 1.1s ease forwards;
}
@keyframes trend-fade {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
</style>
