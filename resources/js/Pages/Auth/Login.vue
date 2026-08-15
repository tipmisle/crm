<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Prijava" />

        <h1 class="mb-1 text-lg font-semibold text-neutral-900">Prijava</h1>
        <p class="mb-5 text-sm text-neutral-500">Vpiši se v svoj račun.</p>

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

            <div class="mt-4">
                <InputLabel for="password" value="Geslo" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-neutral-600">Zapomni si me</span>
                </label>
            </div>

            <div class="mt-5 flex items-center justify-end">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="rounded-md text-sm text-neutral-500 hover:text-neutral-800"
                >
                    Ste pozabili geslo?
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    Prijava
                </PrimaryButton>
            </div>
        </form>

        <p class="mt-6 text-center text-sm text-neutral-500">
            Nimate računa?
            <Link :href="route('register')" class="font-medium text-[var(--color-accent-600)] hover:text-[var(--color-accent-700)]">
                Registracija
            </Link>
        </p>
    </GuestLayout>
</template>
