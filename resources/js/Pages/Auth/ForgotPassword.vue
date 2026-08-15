<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Pozabljeno geslo" />

        <h1 class="mb-1 text-lg font-semibold text-neutral-900">Pozabljeno geslo</h1>
        <p class="mb-5 text-sm text-neutral-500">
            Vpiši svoj e-poštni naslov in poslali ti bomo povezavo za ponastavitev gesla.
        </p>

        <div v-if="status" class="mb-4 text-sm font-medium text-emerald-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="E-pošta" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-5 flex items-center justify-end">
                <PrimaryButton
                    :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    Pošlji povezavo za ponastavitev
                </PrimaryButton>
            </div>
        </form>

        <p class="mt-6 text-center text-sm text-neutral-500">
            <Link :href="route('login')" class="font-medium text-[var(--color-accent-600)] hover:text-[var(--color-accent-700)]">
                Nazaj na prijavo
            </Link>
        </p>
    </GuestLayout>
</template>
