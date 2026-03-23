<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    payload: {
        type: Object,
        default: () => ({}),
    },
});

const rows = ref([]);
const search = ref('');
const filter = ref('all');
const notice = ref(props.payload.notice ?? '');
const page = ref(props.payload.initialPage ?? 1);
const perPage = ref(props.payload.perPage ?? 10);
const meta = ref({ page: 1, perPage: perPage.value, total: 0, totalPages: 1 });
const serverStats = ref({ total: 0, latest: 0, st: 0, gd: 0 });
const isLoading = ref(false);
const apiError = ref('');
let searchTimer = null;

const stats = computed(() => serverStats.value);
const filteredRows = computed(() => rows.value);
const rangeStart = computed(() => filteredRows.value.length ? ((meta.value.page - 1) * meta.value.perPage) + 1 : 0);
const rangeEnd = computed(() => Math.min(meta.value.page * meta.value.perPage, meta.value.total));

async function fetchRows() {
    isLoading.value = true;
    apiError.value = '';

    const params = new URLSearchParams({
        page: String(page.value),
        perPage: String(perPage.value),
        q: search.value.trim(),
        filter: filter.value,
    });

    try {
        const response = await fetch(`${props.payload.apiBase}?${params.toString()}`);
        const data = await response.json();

        if (!response.ok) {
            apiError.value = data.message || 'Failed to load software versions.';
            return;
        }

        rows.value = data.rows ?? [];
        meta.value = data.meta ?? meta.value;
        serverStats.value = data.stats ?? serverStats.value;
    } catch (error) {
        apiError.value = 'Failed to load software versions.';
    } finally {
        isLoading.value = false;
    }
}

async function deleteRow(id) {
    if (!window.confirm('Delete this software version?')) {
        return;
    }

    const response = await fetch(`${props.payload.apiBase}/${encodeURIComponent(id)}`, { method: 'DELETE' });
    const data = await response.json();

    if (!response.ok || !data.success) {
        window.alert(data.message || 'Delete failed.');
        return;
    }

    notice.value = 'Deleted software version.';
    await fetchRows();
}

async function copyRow(row) {
    await navigator.clipboard.writeText(JSON.stringify({
        name: row.name,
        system_version: row.system_version,
        system_version_alt: row.system_version_alt,
        link: row.link,
        st: row.st,
        gd: row.gd,
        latest: row.latest,
    }, null, 2));
}

watch(filter, async () => {
    page.value = 1;
    await fetchRows();
});

watch(perPage, async () => {
    page.value = 1;
    await fetchRows();
});

watch(page, async () => {
    await fetchRows();
});

watch(search, () => {
    page.value = 1;
    if (searchTimer) {
        clearTimeout(searchTimer);
    }
    searchTimer = window.setTimeout(fetchRows, 250);
});

onMounted(fetchRows);
</script>

