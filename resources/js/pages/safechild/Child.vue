<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronRight,
    ChevronUp,
    Eye,
    FileText,
    GraduationCap,
    HeartHandshake,
    OctagonAlert,
    Shield,
    Sparkles,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import PillTabs from '@/components/safechild/PillTabs.vue';
import RiskBadge from '@/components/safechild/RiskBadge.vue';

interface RiskFactor {
    label: string;
    value: number;
    color: string;
}
interface NotifItem {
    id: number;
    status: 'check_it_out' | 'in_progress' | 'resolved';
    title: string;
    body: string;
    created: string;
    attachments: { name: string; note: string }[];
}
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
    eventHistory: EventItem[];
    riskFactors: RiskFactor[];
    notifications: NotifItem[];
}>();

const badge: Record<string, { cls: string; label: string }> = {
    check_it_out: { cls: 'bg-red-500 text-white', label: 'Check It Out' },
    in_progress: { cls: 'bg-sky-500 text-white', label: 'In Process' },
    resolved: { cls: 'bg-emerald-500 text-white', label: 'Resolved' },
};

const expanded = ref<number | null>(null); // initial state: collapsed
const confirmTarget = ref<NotifItem | null>(null);
const aiSummary = ref('');
const aiKeyFactors = ref<string[]>([]);
const aiDisclaimer = ref('');
const recommendations = ref<string[]>([]);
const aiLoading = ref(true);
const aiError = ref('');
const aiBorderAnimating = ref(true);
const profileCard = ref<HTMLElement | null>(null);
const aiScroll = ref<HTMLElement | null>(null);
const aiScrollTrack = ref<HTMLElement | null>(null);
const topRiskCard = ref<HTMLElement | null>(null);
const eventScroll = ref<HTMLElement | null>(null);
const eventScrollTrack = ref<HTMLElement | null>(null);
const profileHeight = ref<number | null>(null);
const topRiskHeight = ref<number | null>(null);
const isLargeScreen = ref(false);
const scrollThumbTop = ref(0);
const scrollThumbHeight = ref(0);
const eventScrollThumbTop = ref(0);
const eventScrollThumbHeight = ref(0);
let profileResizeObserver: ResizeObserver | null = null;
let aiScrollResizeObserver: ResizeObserver | null = null;
let topRiskResizeObserver: ResizeObserver | null = null;
let eventScrollResizeObserver: ResizeObserver | null = null;

// Border spin duration in CSS (.ai-summary-border-spin). Must stay in sync.
const AI_BORDER_CYCLE_MS = 1600;
// Stamp the moment the spinner starts so we can align the stop with a full cycle.
let aiBorderStartedAt = 0;

function toggle(id: number) {
    expanded.value = expanded.value === id ? null : id;
}
function take(n: NotifItem) {
    router.post(`/notifications/${n.id}/take`, {}, { preserveScroll: true });
}
function resolve() {
    if (!confirmTarget.value) {
        return;
    }

    router.post(
        `/notifications/${confirmTarget.value.id}/resolve`,
        {},
        { preserveScroll: true },
    );
    confirmTarget.value = null;
}

const categoryIcon = {
    Incident: Shield,
    Education: GraduationCap,
    Social: HeartHandshake,
};

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

watch(visibleEvents, () => {
    void nextTick(updateEventScrollThumb);
});

const aiCardStyle = computed(() => {
    if (!isLargeScreen.value || !profileHeight.value) {
        return {};
    }

    return { height: `${profileHeight.value}px` };
});

const aiScrollThumbStyle = computed(() => ({
    height: `${scrollThumbHeight.value}px`,
    transform: `translateY(${scrollThumbTop.value}px)`,
}));

const eventHistoryStyle = computed(() => {
    if (!isLargeScreen.value || !topRiskHeight.value) {
        return {};
    }

    return { height: `${topRiskHeight.value}px` };
});

const eventScrollThumbStyle = computed(() => ({
    height: `${eventScrollThumbHeight.value}px`,
    transform: `translateY(${eventScrollThumbTop.value}px)`,
}));

