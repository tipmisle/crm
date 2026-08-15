<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const form = useForm({
    full_name: '',
    email: '',
    phone: '',
    notes: '',
});

watch(
    () => props.open,
    (open) => {
        if (open) {
            form.reset();
            form.clearErrors();
        }
    },
);

function close() {
    emit('update:open', false);
}

function submit() {
    form.post(route('customers.store'), { preserveScroll: true });
}
</script>

<template>
    <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0"
        leave-active-class="transition duration-100 ease-in"
        leave-to-class="opacity-0"
    >
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-[var(--color-ink-900)]/40 px-4" @click.self="close">
            <div class="w-full max-w-md rounded-xl bg-white shadow-xl">
                <header class="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-neutral-900">Nova stranka</h2>
                    <button type="button" class="text-neutral-400 hover:text-neutral-700" @click="close">
                        <X :size="16" />
                    </button>
                </header>

                <form class="space-y-4 px-5 py-4" @submit.prevent="submit">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Polno ime</label>
                        <input
                            v-model="form.full_name"
                            type="text"
                            class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                        <p v-if="form.errors.full_name" class="mt-1 text-xs text-red-500">{{ form.errors.full_name }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">E-pošta</label>
                        <input
                            v-model="form.email"
                            type="email"
                            class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Telefon</label>
                        <input
                            v-model="form.phone"
                            type="text"
                            class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Opombe</label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" class="rounded-md px-3 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-50" @click="close">
                            Prekliči
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing || !form.full_name"
                            class="rounded-md bg-[var(--color-ink-900)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                        >
                            Ustvari stranko
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Transition>
</template>
