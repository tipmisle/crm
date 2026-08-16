<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import PasswordStrengthMeter from '@/Components/PasswordStrengthMeter.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms_dpa_accepted: false,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Registracija" />

        <h1 class="mb-1 text-lg font-semibold text-neutral-900">Registracija</h1>
        <p class="mb-5 text-sm text-neutral-500">Ustvari nov račun in delovni prostor.</p>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Ime" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel for="email" value="E-pošta" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
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
                    autocomplete="new-password"
                />

                <PasswordStrengthMeter :password="form.password" />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="password_confirmation"
                    value="Potrdi geslo"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="mt-5">
                <label class="flex items-start gap-2 text-sm text-neutral-600">
                    <Checkbox v-model:checked="form.terms_dpa_accepted" class="mt-0.5" />
                    <span>
                        Strinjam se s
                        <Link :href="route('legal.terms')" target="_blank" class="text-[var(--color-accent-600)] hover:underline">Pogoji poslovanja</Link>
                        in
                        <Link :href="route('legal.dpa')" target="_blank" class="text-[var(--color-accent-600)] hover:underline">Dogovorom o obdelavi osebnih podatkov</Link>.
                    </span>
                </label>
                <InputError class="mt-2" :message="form.errors.terms_dpa_accepted" />

                <p class="mt-2 text-xs text-neutral-400">
                    Preberi tudi našo
                    <Link :href="route('legal.privacy')" target="_blank" class="text-[var(--color-accent-600)] hover:underline">Politiko zasebnosti</Link>.
                </p>
            </div>

            <div class="mt-5 flex items-center justify-end">
                <Link :href="route('login')" class="rounded-md text-sm text-neutral-500 hover:text-neutral-800">
                    Že imate račun?
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    Registracija
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
