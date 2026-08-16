<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';

const props = defineProps<{
    show: boolean;
    title: string;
    submitLabel: string;
    action: string;
    defaultBody: string;
}>();

const emit = defineEmits<{ close: [] }>();

const form = useForm({ body: props.defaultBody });

watch(
    () => props.defaultBody,
    (value) => (form.body = value),
);

function submit() {
    form.post(props.action, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('close')">
        <form class="p-6" @submit.prevent="submit">
            <h2 class="text-base font-semibold text-neutral-900">{{ title }}</h2>

            <div class="mt-4">
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Sporočilo</label>
                <textarea
                    v-model="form.body"
                    rows="4"
                    class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                />
                <p v-if="form.errors.body" class="mt-1 text-xs text-red-500">{{ form.errors.body }}</p>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-100" @click="emit('close')">
                    Prekliči
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-md bg-[var(--color-ink-900)] px-3 py-1.5 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                >
                    {{ submitLabel }}
                </button>
            </div>
        </form>
    </Modal>
</template>
