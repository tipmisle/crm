<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm.app'), {
        onFinish: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Potrdi geslo" />

        <h1 class="mb-1 text-lg font-semibold text-neutral-900">Potrdi geslo</h1>
        <p class="mb-5 text-sm text-neutral-500">
            To je varno območje aplikacije. Pred nadaljevanjem potrdi svoje geslo.
        </p>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="password" value="Geslo" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-5 flex justify-end">
                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    Potrdi
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
