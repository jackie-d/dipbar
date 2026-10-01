<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { api, errorText } from '../api.js';
import Layout from '../components/Layout.vue';

const COLORS = { public: '#15803d', collection: '#b45309', private: '#57534e' };
const user = computed(() => usePage().props.auth.user);

const mapEl = ref(null);
const collections = ref([]);
const draft = reactive({ open: false, lat: 0, lng: 0, body: '', visibility: 'private', collection_id: null, error: '', saving: false });
const hint = ref('');

let map, notesLayer, draftMarker, loadTimer;

function noteMarker(note) {
    // Build the popup with textContent so user-written text is never parsed as HTML.
    const popup = document.createElement('div');
    const body = document.createElement('p');
    body.className = 'whitespace-pre-wrap text-sm';
    body.textContent = note.body;
    const meta = document.createElement('p');
    meta.className = 'mt-1 text-xs text-stone-500';
    meta.textContent = `${note.author?.name ?? ''} · ${note.visibility} · ${new Date(note.created_at).toLocaleDateString()}`;
    popup.append(body, meta);

    if (user.value && note.author?.id === user.value.id) {
        const del = document.createElement('button');
        del.className = 'mt-2 text-xs text-red-700 underline';
        del.textContent = 'Delete';
        del.onclick = async () => {
            await api('DELETE', `/notes/${note.id}`);
            loadNotes();
        };
        popup.append(del);
    }

    return L.circleMarker([note.lat, note.lng], {
        radius: 8,
        color: '#fff',
        weight: 2,
        fillColor: COLORS[note.visibility],
        fillOpacity: 0.9,
    }).bindPopup(popup);
}

async function loadNotes() {
    const center = map.getCenter();
    const radius = Math.min(50000, Math.ceil(center.distanceTo(map.getBounds().getNorthEast())));
    const { data } = await api('GET', `/notes?lat=${center.lat.toFixed(6)}&lng=${center.lng.toFixed(6)}&radius=${radius}&limit=100`);

    notesLayer.clearLayers();
    data.forEach((note) => notesLayer.addLayer(noteMarker(note)));
}

function onMapClick(e) {
    if (!user.value) {
        hint.value = 'Log in to leave a note here.';
        return;
    }
    Object.assign(draft, { open: true, lat: e.latlng.lat, lng: e.latlng.lng, error: '' });
    draftMarker.setLatLng(e.latlng).addTo(map);
}

function closeDraft() {
    draft.open = false;
    draftMarker.remove();
}

async function saveNote() {
    draft.saving = true;
    draft.error = '';
    try {
        await api('POST', '/notes', {
            body: draft.body,
            lat: Number(draft.lat.toFixed(7)),
            lng: Number(draft.lng.toFixed(7)),
            visibility: draft.visibility,
            collection_id: draft.visibility === 'collection' ? draft.collection_id : null,
        });
        draft.body = '';
        closeDraft();
        loadNotes();
    } catch (error) {
        draft.error = errorText(error);
    } finally {
        draft.saving = false;
    }
}

watch(hint, (text) => text && setTimeout(() => (hint.value = ''), 3000));

onMounted(async () => {
    // Start on Broadway in downtown Los Angeles (Historic Theatre District).
    map = L.map(mapEl.value).setView([34.045, -118.253], 16);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        // OSM tile servers reject requests without a Referer; send our origin even if the page policy strips it.
        referrerPolicy: 'strict-origin-when-cross-origin',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    notesLayer = L.layerGroup().addTo(map);
    draftMarker = L.circleMarker([0, 0], { radius: 9, color: '#b45309', weight: 3, fillOpacity: 0.2, dashArray: '3' });

    map.on('click', onMapClick);
    map.on('moveend', () => {
        clearTimeout(loadTimer);
        loadTimer = setTimeout(loadNotes, 250);
    });
    loadNotes();

    navigator.geolocation?.getCurrentPosition((pos) => map.setView([pos.coords.latitude, pos.coords.longitude], 16));

    if (user.value) collections.value = await api('GET', '/collections');
});

onBeforeUnmount(() => {
    clearTimeout(loadTimer);
    map?.remove();
});
</script>

<template>
    <Head title="Map" />
    <Layout>
        <div ref="mapEl" class="absolute inset-0 z-0"></div>

        <div class="pointer-events-none absolute top-3 left-1/2 z-[1000] -translate-x-1/2">
            <p v-if="hint" class="pointer-events-auto rounded bg-stone-900/85 px-3 py-1.5 text-sm text-white">
                {{ hint }} <Link v-if="!user" href="/login" class="underline">Log in</Link>
            </p>
        </div>

        <ul class="absolute bottom-6 left-3 z-[1000] space-y-1 rounded bg-white/90 px-3 py-2 text-xs shadow">
            <li v-for="(color, name) in COLORS" :key="name" class="flex items-center gap-2">
                <span class="inline-block size-2.5 rounded-full" :style="{ background: color }"></span>{{ name }}
            </li>
        </ul>

        <form
            v-if="draft.open"
            class="absolute top-3 right-3 z-[1000] flex w-80 max-w-[calc(100%-1.5rem)] flex-col gap-2 rounded-lg bg-white p-4 shadow-lg"
            @submit.prevent="saveNote"
        >
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">New note</h2>
                <button type="button" class="text-stone-500 hover:text-stone-900" @click="closeDraft">✕</button>
            </div>
            <p class="text-xs text-stone-500">{{ draft.lat.toFixed(5) }}, {{ draft.lng.toFixed(5) }}</p>
            <textarea v-model="draft.body" rows="4" maxlength="2000" required placeholder="A line, a verse, a scribble…" class="input"></textarea>
            <select v-model="draft.visibility" class="input">
                <option value="private">Private (only me)</option>
                <option value="collection" :disabled="!collections.length">Collection{{ collections.length ? '' : ' (create one first)' }}</option>
                <option value="public">Public</option>
            </select>
            <select v-if="draft.visibility === 'collection'" v-model="draft.collection_id" required class="input">
                <option :value="null" disabled>Choose a collection</option>
                <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <p v-if="draft.error" class="text-sm text-red-700">{{ draft.error }}</p>
            <button :disabled="draft.saving" class="btn">Leave note</button>
        </form>
    </Layout>
</template>
