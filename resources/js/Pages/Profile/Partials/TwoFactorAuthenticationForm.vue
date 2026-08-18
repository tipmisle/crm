<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import InputError from '@/Components/InputError.vue';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const confirmedAt = computed(() => page.props.auth.user.two_factor_confirmed_at ?? null);
const isEnabled = computed(() => confirmedAt.value !== null);

// Local-only state for the setup-in-progress flow (secret generated, not
// yet confirmed) — the backend deliberately never exposes two_factor_secret
// to the frontend at rest, so "setting up" only exists client-side between
// enable() and a successful confirm()/cancel.
const settingUp = ref(false);
const qrSvg = ref<string | null>(null);
const secretKey = ref<string | null>(null);
const recoveryCodes = ref<string[] | null>(null);
const loading = ref(false);

const enableForm = useForm({});
const confirmForm = useForm({ code: '' });
const disableForm = useForm({});
const regenerateForm = useForm({});

async function enable() {
    loading.value = true;
    enableForm.post(route('two-factor.enable'), {
        preserveScroll: true,
        onSuccess: async () => {
            settingUp.value = true;
            await loadSetupData();
            loading.value = false;
        },
        onError: () => {
            loading.value = false;
        },
    });
}

async function loadSetupData() {
    const [qr, secret] = await Promise.all([
        axios.get(route('two-factor.qr-code')),
        axios.get(route('two-factor.secret-key')),
    ]);
    qrSvg.value = qr.data.svg;
    secretKey.value = secret.data.secretKey;
}

function confirm() {
    // Fortify puts confirm() validation errors in a named 'confirmTwoFactorAuthentication'
    // error bag (see vendor/laravel/fortify's ConfirmTwoFactorAuthentication action) — this
    // tells Inertia to surface them at the usual form.errors.* path instead of nested under
    // that bag name.
    confirmForm.post(route('two-factor.confirm'), {
        errorBag: 'confirmTwoFactorAuthentication',
        preserveScroll: true,
        onSuccess: async () => {
            const res = await axios.get(route('two-factor.recovery-codes'));
            recoveryCodes.value = res.data;
            settingUp.value = false;
            qrSvg.value = null;
            secretKey.value = null;
            confirmForm.reset();
            router.reload({ only: ['auth'] });
        },
    });
}

function cancelSetup() {
    settingUp.value = false;
    qrSvg.value = null;
    secretKey.value = null;
    confirmForm.reset();
    confirmForm.clearErrors();
    // The just-generated (unconfirmed) secret is abandoned server-side by
    // disabling — there is nothing durable to leave behind since it was
    // never confirmed.
    disableForm.delete(route('two-factor.disable'), { preserveScroll: true });
}

function disable() {
    disableForm.delete(route('two-factor.disable'), {
        preserveScroll: true,
        onSuccess: () => {
            recoveryCodes.value = null;
            router.reload({ only: ['auth'] });
        },
    });
}

async function regenerateRecoveryCodes() {
    regenerateForm.post(route('two-factor.regenerate-recovery-codes'), {
        preserveScroll: true,
        onSuccess: async () => {
            const res = await axios.get(route('two-factor.recovery-codes'));
            recoveryCodes.value = res.data;
        },
    });
}

function dismissRecoveryCodes() {
    recoveryCodes.value = null;
}
</script>

<template>
    <div class="space-y-4">
        <div v-if="!isEnabled && !settingUp">
            <p class="text-sm text-neutral-600">
                Dvostopenjsko preverjanje doda dodatno raven varnosti tvojemu računu — poleg gesla boš ob prijavi
                potreboval-a še kodo iz aplikacije za preverjanje pristnosti (npr. Google Authenticator, 1Password).
            </p>
            <button
                type="button"
                class="mt-3 rounded-md bg-[var(--color-ink-900)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                :disabled="loading"
                @click="enable"
            >
                Omogoči dvostopenjsko preverjanje
            </button>
        </div>

        <div v-else-if="settingUp" class="space-y-4">
            <p class="text-sm text-neutral-600">
                Skeniraj to kodo QR z aplikacijo za preverjanje pristnosti, nato vnesi 6-mestno kodo, ki jo prikaže.
            </p>
            <div v-if="qrSvg" class="w-fit rounded-lg border border-neutral-200 p-3" v-html="qrSvg" />
            <p v-if="secretKey" class="text-xs text-neutral-500">
                Če QR kode ni mogoče skenirati, vnesi ta ključ ročno: <code class="rounded bg-neutral-100 px-1.5 py-0.5">{{ secretKey }}</code>
            </p>

            <form class="flex items-end gap-2" @submit.prevent="confirm">
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-600">Koda za potrditev</label>
                    <input
                        v-model="confirmForm.code"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        class="w-32 rounded-md border border-neutral-300 px-3 py-2 text-sm"
                    />
                    <InputError :message="confirmForm.errors.code" />
                </div>
                <button
                    type="submit"
                    class="rounded-md bg-[var(--color-ink-900)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                    :disabled="confirmForm.processing"
                >
                    Potrdi
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-sm font-medium text-neutral-500 hover:bg-neutral-100"
                    @click="cancelSetup"
                >
                    Prekliči
                </button>
            </form>
        </div>

        <div v-else class="space-y-3">
            <div class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                Dvostopenjsko preverjanje je omogočeno.
            </div>

            <div v-if="recoveryCodes" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <p class="text-sm font-medium text-amber-900">Shrani te rezervne kode na varno mesto</p>
                <p class="mt-1 text-xs text-amber-700">
                    Vsako kodo lahko uporabiš samo enkrat, če izgubiš dostop do aplikacije za preverjanje pristnosti.
                    Po zapustitvi te strani jih ne bomo več prikazali.
                </p>
                <ul class="mt-3 grid grid-cols-2 gap-1.5 font-mono text-xs text-neutral-800">
                    <li v-for="code in recoveryCodes" :key="code" class="rounded bg-white px-2 py-1">{{ code }}</li>
                </ul>
                <button type="button" class="mt-3 text-xs font-medium text-amber-800 underline" @click="dismissRecoveryCodes">
                    Shranjeno, skrij kode
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="rounded-md border border-neutral-300 px-3 py-1.5 text-sm font-medium text-neutral-700 hover:bg-neutral-50"
                    :disabled="regenerateForm.processing"
                    @click="regenerateRecoveryCodes"
                >
                    Ustvari nove rezervne kode
                </button>
                <button
                    type="button"
                    class="rounded-md border border-red-200 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50"
                    :disabled="disableForm.processing"
                    @click="disable"
                >
                    Onemogoči
                </button>
            </div>
        </div>
    </div>
</template>
