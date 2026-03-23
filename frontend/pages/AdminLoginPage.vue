<script setup>
import { ref } from 'vue';

const props = defineProps({
    payload: {
        type: Object,
        default: () => ({}),
    },
});

const username = ref('');
const password = ref('');
const error = ref('');
const isLoading = ref(false);

async function submit() {
    error.value = '';
    isLoading.value = true;

    try {
        const response = await fetch(props.payload.loginUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: username.value, password: password.value }),
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            error.value = data.message || 'Invalid admin credentials.';
            return;
        }

        window.location.href = data.redirectTo || props.payload.redirectTo || '/admin/software-versions';
    } catch (err) {
        error.value = 'Login failed. Please try again.';
    } finally {
        isLoading.value = false;
    }
}
</script>

<template>
    <main class="grid min-h-screen place-items-center px-4 py-10 sm:px-6">
        <section class="grid w-full max-w-5xl overflow-hidden rounded-[2rem] border border-white/70 bg-white/80 shadow-[0_30px_80px_rgba(15,23,42,0.14)] backdrop-blur xl:grid-cols-[1.1fr_0.9fr]">
            <div class="relative overflow-hidden bg-gradient-to-br from-cyan-800 via-teal-700 to-slate-900 px-6 py-10 text-white sm:px-10 sm:py-12">
                <div class="absolute -left-10 top-10 h-40 w-40 rounded-full bg-white/10 blur-2xl"></div>
                <div class="absolute bottom-0 right-0 h-56 w-56 translate-x-1/4 translate-y-1/4 rounded-full bg-amber-300/10 blur-3xl"></div>
                <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-cyan-50">
                    Firmware Admin
                </span>
                <h1 class="mt-6 max-w-xl text-4xl font-semibold leading-none sm:text-5xl">
                    Protect the data that protects the hardware.
                </h1>
                <p class="mt-5 max-w-lg text-sm leading-7 text-cyan-50/85 sm:text-base">
                    Only authorized staff should be able to change version mappings. Sign in to manage the firmware lookup dataset safely.
                </p>
                <div class="mt-8 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-4">
                        <div class="text-xs uppercase tracking-[0.16em] text-cyan-100/80">Protected</div>
                        <div class="mt-2 text-sm font-medium">Session-based admin access</div>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-4">
                        <div class="text-xs uppercase tracking-[0.16em] text-cyan-100/80">Purpose</div>
                        <div class="mt-2 text-sm font-medium">Firmware mapping control</div>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-4">
                        <div class="text-xs uppercase tracking-[0.16em] text-cyan-100/80">Audience</div>
                        <div class="mt-2 text-sm font-medium">Internal operations team</div>
                    </div>
                </div>
            </div>
            <div class="px-6 py-8 sm:px-10 sm:py-12">
                <div class="mx-auto max-w-md">
                    <h2 class="text-3xl font-semibold tracking-tight text-slate-900">Sign in</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        Credentials come from the Symfony environment configuration.
                    </p>
                    <div v-if="error" class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                        {{ error }}
                    </div>
                    <form class="mt-8 space-y-5" @submit.prevent="submit">
                        <div>
                            <label for="username" class="mb-2 block text-sm font-medium text-slate-700">Username</label>
                            <input
                                id="username"
                                v-model="username"
                                autocomplete="username"
                                required
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 outline-none ring-0 transition focus:border-cyan-600 focus:shadow-[0_0_0_4px_rgba(8,145,178,0.12)]"
                            >
                        </div>
                        <div>
                            <label for="password" class="mb-2 block text-sm font-medium text-slate-700">Password</label>
                            <input
                                id="password"
                                v-model="password"
                                type="password"
                                autocomplete="current-password"
                                required
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 outline-none ring-0 transition focus:border-cyan-600 focus:shadow-[0_0_0_4px_rgba(8,145,178,0.12)]"
                            >
                        </div>
                        <button
                            class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-900 px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                            type="submit"
                            :disabled="isLoading"
                        >
                            {{ isLoading ? 'Signing in...' : 'Open admin panel' }}
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </main>
</template>
