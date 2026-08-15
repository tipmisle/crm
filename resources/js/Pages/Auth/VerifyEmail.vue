<script setup lang="ts">
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Potrditev e-pošte" />

        <h1 class="mb-1 text-lg font-semibold text-neutral-900">Potrditev e-pošte</h1>
        <p class="mb-5 text-sm text-neutral-500">
            Hvala za registracijo! Preden začneš, potrdi svoj e-poštni naslov s klikom na povezavo, ki smo ti jo
            pravkar poslali. Če e-pošte nisi prejel/a, ti bomo z veseljem poslali novo.
        </p>

        <div class="mb-4 text-sm font-medium text-emerald-600" v-if="verificationLinkSent">
            Nova potrditvena povezava je bila poslana na e-poštni naslov, ki ste ga navedli ob registraciji.
        </div>

        <form @submit.prevent="submit">
            <div class="flex items-center justify-between">
                <PrimaryButton
                    :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    Znova pošlji potrditveno e-pošto
                </PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="rounded-md text-sm text-neutral-500 hover:text-neutral-800"
                    >Odjava</Link
                >
            </div>
        </form>
    </GuestLayout>
</template>