onMounted(() => {
    updateLayoutSizes();
    window.addEventListener('resize', updateLayoutSizes);

    if (profileCard.value) {
        profileResizeObserver = new ResizeObserver(updateAiCardSize);
        profileResizeObserver.observe(profileCard.value);
    }

    if (aiScroll.value) {
        aiScrollResizeObserver = new ResizeObserver(updateAiScrollThumb);
        aiScrollResizeObserver.observe(aiScroll.value);
        updateAiScrollThumb();
    }

    if (topRiskCard.value) {
        topRiskResizeObserver = new ResizeObserver(updateEventHistorySize);
        topRiskResizeObserver.observe(topRiskCard.value);
    }

    if (eventScroll.value) {
        eventScrollResizeObserver = new ResizeObserver(updateEventScrollThumb);
        eventScrollResizeObserver.observe(eventScroll.value);
    }

    updateEventHistorySize();

    void loadAiSummary();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateLayoutSizes);
    profileResizeObserver?.disconnect();
    aiScrollResizeObserver?.disconnect();
    topRiskResizeObserver?.disconnect();
    eventScrollResizeObserver?.disconnect();
});

function updateAiCardSize() {
    isLargeScreen.value = window.matchMedia('(min-width: 1024px)').matches;
    profileHeight.value = profileCard.value?.offsetHeight ?? null;
    void nextTick(updateAiScrollThumb);
}

function updateLayoutSizes() {
    updateAiCardSize();
    updateEventHistorySize();
}

function updateEventHistorySize() {
    isLargeScreen.value = window.matchMedia('(min-width: 1024px)').matches;
    topRiskHeight.value = topRiskCard.value?.offsetHeight ?? null;
    void nextTick(updateEventScrollThumb);
}

function updateAiScrollThumb() {
    const el = aiScroll.value;
    const track = aiScrollTrack.value;

    if (!el || !track) {
        return;
    }

    const trackHeight = track.clientHeight;
    const maxScroll = Math.max(0, el.scrollHeight - el.clientHeight);
    const minThumbHeight = 36;
    const thumbHeight =
        maxScroll > 0
            ? Math.max(
                  minThumbHeight,
                  (el.clientHeight / el.scrollHeight) * trackHeight,
              )
            : trackHeight;
    const maxThumbTop = Math.max(0, trackHeight - thumbHeight);

    scrollThumbHeight.value = thumbHeight;
    scrollThumbTop.value =
        maxScroll > 0 ? (el.scrollTop / maxScroll) * maxThumbTop : 0;
}

function updateEventScrollThumb() {
    const el = eventScroll.value;
    const track = eventScrollTrack.value;

    if (!el || !track) {
        return;
    }

    const trackHeight = track.clientHeight;
    const maxScroll = Math.max(0, el.scrollHeight - el.clientHeight);
    const minThumbHeight = 36;
    const thumbHeight =
        maxScroll > 0
            ? Math.max(
                  minThumbHeight,
                  (el.clientHeight / el.scrollHeight) * trackHeight,
              )
            : trackHeight;
    const maxThumbTop = Math.max(0, trackHeight - thumbHeight);

    eventScrollThumbHeight.value = thumbHeight;
    eventScrollThumbTop.value =
        maxScroll > 0 ? (el.scrollTop / maxScroll) * maxThumbTop : 0;
}

async function waitForPaint() {
    await nextTick();
    await new Promise<void>((resolve) =>
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => resolve());
        }),
    );
}

function sleep(ms: number) {
    return new Promise<void>((resolve) => window.setTimeout(resolve, ms));
}

async function typewriter(
    text: string,
    setter: (v: string) => void,
    speed = 4,
) {
    for (let i = 0; i <= text.length; i++) {
        setter(text.slice(0, i));

        // Update virtual scrollbar as the text grows.
        if (i % 24 === 0) {
            updateAiScrollThumb();
        }

        await sleep(speed);
    }

    updateAiScrollThumb();
}

