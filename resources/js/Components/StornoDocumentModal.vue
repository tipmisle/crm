<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';

const props = defineProps<{
    show: boolean;
    documentNumber: string;
    action: string;
}>();

const emit = defineEmits<{ close: [] }>();

const form = useForm({ reason: '' });

const reasonExamples = ['Napačen znesek', 'Naročilo preklicano', 'Račun izdan pomotoma'];

function submit() {
    form.post(props.action, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('close');
        },
    });
}

function close() {
    form.reset();
    form.clearErrors();
    emit('close');
}
</script>

<template>
    <Modal :show="show" max-width="md" @close="close">
        <form class="p-6" @submit.prevent="submit">
            <h2 class="text-base font-semibold text-neutral-900">Storniraj račun {{ documentNumber }}</h2>
            <p class="mt-1.5 text-sm text-neutral-500">
                Ustvarjen bo nov, ločen dobropis/storno dokument, ki v celoti obrne ta račun. Prvotni račun ostane
                nespremenjen in bo označen kot "Storniran".
            </p>

            <div class="mt-4">
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Razlog storna</label>
                <textarea
                    v-model="form.reason"
                    rows="2"
                    placeholder="npr. Napačen znesek"
                    class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                />
                <p v-if="form.errors.reason" class="mt-1 text-xs text-red-500">{{ form.errors.reason }}</p>
                <p class="mt-1.5 text-xs text-neutral-400">Npr.: {{ reasonExamples.join(' · ') }}</p>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-100" @click="close">
                    Prekliči
                </button>
                <button
                    type="submit"
                    :disabled="form.processing || !form.reason.trim()"
                    class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                >
                    Storniraj račun
                </button>
            </div>
        </form>
    </Modal>
</template>
