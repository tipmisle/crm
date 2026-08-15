<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
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
    <button
        type="button"
        class="rounded-md border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
        @click="confirmUserDeletion"
    >
        Izbriši račun
    </button>

    <Modal :show="confirmingUserDeletion" max-width="sm" @close="closeModal">
        <div class="p-6">
            <h2 class="text-base font-semibold text-neutral-900">Ste prepričani, da želite izbrisati svoj račun?</h2>

            <p class="mt-1 text-sm text-neutral-500">
                Ko je vaš račun izbrisan, bodo vsi njegovi viri in podatki trajno izbrisani. Vnesite geslo, da potrdite, da
                želite trajno izbrisati svoj račun.
            </p>

            <div class="mt-4">
                <label for="password" class="sr-only">Geslo</label>
                <TextInput
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    placeholder="Geslo"
                    @keyup.enter="deleteUser"
                />
                <p v-if="form.errors.password" class="mt-1 text-xs text-red-500">{{ form.errors.password }}</p>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-100" @click="closeModal">
                    Prekliči
                </button>
                <button
                    type="button"
                    :disabled="form.processing"
                    class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                    @click="deleteUser"
                >
                    Izbriši račun
                </button>
            </div>
        </div>
    </Modal>
</template>
