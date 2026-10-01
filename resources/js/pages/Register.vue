<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Layout from '../components/Layout.vue';

const form = useForm({ name: '', email: '', password: '' });
</script>

<template>
    <Head title="Sign up" />
    <Layout>
        <form class="mx-auto mt-16 flex max-w-sm flex-col gap-3 px-4" @submit.prevent="form.post('/register', { onFinish: () => form.reset('password') })">
            <h1 class="text-2xl font-semibold">Sign up</h1>
            <label class="flex flex-col gap-1 text-sm">
                Name
                <input v-model="form.name" autocomplete="name" required class="input" />
                <span v-if="form.errors.name" class="text-red-700">{{ form.errors.name }}</span>
            </label>
            <label class="flex flex-col gap-1 text-sm">
                Email
                <input v-model="form.email" type="email" autocomplete="email" required class="input" />
                <span v-if="form.errors.email" class="text-red-700">{{ form.errors.email }}</span>
            </label>
            <label class="flex flex-col gap-1 text-sm">
                Password <span class="text-stone-500">(at least 8 characters)</span>
                <input v-model="form.password" type="password" autocomplete="new-password" minlength="8" required class="input" />
                <span v-if="form.errors.password" class="text-red-700">{{ form.errors.password }}</span>
            </label>
            <button :disabled="form.processing" class="btn">Create account</button>
            <p class="text-sm text-stone-500">Already have one? <Link href="/login" class="text-amber-700 underline">Log in</Link></p>
        </form>
    </Layout>
</template>
