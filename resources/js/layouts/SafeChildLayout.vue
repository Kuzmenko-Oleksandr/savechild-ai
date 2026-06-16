<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Bell, ChevronDown, Search } from '@lucide/vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { searchQuery } from '@/composables/useSearch';

function submitSearch() {
    // Jump to the children list where the query is applied.
    if (window.location.pathname !== '/children') {
        router.visit('/children');
    }
}
</script>

<template>
    <div class="min-h-screen bg-[#eef1f1] text-neutral-900">
        <!-- Top navigation -->
        <header class="sticky top-0 z-30 border-b border-neutral-200/70 bg-[#eef1f1]/90 backdrop-blur">
            <div class="mx-auto flex h-[72px] max-w-[1320px] items-center gap-3 px-4 sm:gap-6 sm:px-6">
                <Link href="/" class="flex shrink-0 cursor-pointer items-center">
                    <img src="/img/logo.png" alt="SafeChild" class="h-7 w-auto" />
                </Link>

                <form class="relative mx-auto w-full max-w-2xl" @submit.prevent="submitSearch">
                    <Search class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-neutral-400" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search child"
                        class="h-11 w-full rounded-full border border-neutral-200 bg-white pl-11 pr-16 text-sm text-neutral-700 placeholder:text-neutral-400 focus:border-neutral-300 focus:outline-none focus:ring-4 focus:ring-neutral-100"
                    />
                    <kbd class="absolute right-3 top-1/2 hidden -translate-y-1/2 rounded-md border border-neutral-200 bg-neutral-50 px-1.5 py-0.5 text-[11px] font-medium text-neutral-400 sm:block">⌘K</kbd>
                </form>

                <div class="flex shrink-0 items-center gap-3 sm:gap-4">
                    <button class="relative flex size-10 cursor-pointer items-center justify-center rounded-full border border-neutral-200 bg-white text-neutral-600 transition hover:bg-neutral-50">
                        <Bell class="size-5" />
                        <span class="absolute right-2.5 top-2.5 size-2 rounded-full bg-red-500 ring-2 ring-white" />
                    </button>
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
    </div>
</template>
