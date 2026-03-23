<script setup>
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    payload: {
        type: Object,
        default: () => ({}),
    },
});

const form = reactive({
    name: props.payload.values?.name ?? '',
    system_version: props.payload.values?.system_version ?? '',
    system_version_alt: props.payload.values?.system_version_alt ?? '',
    link: props.payload.values?.link ?? '',
    st: props.payload.values?.st ?? '',
    gd: props.payload.values?.gd ?? '',
    latest: Boolean(props.payload.values?.latest),
});

const errors = ref(props.payload.errors ?? []);
const isLoading = ref(false);

watch(() => form.system_version, (value) => {
    if (!form.system_version_alt && value) {
        form.system_version_alt = value.replace(/^[vV]/, '');
    }
});

const familyBadges = computed(() => {
    const name = form.name.toUpperCase();
    const badges = [name.includes('LCI') ? 'LCI family' : 'Standard family'];
    if (name.includes('CIC')) badges.push('CIC hardware');
    if (name.includes('NBT')) badges.push('NBT hardware');
    if (name.includes('EVO')) badges.push('EVO hardware');
    return badges;
});

const matchPreview = computed(() => {
    if (!form.system_version) {
        return 'Waiting for input';
    }
    return `${form.system_version} -> ${form.system_version_alt || form.system_version.replace(/^[vV]/, '')}`;
});

async function submit() {
    errors.value = [];
    isLoading.value = true;

    try {
        const response = await fetch(props.payload.apiUrl, {
            method: props.payload.mode === 'edit' ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ...form,
                csrfToken: props.payload.csrfToken,
            }),
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            errors.value = data.errors || [data.error?.message || data.message || 'Save failed.'];
            return;
        }

        window.location.href = data.redirectTo || props.payload.cancelUrl;
    } catch (error) {
        errors.value = ['Save failed. Please try again.'];
    } finally {
        isLoading.value = false;
    }
}
</script>

