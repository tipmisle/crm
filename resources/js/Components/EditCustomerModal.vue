<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import type { Customer } from '@/types/models';

const props = withDefaults(
    defineProps<{
        show: boolean;
        customer: Customer;
        showName?: boolean;
        showNotes?: boolean;
    }>(),
    {
        showName: false,
        showNotes: false,
    },
);

const emit = defineEmits<{ close: [] }>();

const form = useForm({
    full_name: props.customer.full_name,
    email: props.customer.email ?? '',
    phone: props.customer.phone ?? '',
    address_line: props.customer.address_line ?? '',
    postal_code: props.customer.postal_code ?? '',
    city: props.customer.city ?? '',
    notes: props.customer.notes ?? '',
});

watch(
    () => props.show,
    (show) => {
        if (!show) return;
        form.full_name = props.customer.full_name;
        form.email = props.customer.email ?? '';
        form.phone = props.customer.phone ?? '';
        form.address_line = props.customer.address_line ?? '';
        form.postal_code = props.customer.postal_code ?? '';
        form.city = props.customer.city ?? '';
        form.notes = props.customer.notes ?? '';
        form.clearErrors();
    },
);

function submit() {
    form.patch(route('customers.update', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('close')">
        <form class="p-6" @submit.prevent="submit">
            <h2 class="text-base font-semibold text-neutral-900">Uredi podatke stranke</h2>

            <div class="mt-4 space-y-3">
                <div v-if="showName">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Polno ime</label>
                    <input
                        v-model="form.full_name"
                        type="text"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    />
                    <p v-if="form.errors.full_name" class="mt-1 text-xs text-red-500">{{ form.errors.full_name }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Telefonska</label>
                    <input
                        v-model="form.phone"
                        type="text"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    />
                    <p v-if="form.errors.phone" class="mt-1 text-xs text-red-500">{{ form.errors.phone }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Naslov</label>
                    <input
                        v-model="form.address_line"
                        type="text"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    />
                    <p v-if="form.errors.address_line" class="mt-1 text-xs text-red-500">{{ form.errors.address_line }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Poštna številka</label>
                        <input
                            v-model="form.postal_code"
                            type="text"
                            class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                        <p v-if="form.errors.postal_code" class="mt-1 text-xs text-red-500">{{ form.errors.postal_code }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kraj</label>
                        <input
                            v-model="form.city"
                            type="text"
                            class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                        <p v-if="form.errors.city" class="mt-1 text-xs text-red-500">{{ form.errors.city }}</p>
                    </div>
                </div>

                <div v-if="showNotes">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Opombe</label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    />
                    <p v-if="form.errors.notes" class="mt-1 text-xs text-red-500">{{ form.errors.notes }}</p>
                </div>
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
                    Shrani
                </button>
            </div>
        </form>
    </Modal>
</template>
