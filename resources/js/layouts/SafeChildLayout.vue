<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Bell, ChevronDown, ChevronRight, X } from '@lucide/vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { filterPanelOpen } from '@/composables/usePanel';

const page = usePage();
const notif = computed<any>(() => (page.props as any).notif ?? { unread: 0, latest: null });
const unread = computed<number>(() => notif.value.unread ?? 0);

// Toast for the newest unread notification.
const toast = ref<any>(notif.value.latest);
let seenId: number | null = notif.value.latest?.id ?? null;

watch(() => notif.value.latest?.id, (id) => {
    if (id && id !== seenId) {
        toast.value = notif.value.latest;
        seenId = id;
    }
});

// Polling: quietly refresh the shared `notif` prop (near real-time).
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    timer = setInterval(() => router.reload({ only: ['notif'] }), 12000);
});
onUnmounted(() => clearInterval(timer));
</script>

<template>
    <div class="min-h-screen bg-[#eef1f1] text-neutral-900">
        <header
            class="sticky top-0 z-30 bg-white shadow-sm transition-[border-radius] duration-200"
            :class="filterPanelOpen ? 'rounded-b-none' : 'rounded-b-3xl'"
        >
            <div class="flex h-[72px] items-center justify-between gap-3 px-6 sm:px-10">
                <Link href="/" class="flex shrink-0 cursor-pointer items-center">
                    <img src="/img/logo.png" alt="SafeChild" class="h-7 w-auto" />
                </Link>

                <div class="flex shrink-0 items-center gap-3 sm:gap-4">
                    <Link
                        href="/notifications"
                        class="relative flex size-10 cursor-pointer items-center justify-center rounded-full border border-neutral-200 bg-white text-neutral-600 transition hover:bg-neutral-50"
                    >
                        <Bell class="size-5" />
                        <span v-if="unread > 0" class="absolute right-2.5 top-2.5 size-2 rounded-full bg-red-500 ring-2 ring-white" />
                    </Link>
                    <button class="flex cursor-pointer items-center gap-2.5">
                        <Avatar class="size-9">
                            <AvatarImage src="https://i.pravatar.cc/80?img=47" alt="Olena Franko" />
                            <AvatarFallback>OF</AvatarFallback>
                        </Avatar>
                        <span class="hidden text-sm font-medium md:inline">Olena Franko</span>
                        <ChevronDown class="hidden size-4 text-neutral-400 md:inline" />
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-[1320px] px-4 py-8 sm:px-6 sm:py-10">
            <slot />
        </main>

        <!-- Global notification toast (near real-time via polling) -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 translate-y-4"
                leave-active-class="transition duration-200 ease-in" leave-to-class="opacity-0 translate-y-4"
            >
                <div v-if="toast" class="fixed bottom-6 right-6 z-50 w-80 rounded-2xl border border-red-200 bg-red-50 p-4 shadow-xl">
                    <div class="flex items-start justify-between">
                        <span class="rounded-full bg-red-500 px-2.5 py-1 text-xs font-semibold text-white">CHECK IT OUT</span>
                        <button @click="toast = null" class="cursor-pointer text-neutral-400 hover:text-neutral-700"><X class="size-4" /></button>
                    </div>
                    <p class="mt-2 text-sm font-semibold text-neutral-900">{{ toast.title }}</p>
                    <p class="mt-1 text-xs text-neutral-500">New risk factors recorded. Review the case and assess next steps.</p>
                    <Link
                        :href="`/children/${toast.child_id}`"
                        @click="toast = null"
                        class="mt-3 inline-flex cursor-pointer items-center gap-1 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50"
                    >More details <ChevronRight class="size-4" /></Link>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