<template>
    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="grid gap-5 rounded-[2rem] border border-white/70 bg-white/80 p-6 shadow-[0_20px_60px_rgba(15,23,42,0.12)] backdrop-blur md:grid-cols-[1.15fr_0.85fr] md:p-8">
            <div>
                <span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">
                    Admin Form
                </span>
                <h1 class="mt-4 text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">{{ payload.mode === 'edit' ? 'Edit software version' : 'Add software version' }}</h1>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                    Use the guided Vue form to keep entries aligned with the legacy matching format.
                </p>
            </div>
            <div class="flex flex-wrap items-start gap-3 md:justify-end">
                <a class="inline-flex items-center rounded-2xl bg-white px-5 py-3 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 transition hover:bg-slate-50" :href="payload.cancelUrl">Back to list</a>
                <a class="inline-flex items-center rounded-2xl bg-white px-5 py-3 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 transition hover:bg-slate-50" href="/carplay/software-download" target="_blank" rel="noreferrer">View customer page</a>
            </div>
        </section>

        <div class="mt-5 grid gap-5 xl:grid-cols-[1.05fr_0.95fr]">
            <section class="rounded-[2rem] border border-white/70 bg-white/80 p-5 shadow-[0_20px_60px_rgba(15,23,42,0.1)] backdrop-blur sm:p-6">
                <div v-if="errors.length" class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                    <div v-for="error in errors" :key="error">{{ error }}</div>
                </div>

                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                    <div class="md:col-span-2">
                        <label class="mb-2 flex items-center justify-between gap-3 text-sm font-medium text-slate-700">
                            <span>Product name</span>
                            <small class="text-slate-500">Used to separate standard and LCI families</small>
                        </label>
                        <input v-model="form.name" required class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600 focus:shadow-[0_0_0_4px_rgba(8,145,178,0.12)]">
                    </div>
                    <div>
                        <label class="mb-2 flex items-center justify-between gap-3 text-sm font-medium text-slate-700">
                            <span>System version</span>
                            <small class="text-slate-500">Legacy display value</small>
                        </label>
                        <input v-model="form.system_version" required class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600 focus:shadow-[0_0_0_4px_rgba(8,145,178,0.12)]">
                    </div>
                    <div>
                        <label class="mb-2 flex items-center justify-between gap-3 text-sm font-medium text-slate-700">
                            <span>System version alt</span>
                            <small class="text-slate-500">API match value</small>
                        </label>
                        <input v-model="form.system_version_alt" required class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600 focus:shadow-[0_0_0_4px_rgba(8,145,178,0.12)]">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 flex items-center justify-between gap-3 text-sm font-medium text-slate-700">
                            <span>Main link</span>
                            <small class="text-slate-500">Required</small>
                        </label>
                        <input v-model="form.link" required class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600 focus:shadow-[0_0_0_4px_rgba(8,145,178,0.12)]">
                    </div>
                    <div>
                        <label class="mb-2 flex items-center justify-between gap-3 text-sm font-medium text-slate-700">
                            <span>ST link</span>
                            <small class="text-slate-500">Recommended for CIC</small>
                        </label>
                        <input v-model="form.st" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600 focus:shadow-[0_0_0_4px_rgba(8,145,178,0.12)]">
                    </div>
                    <div>
                        <label class="mb-2 flex items-center justify-between gap-3 text-sm font-medium text-slate-700">
                            <span>GD link</span>
                            <small class="text-slate-500">Recommended for NBT / EVO</small>
                        </label>
                        <input v-model="form.gd" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600 focus:shadow-[0_0_0_4px_rgba(8,145,178,0.12)]">
                    </div>
                    <label class="md:col-span-2 flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-700">
                        <input v-model="form.latest" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-cyan-700 focus:ring-cyan-600">
                        <span>Mark this entry as already up to date</span>
                    </label>
                    <div class="md:col-span-2 flex flex-wrap gap-3 pt-2">
                        <button class="inline-flex items-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" type="submit" :disabled="isLoading">{{ isLoading ? 'Saving...' : payload.submitLabel }}</button>
                        <a class="inline-flex items-center rounded-2xl bg-white px-5 py-3 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 transition hover:bg-slate-50" :href="payload.cancelUrl">Cancel</a>
                    </div>
                </form>
            </section>

            <aside class="grid gap-5">
                <div class="rounded-[2rem] border border-white/70 bg-slate-950 p-6 text-white shadow-[0_20px_60px_rgba(15,23,42,0.18)]">
                    <h2 class="text-2xl font-semibold tracking-tight">Live entry preview</h2>
                    <p class="mt-3 text-sm leading-7 text-slate-300">This side panel updates immediately while staff fill the form.</p>
                </div>
                <div class="rounded-[2rem] border border-white/70 bg-white/80 p-5 shadow-[0_20px_60px_rgba(15,23,42,0.1)] backdrop-blur">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Normalized match flow</div>
                    <div class="mt-3 break-all rounded-2xl bg-slate-900 px-4 py-3 font-mono text-sm text-cyan-100">{{ matchPreview }}</div>
                </div>
                <div class="rounded-[2rem] border border-white/70 bg-white/80 p-5 shadow-[0_20px_60px_rgba(15,23,42,0.1)] backdrop-blur">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Detected family</div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span v-for="badge in familyBadges" :key="badge" class="inline-flex rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold text-cyan-700 ring-1 ring-cyan-200">{{ badge }}</span>
                    </div>
                </div>
                <div class="rounded-[2rem] border border-white/70 bg-white/80 p-5 shadow-[0_20px_60px_rgba(15,23,42,0.1)] backdrop-blur">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Save checklist</div>
                    <ul class="mt-3 space-y-3 text-sm leading-6 text-slate-600">
                        <li>Product name must stay aligned with legacy naming.</li>
                        <li>`system_version_alt` should be the no-`v` match target.</li>
                        <li>URLs should be valid before saving.</li>
                        <li>CIC expects ST. NBT/EVO expect GD.</li>
                    </ul>
                </div>
            </aside>
        </div>
    </main>
</template>