async function loadAiSummary() {
    aiLoading.value = true;
    aiError.value = '';
    aiBorderAnimating.value = true;
    aiBorderStartedAt = performance.now();

    try {
        const response = await fetch(`/children/${props.child.id}/ai-summary`, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('AI summary request failed.');
        }

        const data = await response.json();
        const fullSummary = (data.aiSummary ?? '') as string;
        const factors = (data.aiKeyFactors ?? []) as string[];
        const recs = (data.recommendations ?? []) as string[];
        aiDisclaimer.value = data.aiDisclaimer ?? '';

        // Hide the loading skeleton, but keep the border spinner running while
        // the text reveals itself. This matches the requirement literally:
        // the spinner stays alive until every piece of text is on screen.
        aiLoading.value = false;
        aiSummary.value = '';
        aiKeyFactors.value = [];
        recommendations.value = [];
        await waitForPaint();

        await typewriter(fullSummary, (v) => (aiSummary.value = v), 4);

        for (const f of factors) {
            aiKeyFactors.value.push(f);
            await sleep(140);
            updateAiScrollThumb();
        }

        for (const r of recs) {
            recommendations.value.push(r);
            await sleep(140);
            updateAiScrollThumb();
        }
    } catch {
        aiError.value = 'AI summary is temporarily unavailable.';
        aiLoading.value = false;
    } finally {
        await waitForPaint();
        updateAiScrollThumb();

        // Finish the current border cycle cleanly + one full extra rotation.
        const elapsed = performance.now() - aiBorderStartedAt;
        const finishCurrent =
            AI_BORDER_CYCLE_MS - (elapsed % AI_BORDER_CYCLE_MS);
        await sleep(finishCurrent + AI_BORDER_CYCLE_MS);
        aiBorderAnimating.value = false;
    }
}
</script>

