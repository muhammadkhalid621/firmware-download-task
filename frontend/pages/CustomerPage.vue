<script setup>
import { computed, ref } from 'vue';

defineProps({
    payload: {
        type: Object,
        default: () => ({}),
    },
});

const version = ref('');
const hwVersion = ref('');
const result = ref(null);
const isLoading = ref(false);
const modalOpen = ref(false);
const recentKey = 'firmware-download-recent-checks';
const recentChecks = ref(JSON.parse(localStorage.getItem(recentKey) || '[]'));

const normalizedVersion = computed(() => version.value.trim().replace(/^[vV]/, '') || '-');

const hardwareState = computed(() => {
    const value = hwVersion.value.trim();
    if (!value) {
        return { title: 'Waiting for input', detail: 'Paste the exact hardware string', key: '' };
    }

    if (/^CPAA_[0-9]{4}\.[0-9]{2}\.[0-9]{2}(_[A-Z]+)?$/i.test(value)) {
        return { title: 'Standard ST detected', detail: 'This hardware returns ST links when available.', key: 'standard-st' };
    }

    if (/^CPAA_G_[0-9]{4}\.[0-9]{2}\.[0-9]{2}(_[A-Z]+)?$/i.test(value)) {
        return { title: 'Standard GD detected', detail: 'This hardware returns GD links when available.', key: 'standard-gd' };
    }

    if (/^B_C_[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/i.test(value)) {
        return { title: 'LCI CIC detected', detail: 'LCI path with CIC-specific ST behavior.', key: 'lci-cic' };
    }

    if (/^B_N_G_[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/i.test(value)) {
        return { title: 'LCI NBT detected', detail: 'LCI path with NBT-specific GD behavior.', key: 'lci-nbt-evo' };
    }

    if (/^B_E_G_[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/i.test(value)) {
        return { title: 'LCI EVO detected', detail: 'LCI path with EVO-specific GD behavior.', key: 'lci-nbt-evo' };
    }

    return { title: 'Pattern not recognized yet', detail: 'The API will return the legacy identification error if this stays invalid.', key: '' };
});

function rememberCheck(entry) {
    recentChecks.value = [entry, ...recentChecks.value.filter((item) => item.version !== entry.version || item.hwVersion !== entry.hwVersion)].slice(0, 4);
    localStorage.setItem(recentKey, JSON.stringify(recentChecks.value));
}

function restoreCheck(entry) {
    version.value = entry.version;
    hwVersion.value = entry.hwVersion;
}

async function submit() {
    result.value = null;
    isLoading.value = true;

    try {
        const response = await fetch('/api2/carplay/software/version', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                version: version.value,
                mcuVersion: '',
                hwVersion: hwVersion.value,
            }),
        });

        const payload = await response.json();
        result.value = {
            success: payload.versionExist === true,
            ...payload,
        };

        if (version.value.trim() && hwVersion.value.trim()) {
            rememberCheck({ version: version.value.trim(), hwVersion: hwVersion.value.trim() });
        }
    } catch (error) {
        result.value = {
            success: false,
            msg: 'Failed to fetch software details. Please try again.',
        };
    } finally {
        isLoading.value = false;
    }
}
</script>

