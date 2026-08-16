<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';

const props = defineProps<{
    show: boolean;
    orderId: number;
}>();

const emit = defineEmits<{ close: [] }>();

const form = useForm<{ file: File | null; type: string; external_document_number: string }>({
    file: null,
    type: 'invoice',
    external_document_number: '',
});

function onFileChange(event: Event) {
    form.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function submit() {
    form.post(route('orders.documents.external.store', props.orderId), {
        preserveScroll: true,
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
            <h2 class="text-base font-semibold text-neutral-900">Priloži obstoječ dokument</h2>
            <p class="mt-1 text-xs text-neutral-500">Za dokumente izdane prek Minimax, Pantheon, Apollo ipd. Ne vpliva na oštevilčevanje Beležke.</p>

            <div class="mt-4 space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Vrsta dokumenta</label>
                    <select v-model="form.type" class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none">
                        <option value="proforma">Predračun</option>
                        <option value="invoice">Račun</option>
                        <option value="other">Drugo</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Številka dokumenta (neobvezno)</label>
                    <input v-model="form.external_document_number" type="text" class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">PDF datoteka</label>
                    <input type="file" accept="application/pdf" class="w-full text-sm" @change="onFileChange" />
                    <p v-if="form.errors.file" class="mt-1 text-xs text-red-500">{{ form.errors.file }}</p>
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-100" @click="emit('close')">
                    Prekliči
                </button>
                <button
                    type="submit"
                    :disabled="form.processing || !form.file"
                    class="rounded-md bg-[var(--color-ink-900)] px-3 py-1.5 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                >
                    Dodaj dokument
                </button>
            </div>
        </form>
    </Modal>
</template>