<template>
    <Head :title="child.name" />

    <div class="flex flex-wrap items-start justify-between gap-3">
        <h1
            class="text-3xl font-bold tracking-tight text-neutral-900 sm:text-4xl"
        >
            {{ child.name }}
        </h1>
        <nav
            class="mt-1 flex flex-wrap items-center gap-2 text-sm text-neutral-400 sm:mt-3"
        >
            <Link href="/" class="cursor-pointer hover:text-neutral-600"
                >Home</Link
            >
            <ChevronRight class="size-4" />
            <Link href="/children" class="cursor-pointer hover:text-neutral-600"
                >Children</Link
            >
            <ChevronRight class="size-4" />
            <span class="font-medium text-neutral-900">{{ child.name }}</span>
        </nav>
    </div>

    <!-- Tasks & notifications -->
    <section v-if="notifications.length" class="mt-8">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-neutral-900">
                Tasks &amp; notifications
            </h2>
            <Link
                href="/notifications"
                class="inline-flex cursor-pointer items-center gap-1 rounded-full border border-neutral-200 bg-white px-3.5 py-1.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50"
            >
                See all <ChevronRight class="size-4" />
            </Link>
        </div>

        <article
            v-for="n in notifications"
            :key="n.id"
            class="mb-4 rounded-2xl bg-white p-5"
        >
            <div class="flex items-start justify-between">
                <span
                    :class="[
                        'rounded-full px-2.5 py-1 text-xs font-semibold',
                        badge[n.status].cls,
                    ]"
                    >{{ badge[n.status].label }}</span
                >
                <button
                    @click="toggle(n.id)"
                    class="flex cursor-pointer items-center gap-2 text-xs text-neutral-400 hover:text-neutral-700"
                >
                    {{ n.created }}
                    <component
                        :is="expanded === n.id ? ChevronUp : ChevronDown"
                        class="size-4"
                    />
                </button>
            </div>
            <h3 class="mt-3 font-semibold text-neutral-900">{{ n.title }}</h3>

            <div
                class="grid transition-[grid-template-rows,opacity] duration-200 ease-out"
                :class="
                    expanded === n.id
                        ? 'grid-rows-[1fr] opacity-100'
                        : 'pointer-events-none grid-rows-[0fr] opacity-0'
                "
                :aria-hidden="expanded !== n.id"
                :inert="expanded !== n.id"
            >
                <div class="min-h-0 overflow-hidden">
                    <p
                        class="mt-2 text-sm leading-relaxed whitespace-pre-line text-neutral-500"
                    >
                        {{ n.body }}
                    </p>
                    <ul v-if="n.attachments.length" class="mt-4">
                        <li
                            v-for="(a, i) in n.attachments"
                            :key="i"
                            class="flex items-center gap-3 border-b border-neutral-100 py-3 first:pt-0 last:border-b-0 last:pb-0"
                        >
                            <FileText
                                class="size-5 shrink-0 text-neutral-400"
                            />
                            <div class="flex-1">
                                <p class="text-sm font-medium text-neutral-900">
                                    {{ a.name }}
                                </p>
                                <p class="text-xs text-neutral-400">
                                    {{ a.note }}
                                </p>
                            </div>
                            <button
                                class="cursor-pointer text-neutral-400 transition hover:text-neutral-700"
                                title="Preview"
                                aria-label="Preview document"
                            >
                                <Eye class="size-5" />
                            </button>
                        </li>
                    </ul>
                    <div class="mt-4">
                        <button
                            v-if="n.status === 'check_it_out'"
                            @click="take(n)"
                            class="cursor-pointer rounded-3xl bg-[#0C111D] px-5 py-2 text-sm font-semibold text-white transition hover:bg-[#111827]"
                        >
                            Take to work
                        </button>
                        <button
                            v-else-if="n.status === 'in_progress'"
                            @click="confirmTarget = n"
                            class="cursor-pointer rounded-3xl bg-[#0C111D] px-5 py-2 text-sm font-semibold text-white transition hover:bg-[#111827]"
                        >
                            Mark as resolved
                        </button>
                    </div>
                </div>
            </div>
            <p
                v-if="expanded !== n.id"
                class="mt-1 line-clamp-1 text-sm text-neutral-500"
            >
                {{ n.body }}
            </p>
        </article>
    </section>

    <div
        class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]"
    >
        <!-- Profile card -->
        <section ref="profileCard" class="rounded-2xl bg-white p-4 sm:p-6">
            <RiskBadge :level="child.riskLabel" />
            <div class="mt-4 flex flex-col gap-6 sm:flex-row">
                <img
                    :src="child.photo"
                    :alt="child.name"
                    class="h-60 w-full shrink-0 rounded-xl object-cover sm:size-60"
                />
                <dl class="grid flex-1 grid-cols-2 gap-x-8 gap-y-5 text-sm">
                    <div>
                        <dt class="font-semibold text-neutral-900">Age:</dt>
                        <dd class="text-neutral-500">{{ child.age }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-neutral-900">Sex:</dt>
                        <dd class="text-neutral-500">{{ child.sex }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-neutral-900">School:</dt>
                        <dd class="text-neutral-500">{{ child.school }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-neutral-900">Grade:</dt>
                        <dd class="text-neutral-500">{{ child.grade }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-neutral-900">
                            Parent / Guardian:
                        </dt>
                        <dd class="text-neutral-500">{{ child.guardians }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-neutral-900">
                            Contact Number:
                        </dt>
                        <dd class="text-neutral-500">{{ child.contact }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="font-semibold text-neutral-900">Address:</dt>
                        <dd class="text-neutral-500">{{ child.address }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        <!-- AI summary -->
        <div
            class="ai-summary-border-shell rounded-2xl"
            :class="{ 'is-animating': aiBorderAnimating }"
            :style="aiCardStyle"
        >
            <section
                class="ai-summary-card relative flex h-full min-h-0 flex-col overflow-hidden rounded-2xl bg-blue-50/95 p-5 pb-0"
            >
                <Sparkles class="size-6 shrink-0 text-blue-500" />
                <h2 class="mt-2 shrink-0 text-base font-semibold text-blue-600">
                    AI Summary
                </h2>
                <div
                    ref="aiScroll"
                    class="ai-summary-scroll mt-4 min-h-0 flex-1 pr-5"
                    @scroll="updateAiScrollThumb"
                >
                    <p
                        v-if="aiLoading"
                        class="text-sm leading-relaxed text-blue-700"
                    >
                        Generating recommendations...
                    </p>
                    <div v-if="aiLoading" class="mt-4 space-y-2">
                        <span class="block h-3 rounded-full bg-blue-100" />
                        <span
                            class="block h-3 w-5/6 rounded-full bg-blue-100"
                        />
                        <span
                            class="block h-3 w-2/3 rounded-full bg-blue-100"
                        />
                    </div>
                    <p
                        v-else-if="aiError"
                        class="text-sm leading-relaxed text-red-600"
                    >
                        {{ aiError }}
                    </p>
                    <template v-else>
                        <p class="text-sm leading-relaxed text-neutral-600">
                            {{ aiSummary }}
                        </p>
                        <ul v-if="aiKeyFactors.length" class="mt-4 space-y-2">
                            <li
                                v-for="factor in aiKeyFactors"
                                :key="factor"
                                class="text-sm leading-relaxed text-neutral-700"
                            >
                                {{ factor }}
                            </li>
                        </ul>
                        <ul class="mt-4 space-y-2.5">
                            <li
                                v-for="(rec, i) in recommendations"
                                :key="i"
                                class="flex items-start gap-3"
                            >
                                <span
                                    class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-md bg-blue-500 text-xs font-semibold text-white"
                                    >{{ i + 1 }}</span
                                >
                                <span
                                    class="text-sm font-medium text-neutral-800"
                                    >{{ rec }}</span
                                >
                            </li>
                        </ul>
                        <p
                            v-if="aiDisclaimer"
                            class="mt-4 text-xs leading-relaxed text-neutral-500"
                        >
                            {{ aiDisclaimer }}
                        </p>
                    </template>
                </div>
                <span
                    v-if="!aiLoading && !aiError"
                    ref="aiScrollTrack"
                    class="ai-summary-scroll-track"
                    aria-hidden="true"
                >
                    <span
                        class="ai-summary-scroll-thumb"
                        :style="aiScrollThumbStyle"
                    />
                </span>
            </section>
        </div>

        <!-- Event history -->
        <section
            class="relative flex min-h-0 flex-col overflow-hidden rounded-2xl bg-white p-4 sm:p-6"
            :style="eventHistoryStyle"
        >
            <div
                class="mb-5 flex shrink-0 flex-wrap items-center justify-between gap-3"
            >
                <h2 class="text-lg font-semibold text-neutral-900">
                    Event History
                </h2>
                <PillTabs
                    :tabs="
                        periods.map((p) => ({ value: p.id, label: p.label }))
                    "
                    :model-value="period"
                    @update:model-value="(v) => (period = v as typeof period)"
                />
            </div>

            <div
                ref="eventScroll"
                class="event-history-scroll min-h-0 flex-1 pr-5"
                @scroll="updateEventScrollThumb"
            >
                <TransitionGroup
                    tag="ul"
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="opacity-0 translate-y-2"
                    move-class="transition duration-300 ease-out"
                >
                    <li
                        v-for="(ev, i) in visibleEvents"
                        :key="`${ev.date}-${ev.title}-${i}`"
                        class="flex gap-4"
                    >
                        <div class="flex flex-col items-center">
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-500"
                            >
                                <component
                                    :is="categoryIcon[ev.category]"
                                    class="size-4"
                                />
                            </span>
                            <span
                                v-if="i < visibleEvents.length - 1"
                                class="my-1 w-px flex-1 bg-neutral-200"
                            />
                        </div>
                        <div class="flex-1 pb-6">
                            <div class="text-sm font-semibold text-neutral-900">
                                {{ ev.date }}
                            </div>
                            <div class="text-xs text-neutral-400">
                                {{ ev.time }}
                            </div>
                            <div
                                class="mt-2 flex items-start justify-between gap-3"
                            >
                                <p class="font-semibold text-neutral-900">
                                    {{ ev.title }}
                                </p>
                                <span
                                    class="shrink-0 rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-500"
                                    >{{ ev.category }}</span
                                >
                            </div>
                            <p
                                class="mt-1 text-sm leading-relaxed text-neutral-500"
                            >
                                {{ ev.description }}
                            </p>
                        </div>
                    </li>
                </TransitionGroup>
                <p
                    v-if="!visibleEvents.length"
                    class="py-6 text-center text-sm text-neutral-400"
                >
                    No events in this period.
                </p>
            </div>
            <span
                ref="eventScrollTrack"
                class="event-history-scroll-track"
                aria-hidden="true"
            >
                <span
                    class="event-history-scroll-thumb"
                    :style="eventScrollThumbStyle"
                />
            </span>
        </section>

        <!-- Top risk factors -->
        <section
            ref="topRiskCard"
            class="h-fit rounded-2xl bg-white p-4 sm:p-6"
        >
            <h2 class="text-lg font-semibold text-neutral-900">
                Top Risk Factors
            </h2>
            <p class="text-sm text-neutral-400">
                Number of customer based on country
            </p>
            <ul class="mt-6 space-y-5">
                <li v-for="(f, i) in riskFactors" :key="i">
                    <p class="mb-2 text-sm font-semibold text-neutral-900">
                        {{ f.label }}
                    </p>
                    <div class="flex items-center gap-3">
                        <div
                            class="h-2 flex-1 overflow-hidden rounded-full bg-neutral-100"
                        >
                            <div
                                class="h-full rounded-full"
                                :class="f.color"
                                :style="{ width: `${f.value}%` }"
                            />
                        </div>
                        <span
                            class="w-10 shrink-0 text-right text-sm font-semibold text-neutral-900"
                            >{{ f.value }}%</span
                        >
                    </div>
                </li>
            </ul>
        </section>
    </div>

    <!-- Resolve confirmation modal -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="confirmTarget"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                @click.self="confirmTarget = null"
            >
                <div
                    class="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-xl transition duration-200"
                    :class="confirmTarget ? 'scale-100' : 'scale-95'"
                >
                    <span
                        class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-neutral-900 text-white"
                    >
                        <OctagonAlert class="size-6" />
                    </span>
                    <h3 class="mt-5 text-xl font-bold text-neutral-900">
                        Confirm task completion?
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-neutral-500">
                        After confirmation the task moves to the completed list,
                        and the date and responsible worker are saved in the
                        case history.
                    </p>
                    <div class="mt-6 flex items-center justify-center gap-3">
                        <button
                            @click="resolve"
                            class="cursor-pointer rounded-xl bg-neutral-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-neutral-800"
                        >
                            Mark as resolved
                        </button>
                        <button
                            @click="confirmTarget = null"
                            class="cursor-pointer rounded-xl border border-neutral-200 px-6 py-3 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
@property --ai-border-angle {
    syntax: '<angle>';
    inherits: false;
    initial-value: 0deg;
}

.ai-summary-border-shell {
    --ai-border-angle: 0deg;
    position: relative;
    overflow: hidden;
    background: #bfdbfe;
}

.ai-summary-border-shell::before {
    position: absolute;
    inset: 0;
    padding: 1px;
    border-radius: inherit;
    background: conic-gradient(
        from var(--ai-border-angle),
        #2563eb 0deg,
        #93c5fd 95deg,
        #bfdbfe 180deg,
        #60a5fa 265deg,
        #2563eb 360deg
    );
    content: '';
    opacity: 0;
    transition: opacity 240ms ease;
    pointer-events: none;
    mask:
        linear-gradient(#000 0 0) content-box,
        linear-gradient(#000 0 0);
    mask-composite: exclude;
}

.ai-summary-border-shell.is-animating::before {
    animation: ai-summary-border-spin 5s linear infinite;
    opacity: 1;
}

.ai-summary-card {
    position: relative;
    z-index: 1;
}

.ai-summary-scroll {
    overflow-y: auto;
    scrollbar-width: none;
}

.ai-summary-scroll::-webkit-scrollbar {
    display: none;
}

.ai-summary-scroll-track {
    position: absolute;
    top: 5rem;
    right: 0.75rem;
    bottom: 1.25rem;
    width: 4px;
    overflow: hidden;
    border-radius: 999px;
    background: #dbeafe;
}

.ai-summary-scroll-thumb {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    border-radius: inherit;
    background: #60a5fa;
    transition:
        height 160ms ease,
        transform 80ms linear;
}

.event-history-scroll {
    overflow-y: auto;
    scrollbar-width: none;
}

.event-history-scroll::-webkit-scrollbar {
    display: none;
}

.event-history-scroll-track {
    position: absolute;
    top: 5.5rem;
    right: 0.75rem;
    bottom: 1.5rem;
    width: 4px;
    overflow: hidden;
    border-radius: 999px;
    background: #e5e5e5;
}

.event-history-scroll-thumb {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    border-radius: inherit;
    background: #111827;
    transition:
        height 160ms ease,
        transform 80ms linear;
}

@keyframes ai-summary-border-spin {
    to {
        --ai-border-angle: 360deg;
    }
}
</style>
