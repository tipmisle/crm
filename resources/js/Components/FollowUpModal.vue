<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';

const props = defineProps<{
    show: boolean;
    followableType: string;
    followableId: number;
    defaultNote?: string;
}>();

const emit = defineEmits<{ close: [] }>();

const form = useForm({
    followable_type: props.followableType,
    followable_id: props.followableId,
    note: props.defaultNote ?? '',
    due_at: '',
});

function submit() {
    form.post(route('follow-ups.store'), {
        onSuccess: () => {
            form.reset();
            emit('close');
        },
    });
}
</script>

<template>
    <Modal :show="show" max-width="sm" @close="emit('close')">
        <form class="p-6" @submit.prevent="submit">
            <h2 class="text-base font-semibold text-neutral-900">Nastavi opomnik</h2>

            <div class="mt-4 space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Opomba</label>
                    <textarea
                        v-model="form.note"
                        rows="2"
                        placeholder="Opomnik glede ponudbe …"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    />
                    <p v-if="form.errors.note" class="mt-1 text-xs text-red-500">{{ form.errors.note }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Rok</label>
                    <input
                        v-model="form.due_at"
                        type="datetime-local"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    />
                    <p v-if="form.errors.due_at" class="mt-1 text-xs text-red-500">{{ form.errors.due_at }}</p>
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-100"
                    @click="emit('close')"
                >
                    Prekliči
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-800 disabled:opacity-50"
                >
                    Shrani opomnik
                </button>
            </div>
        </form>
    </Modal>
</template>
