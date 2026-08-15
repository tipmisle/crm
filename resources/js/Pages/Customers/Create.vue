<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const form = useForm({
    full_name: '',
    email: '',
    phone: '',
    notes: '',
});

function submit() {
    form.post(route('customers.store'));
}
</script>

<template>
    <Head title="Nova stranka" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Nova stranka</h1>
        </template>

        <div class="mx-auto max-w-2xl px-4 py-6 sm:px-6 sm:py-8">
            <h1 class="text-2xl font-semibold text-neutral-900">Nova stranka</h1>

            <form class="mt-6 space-y-5" @submit.prevent="submit">
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

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-md bg-[var(--color-ink-900)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                >
                    Ustvari stranko
                </button>
            </form>
        </div>
    </AppLayout>
</template>
