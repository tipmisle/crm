<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value?.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => {
            form.reset();
        },
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Izbriši račun
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Ko je vaš račun izbrisan, bodo vsi njegovi viri in podatki
                trajno izbrisani. Preden izbrišete račun, prosimo prenesite
                vse podatke ali informacije, ki jih želite obdržati.
            </p>
        </header>

        <DangerButton @click="confirmUserDeletion">Izbriši račun</DangerButton>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    Ste prepričani, da želite izbrisati svoj račun?
                </h2>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Ko je vaš račun izbrisan, bodo vsi njegovi viri in podatki
                    trajno izbrisani. Vnesite geslo, da potrdite, da želite
                    trajno izbrisati svoj račun.
                </p>

                <div class="mt-6">
                    <InputLabel
                        for="password"
                        value="Geslo"
                        class="sr-only"
                    />

                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-3/4"
                        placeholder="Geslo"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal">
                        Prekliči
                    </SecondaryButton>

                    <DangerButton
                        class="ms-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        Izbriši račun
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