<template>
    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="grid gap-5 xl:grid-cols-[1.05fr_0.95fr]">
            <div class="relative overflow-hidden rounded-[2rem] bg-slate-950 px-6 py-8 text-white shadow-[0_30px_90px_rgba(15,23,42,0.22)] sm:px-8 sm:py-10">
                <div class="absolute -left-14 top-6 h-48 w-48 rounded-full bg-cyan-400/15 blur-3xl"></div>
                <div class="absolute bottom-0 right-0 h-56 w-56 translate-x-1/4 translate-y-1/4 rounded-full bg-amber-300/15 blur-3xl"></div>
                <span class="inline-flex items-center rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-100">
                    Firmware Match Check
                </span>
                <h1 class="relative mt-5 max-w-3xl text-4xl font-semibold leading-none tracking-tight sm:text-5xl lg:text-6xl">Update the software for your CarPlay / Android Auto MMI</h1>
                <p class="relative mt-5 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base">
                    Enter the exact software and hardware versions shown on your device. The lookup rules and response messages stay aligned with the legacy implementation.
                </p>
                <div class="relative mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-[1.5rem] border border-white/10 bg-white/10 p-4 backdrop-blur">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-cyan-100/80">Standard Units</div>
                        <div class="mt-2 text-sm leading-6 text-slate-200">Uses `CPAA_...` or `CPAA_G_...` hardware patterns.</div>
                    </div>
                    <div class="rounded-[1.5rem] border border-white/10 bg-white/10 p-4 backdrop-blur">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-cyan-100/80">LCI Units</div>
                        <div class="mt-2 text-sm leading-6 text-slate-200">Uses `B_C_...`, `B_N_G_...`, or `B_E_G_...` patterns.</div>
                    </div>
                    <div class="rounded-[1.5rem] border border-white/10 bg-white/10 p-4 backdrop-blur sm:col-span-2 lg:col-span-1">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-cyan-100/80">Safety First</div>
                        <div class="mt-2 text-sm leading-6 text-slate-200">Wrong firmware can brick the device. Double-check before downloading.</div>
                    </div>
                </div>
            </div>

            <section class="rounded-[2rem] border border-white/70 bg-white/85 p-5 shadow-[0_20px_60px_rgba(15,23,42,0.12)] backdrop-blur sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Check for the correct firmware</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">The result payload is still produced by Symfony. Vue adds the live guidance and smoother interaction.</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Hardware detection</div>
                        <div class="mt-1 text-sm font-medium text-slate-900">{{ hardwareState.title }}</div>
                    </div>
                </div>

                <form class="mt-6 grid gap-4" @submit.prevent="submit">
                    <div>
                        <label for="version" class="mb-2 flex items-center justify-between gap-3 text-sm font-medium text-slate-700">
                            <span>Software Version</span>
                            <small class="text-slate-500">Normalized: {{ normalizedVersion }}</small>
                        </label>
                        <input id="version" v-model="version" placeholder="Your software version*" required class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600 focus:shadow-[0_0_0_4px_rgba(8,145,178,0.12)]">
                        <div class="mt-2 text-xs text-slate-500">Example: `v3.3.7.mmipri.c`</div>
                    </div>

                    <div>
                        <label for="mcuVersion" class="mb-2 flex items-center justify-between gap-3 text-sm font-medium text-slate-700">
                            <span>MCU Version</span>
                            <small class="text-slate-500">Not required</small>
                        </label>
                        <input id="mcuVersion" value="Not Required" disabled class="w-full rounded-2xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm text-slate-500">
                        <div class="mt-2 text-xs text-slate-500">The existing logic ignores MCU version for this lookup.</div>
                    </div>

                    <div>
                        <label for="hwVersion" class="mb-2 flex items-center justify-between gap-3 text-sm font-medium text-slate-700">
                            <span>HW Version</span>
                            <small class="text-slate-500">{{ hardwareState.detail }}</small>
                        </label>
                        <input id="hwVersion" v-model="hwVersion" placeholder="Your HW version*" required class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600 focus:shadow-[0_0_0_4px_rgba(8,145,178,0.12)]">
                        <div class="mt-2 text-xs text-slate-500">Use the exact punctuation and underscores from the device screen.</div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-[1.25rem] border p-4 text-sm transition" :class="hardwareState.key === 'standard-st' ? 'border-cyan-300 bg-cyan-50 text-cyan-900' : 'border-slate-200 bg-slate-50 text-slate-700'">
                            <div class="font-semibold">Standard ST</div>
                            <div class="mt-1 font-mono text-xs">CPAA_YYYY.MM.DD</div>
                        </div>
                        <div class="rounded-[1.25rem] border p-4 text-sm transition" :class="hardwareState.key === 'standard-gd' ? 'border-cyan-300 bg-cyan-50 text-cyan-900' : 'border-slate-200 bg-slate-50 text-slate-700'">
                            <div class="font-semibold">Standard GD</div>
                            <div class="mt-1 font-mono text-xs">CPAA_G_YYYY.MM.DD</div>
                        </div>
                        <div class="rounded-[1.25rem] border p-4 text-sm transition" :class="hardwareState.key === 'lci-cic' ? 'border-cyan-300 bg-cyan-50 text-cyan-900' : 'border-slate-200 bg-slate-50 text-slate-700'">
                            <div class="font-semibold">LCI CIC</div>
                            <div class="mt-1 font-mono text-xs">B_C_YYYY.MM.DD</div>
                        </div>
                        <div class="rounded-[1.25rem] border p-4 text-sm transition" :class="hardwareState.key === 'lci-nbt-evo' ? 'border-cyan-300 bg-cyan-50 text-cyan-900' : 'border-slate-200 bg-slate-50 text-slate-700'">
                            <div class="font-semibold">LCI NBT / EVO</div>
                            <div class="mt-1 font-mono text-xs">B_N_G_... or B_E_G_...</div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <button class="inline-flex items-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" type="submit" :disabled="isLoading">{{ isLoading ? 'Checking...' : 'Check firmware' }}</button>
                        <button class="inline-flex items-center rounded-2xl bg-white px-5 py-3 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 transition hover:bg-slate-50" type="button" @click="modalOpen = true">What is my current software?</button>
                        <span class="text-sm text-slate-500">Tip: find it in MMI settings &gt; System &gt; System/Firmware version.</span>
                    </div>
                </form>

                <div v-if="result" class="mt-5 rounded-[1.5rem] border px-5 py-4" :class="result.success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-700'">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.16em]">{{ result.success ? 'Firmware match found' : 'Match not confirmed' }}</h3>
                    <p class="mt-2 text-sm leading-6">{{ result.msg }}</p>
                    <div v-if="result.success && (result.st || result.gd)" class="mt-4 flex flex-wrap gap-3">
                        <a v-if="result.st" class="inline-flex rounded-2xl bg-white px-4 py-2 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-200" :href="result.st" target="_blank" rel="noreferrer">ST Version</a>
                        <a v-if="result.gd" class="inline-flex rounded-2xl bg-white px-4 py-2 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-200" :href="result.gd" target="_blank" rel="noreferrer">GD Version</a>
                    </div>
                </div>

                <section v-if="recentChecks.length" class="mt-5 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                    <strong class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500">Recent checks</strong>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="entry in recentChecks"
                            :key="`${entry.version}-${entry.hwVersion}`"
                            type="button"
                            class="inline-flex rounded-full bg-white px-4 py-2 text-xs font-semibold text-slate-700 ring-1 ring-slate-200 transition hover:bg-slate-100"
                            @click="restoreCheck(entry)"
                        >
                            {{ entry.version }} | {{ entry.hwVersion }}
                        </button>
                    </div>
                </section>

                <div class="mt-5 rounded-[1.5rem] border border-amber-200 bg-amber-50 p-5 text-amber-900">
                    <strong class="text-sm font-semibold uppercase tracking-[0.16em]">Warning!!!</strong>
                    <p class="mt-3 text-sm leading-7">
                        Entering an incorrect software number and uploading it to another version of MMI will brick the MMI.<br>
                        In most cases, this is a reversible process, but impossible to perform without the assistance of our support.<br>
                        BimmerTech is not responsible for errors in software uploading by customers due to uploading the incorrect version.<br>
                        After this step, replacement under warranty cannot be considered.
                    </p>
                </div>
            </section>
        </section>

        <section class="mt-6 rounded-[2rem] border border-white/70 bg-white/85 px-6 py-8 text-center shadow-[0_20px_60px_rgba(15,23,42,0.1)] backdrop-blur">
            <a class="inline-flex items-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800" href="https://newshop.testshop1.bimmer-tech.net/" target="_blank" rel="noreferrer">Go back to the shop</a>
            <p class="mx-auto mt-4 max-w-3xl text-sm leading-7 text-slate-500">
                BMW and MINI are registered trademarks of Bayerische Motoren Werke AG. BimmerTech is not affiliated with BMW AG in any way,
                and is not authorized by BMW AG to act as an official distributor or representative.
            </p>
        </section>

        <div v-if="modalOpen" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/70 p-4 backdrop-blur-sm" @click.self="modalOpen = false">
            <div class="w-full max-w-5xl overflow-hidden rounded-[2rem] bg-white shadow-2xl">
                <div class="flex items-center justify-between bg-slate-950 px-5 py-4 text-white">
                    <strong class="text-sm font-semibold uppercase tracking-[0.16em]">Find your current software version</strong>
                    <button class="inline-flex items-center rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/15" type="button" @click="modalOpen = false">Close</button>
                </div>
                <img class="block w-full" src="https://www.bimmer-tech.net/assets/carplay/assets/mmi_software_version.jpg" alt="Where to find your software version">
            </div>
        </div>
    </main>
</template>