<template>
    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="grid gap-5 rounded-[2rem] border border-white/70 bg-white/80 p-6 shadow-[0_20px_60px_rgba(15,23,42,0.12)] backdrop-blur md:grid-cols-[1.2fr_0.8fr] md:p-8">
            <div>
                <span class="inline-flex rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">
                    Admin Panel
                </span>
                <h1 class="mt-4 text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">Software version control, without touching code.</h1>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                    Search, inspect, and maintain the firmware map that powers the customer download checker.
                </p>
            </div>
            <div class="flex flex-wrap items-start justify-start gap-3 md:justify-end">
                <a class="inline-flex items-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800" :href="payload.createUrl">Add software version</a>
                <a class="inline-flex items-center rounded-2xl bg-white px-5 py-3 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 transition hover:bg-slate-50" :href="payload.customerPageUrl" target="_blank" rel="noreferrer">Open customer page</a>
                <form method="post" :action="payload.logoutUrl">
                    <button class="inline-flex items-center rounded-2xl bg-rose-50 px-5 py-3 text-sm font-semibold text-rose-700 ring-1 ring-rose-200 transition hover:bg-rose-100" type="submit">Log out</button>
                </form>
            </div>
        </section>

        <section class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-[1.75rem] border border-white/70 bg-white/80 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.08)] backdrop-blur">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Total entries</div>
                <div class="mt-3 text-4xl font-semibold text-slate-900">{{ stats.total }}</div>
            </div>
            <div class="rounded-[1.75rem] border border-white/70 bg-white/80 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.08)] backdrop-blur">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Latest markers</div>
                <div class="mt-3 text-4xl font-semibold text-slate-900">{{ stats.latest }}</div>
            </div>
            <div class="rounded-[1.75rem] border border-white/70 bg-white/80 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.08)] backdrop-blur">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">ST links</div>
                <div class="mt-3 text-4xl font-semibold text-slate-900">{{ stats.st }}</div>
            </div>
            <div class="rounded-[1.75rem] border border-white/70 bg-white/80 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.08)] backdrop-blur">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">GD links</div>
                <div class="mt-3 text-4xl font-semibold text-slate-900">{{ stats.gd }}</div>
            </div>
        </section>

        <section class="mt-5 rounded-[2rem] border border-white/70 bg-white/80 p-5 shadow-[0_20px_60px_rgba(15,23,42,0.1)] backdrop-blur sm:p-6">
            <div v-if="notice" class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ notice }}</div>
            <div class="mb-5 grid gap-3 lg:grid-cols-[1fr_220px_160px]">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Filter by product, version, or URL"
                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600 focus:shadow-[0_0_0_4px_rgba(8,145,178,0.12)]"
                >
                <select
                    v-model="filter"
                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600 focus:shadow-[0_0_0_4px_rgba(8,145,178,0.12)]"
                >
                    <option value="all">All entries</option>
                    <option value="latest">Latest only</option>
                    <option value="st">Has ST link</option>
                    <option value="gd">Has GD link</option>
                    <option value="lci">LCI only</option>
                </select>
                <select
                    v-model="perPage"
                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600 focus:shadow-[0_0_0_4px_rgba(8,145,178,0.12)]"
                >
                    <option :value="10">10 / page</option>
                    <option :value="20">20 / page</option>
                    <option :value="50">50 / page</option>
                </select>
            </div>

            <div v-if="apiError" class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {{ apiError }}
            </div>
            <div v-if="isLoading" class="mb-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                Loading software versions...
            </div>

            <div class="hidden overflow-hidden rounded-[1.5rem] border border-slate-200 xl:block">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50/90">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                            <th class="px-4 py-4">Name</th>
                            <th class="px-4 py-4">System Version</th>
                            <th class="px-4 py-4">Alt Version</th>
                            <th class="px-4 py-4">Links</th>
                            <th class="px-4 py-4">Status</th>
                            <th class="px-4 py-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white/80">
                        <tr v-for="row in filteredRows" :key="row.id" class="align-top">
                            <td class="px-4 py-4">
                                <div class="font-semibold text-slate-900">{{ row.name }}</div>
                                <div class="mt-1 text-xs text-slate-500">ID: {{ row.id }}</div>
                            </td>
                            <td class="px-4 py-4 font-mono text-sm text-slate-700">{{ row.system_version }}</td>
                            <td class="px-4 py-4 font-mono text-sm text-slate-700">{{ row.system_version_alt }}</td>
                            <td class="px-4 py-4 text-sm text-slate-700">
                                <div v-if="row.link"><a class="text-cyan-700 underline-offset-4 hover:underline" :href="row.link" target="_blank" rel="noreferrer">Main link</a></div>
                                <div v-if="row.st" class="mt-1"><a class="text-cyan-700 underline-offset-4 hover:underline" :href="row.st" target="_blank" rel="noreferrer">ST link</a></div>
                                <div v-if="row.gd" class="mt-1"><a class="text-cyan-700 underline-offset-4 hover:underline" :href="row.gd" target="_blank" rel="noreferrer">GD link</a></div>
                                <div v-if="!row.link && !row.st && !row.gd" class="text-slate-400">No links</div>
                            </td>
                            <td class="px-4 py-4">
                                <span v-if="row.latest" class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">Latest</span>
                                <div class="mt-2 text-sm text-slate-500">{{ row.st ? 'ST-ready' : 'No ST' }} | {{ row.gd ? 'GD-ready' : 'No GD' }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <a class="inline-flex rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white" :href="`${payload.editBaseUrl}/${encodeURIComponent(row.id)}/edit`">Edit</a>
                                    <button class="inline-flex rounded-xl bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700 ring-1 ring-slate-200" type="button" @click="copyRow(row)">Copy JSON</button>
                                    <button class="inline-flex rounded-xl bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 ring-1 ring-rose-200" type="button" @click="deleteRow(row.id)">Delete</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid gap-4 xl:hidden">
                <article v-for="row in filteredRows" :key="row.id" class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">{{ row.name }}</h2>
                            <p class="mt-1 text-xs text-slate-500">ID: {{ row.id }}</p>
                        </div>
                        <span v-if="row.latest" class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">Latest</span>
                    </div>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="font-medium text-slate-500">System version</dt>
                            <dd class="mt-1 break-all font-mono text-slate-800">{{ row.system_version }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Alt version</dt>
                            <dd class="mt-1 break-all font-mono text-slate-800">{{ row.system_version_alt }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Links</dt>
                            <dd class="mt-1 flex flex-col gap-1">
                                <a v-if="row.link" class="text-cyan-700 underline-offset-4 hover:underline" :href="row.link" target="_blank" rel="noreferrer">Main link</a>
                                <a v-if="row.st" class="text-cyan-700 underline-offset-4 hover:underline" :href="row.st" target="_blank" rel="noreferrer">ST link</a>
                                <a v-if="row.gd" class="text-cyan-700 underline-offset-4 hover:underline" :href="row.gd" target="_blank" rel="noreferrer">GD link</a>
                                <span v-if="!row.link && !row.st && !row.gd" class="text-slate-400">No links</span>
                            </dd>
                        </div>
                    </dl>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <a class="inline-flex rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white" :href="`${payload.editBaseUrl}/${encodeURIComponent(row.id)}/edit`">Edit</a>
                        <button class="inline-flex rounded-xl bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700 ring-1 ring-slate-200" type="button" @click="copyRow(row)">Copy JSON</button>
                        <button class="inline-flex rounded-xl bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 ring-1 ring-rose-200" type="button" @click="deleteRow(row.id)">Delete</button>
                    </div>
                </article>
            </div>

            <div class="mt-5 flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-slate-500">
                    Showing {{ rangeStart }} to {{ rangeEnd }} of {{ meta.total }} matching entries
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                        type="button"
                        :disabled="meta.page <= 1 || isLoading"
                        @click="page -= 1"
                    >
                        Previous
                    </button>
                    <div class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                        Page {{ meta.page }} / {{ meta.totalPages }}
                    </div>
                    <button
                        class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                        type="button"
                        :disabled="meta.page >= meta.totalPages || isLoading"
                        @click="page += 1"
                    >
                        Next
                    </button>
                </div>
            </div>

            <div v-if="!filteredRows.length && !isLoading" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
                No entries match the current filter.
            </div>
        </section>
    </main>
</template>
