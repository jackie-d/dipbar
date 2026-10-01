<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { api, errorText } from '../api.js';
import Layout from '../components/Layout.vue';

const user = computed(() => usePage().props.auth.user);
const collections = ref([]);
const open = ref(null); // the expanded collection, with members loaded
const newName = ref('');
const memberEmail = ref('');
const error = ref('');

async function load() {
    collections.value = await api('GET', '/collections');
}

async function run(action) {
    error.value = '';
    try {
        await action();
    } catch (e) {
        error.value = errorText(e);
    }
}

const create = () =>
    run(async () => {
        await api('POST', '/collections', { name: newName.value });
        newName.value = '';
        await load();
    });

const toggle = (c) =>
    run(async () => {
        open.value = open.value?.id === c.id ? null : await api('GET', `/collections/${c.id}`);
    });

const addMember = () =>
    run(async () => {
        open.value = await api('POST', `/collections/${open.value.id}/members`, { email: memberEmail.value });
        memberEmail.value = '';
        await load();
    });

const removeMember = (member) =>
    run(async () => {
        await api('DELETE', `/collections/${open.value.id}/members/${member.id}`);
        open.value = member.id === user.value.id ? null : await api('GET', `/collections/${open.value.id}`);
        await load();
    });

const destroy = (c) =>
    run(async () => {
        await api('DELETE', `/collections/${c.id}`);
        open.value = null;
        await load();
    });

onMounted(load);
</script>

<template>
    <Head title="Collections" />
    <Layout>
        <div class="mx-auto h-full max-w-xl overflow-y-auto px-4 py-8">
            <h1 class="text-2xl font-semibold">Collections</h1>
            <p class="mt-1 text-sm text-stone-500">Share notes with a group. Only members can read a collection's notes.</p>

            <form class="mt-6 flex gap-2" @submit.prevent="create">
                <input v-model="newName" required maxlength="255" placeholder="New collection name" class="input flex-1" />
                <button class="btn">Create</button>
            </form>

            <p v-if="error" class="mt-3 text-sm text-red-700">{{ error }}</p>

            <ul class="mt-6 divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white">
                <li v-if="!collections.length" class="p-4 text-sm text-stone-500">No collections yet.</li>
                <li v-for="c in collections" :key="c.id" class="p-4">
                    <button class="flex w-full items-center justify-between text-left" @click="toggle(c)">
                        <span class="font-medium">{{ c.name }}</span>
                        <span class="text-xs text-stone-500">
                            {{ c.owner_id === user.id ? 'owner · ' : '' }}{{ c.members_count }} member{{ c.members_count === 1 ? '' : 's' }}
                        </span>
                    </button>

                    <div v-if="open?.id === c.id" class="mt-3 space-y-3 text-sm">
                        <ul class="space-y-1">
                            <li v-for="m in open.members" :key="m.id" class="flex items-center justify-between">
                                <span>{{ m.name }}<span v-if="m.id === c.owner_id" class="text-stone-500"> (owner)</span></span>
                                <button
                                    v-if="m.id !== c.owner_id && (c.owner_id === user.id || m.id === user.id)"
                                    class="text-xs text-red-700 underline"
                                    @click="removeMember(m)"
                                >
                                    {{ m.id === user.id ? 'Leave' : 'Remove' }}
                                </button>
                            </li>
                        </ul>
                        <template v-if="c.owner_id === user.id">
                            <form class="flex gap-2" @submit.prevent="addMember">
                                <input v-model="memberEmail" type="email" required placeholder="Member's email" class="input flex-1" />
                                <button class="btn">Add</button>
                            </form>
                            <button class="text-xs text-red-700 underline" @click="destroy(c)">Delete collection (its notes become private)</button>
                        </template>
                    </div>
                </li>
            </ul>
        </div>
    </Layout>
</template>
