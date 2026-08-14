<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import type { Conversation, Customer } from '@/types/models';

const props = defineProps<{
    customer: Customer | null;
    conversation: Conversation | null;
}>();

const contactName =
    props.customer?.full_name ??
    props.conversation?.customer?.full_name ??
    props.conversation?.customer_display_name ??
    undefined;

const form = useForm({
    title: '',
    description: '',
    customer_id: props.customer?.id ?? props.conversation?.customer?.id ?? null,
    conversation_id: props.conversation?.id ?? null,
    due_date: '',
    due_time: '',
    price: '',
    deposit_amount: '',
    internal_notes: '',
    customer_notes: '',
});

function submit() {
    form.post(route('orders.store'));
}
</script>

<template>
    <Head title="Novo naročilo" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Novo naročilo</h1>
        </template>

        <div class="mx-auto max-w-2xl px-6 py-8">
            <h1 class="text-2xl font-semibold text-neutral-900">Novo naročilo</h1>

            <div v-if="contactName" class="mt-3 flex items-center gap-2 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5">
                <Avatar :name="contactName" size="sm" />
                <div class="text-sm">
                    <span class="font-medium text-neutral-900">{{ contactName }}</span>
                    <span v-if="!customer" class="ml-1 text-neutral-500">— dodan bo kot nova stranka</span>
                </div>
            </div>

            <form class="mt-6 space-y-5" @submit.prevent="submit">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Naslov naročila</label>
                    <input
                        v-model="form.title"
                        type="text"
                        placeholder="npr. Torta za rojstni dan – tema samorog"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    />
                    <p v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Opis</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Rok</label>
                        <input
                            v-model="form.due_date"
                            type="date"
                            class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Ura (neobvezno)</label>
                        <input
                            v-model="form.due_time"
                            type="time"
                            class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Cena</label>
                        <input
                            v-model="form.price"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                        <p v-if="form.errors.price" class="mt-1 text-xs text-red-500">{{ form.errors.price }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Ara (neobvezno)</label>
                        <input
                            v-model="form.deposit_amount"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Opombe stranke</label>
                    <textarea
                        v-model="form.customer_notes"
                        rows="2"
                        placeholder="Karkoli ti je stranka povedala — alergije, želje…"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Interne opombe</label>
                    <textarea
                        v-model="form.internal_notes"
                        rows="2"
                        placeholder="Opombe samo zate"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    />
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800 disabled:opacity-50"
                    >
                        Ustvari naročilo
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
