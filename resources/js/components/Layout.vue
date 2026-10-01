<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const user = computed(() => usePage().props.auth.user);
</script>

<template>
    <div class="flex h-dvh flex-col">
        <header class="flex items-center gap-4 border-b border-stone-200 bg-white px-4 py-2 text-sm">
            <Link href="/" class="text-lg font-semibold tracking-tight text-amber-700">dipbar</Link>
            <nav class="flex flex-1 items-center gap-4">
                <Link href="/" class="hover:text-amber-700">Map</Link>
                <Link v-if="user" href="/collections" class="hover:text-amber-700">Collections</Link>
            </nav>
            <template v-if="user">
                <span class="hidden text-stone-500 sm:inline">{{ user.name }}</span>
                <Link href="/logout" method="post" as="button" class="hover:text-amber-700">Log out</Link>
            </template>
            <template v-else>
                <Link href="/login" class="hover:text-amber-700">Log in</Link>
                <Link href="/register" class="rounded bg-amber-700 px-3 py-1 text-white hover:bg-amber-800">Sign up</Link>
            </template>
        </header>
        <main class="relative min-h-0 flex-1">
            <slot />
        </main>
    </div>
</template>
