<script setup lang="ts">
import { ref } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const usingRecoveryCode = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

function toggleRecoveryCode() {
    usingRecoveryCode.value = !usingRecoveryCode.value;
    form.reset();
    form.clearErrors();
}

function submit() {
    form.transform((data) =>
        usingRecoveryCode.value ? { recovery_code: data.recovery_code } : { code: data.code },
    ).post(route('two-factor.login.store'), {
        onFinish: () => form.reset(),
    });
}
</script>

<template>
    <GuestLayout>
        <Head title="Dvostopenjska prijava" />

        <h1 class="mb-1 text-lg font-semibold text-neutral-900">Dvostopenjska prijava</h1>
        <p class="mb-5 text-sm text-neutral-500">
            <span v-if="!usingRecoveryCode">Vnesi 6-mestno kodo iz svoje aplikacije za preverjanje pristnosti.</span>
            <span v-else>Vnesi eno od svojih rezervnih kod za obnovitev.</span>
        </p>

        <form @submit.prevent="submit">
            <div v-if="!usingRecoveryCode">
                <InputLabel for="code" value="Koda" />
                <TextInput
                    id="code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    class="mt-1 block w-full"
                    v-model="form.code"
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.code" />
            </div>

            <div v-else>
                <InputLabel for="recovery_code" value="Rezervna koda" />
                <TextInput
                    id="recovery_code"
                    type="text"
                    autocomplete="one-time-code"
                    class="mt-1 block w-full"
                    v-model="form.recovery_code"
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.recovery_code" />
            </div>

            <div class="mt-5 flex items-center justify-between">
                <button
                    type="button"
                    class="text-sm text-neutral-500 hover:text-neutral-800"
                    @click="toggleRecoveryCode"
                >
                    {{ usingRecoveryCode ? 'Uporabi kodo iz aplikacije' : 'Uporabi rezervno kodo' }}
                </button>

                <PrimaryButton :class="{ 'opacity-50': form.processing }" :disabled="form.processing">
                    Potrdi
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
