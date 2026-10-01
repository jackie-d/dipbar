<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Layout from '../components/Layout.vue';

const form = useForm({ email: '', password: '', remember: false });
</script>

<template>
    <Head title="Log in" />
    <Layout>
        <form class="mx-auto mt-16 flex max-w-sm flex-col gap-3 px-4" @submit.prevent="form.post('/login', { onFinish: () => form.reset('password') })">
            <h1 class="text-2xl font-semibold">Log in</h1>
            <label class="flex flex-col gap-1 text-sm">
                Email
                <input v-model="form.email" type="email" autocomplete="email" required class="input" />
            </label>
            <label class="flex flex-col gap-1 text-sm">
                Password
                <input v-model="form.password" type="password" autocomplete="current-password" required class="input" />
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.remember" type="checkbox" /> Remember me
            </label>
            <p v-if="form.errors.email" class="text-sm text-red-700">{{ form.errors.email }}</p>
            <button :disabled="form.processing" class="btn">Log in</button>
            <p class="text-sm text-stone-500">No account? <Link href="/register" class="text-amber-700 underline">Sign up</Link></p>
        </form>
    </Layout>
</template>
