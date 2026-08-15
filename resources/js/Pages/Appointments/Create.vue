<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import CatalogItemModal from '@/Components/CatalogItemModal.vue';
import CustomerCombobox from '@/Components/CustomerCombobox.vue';
import DateInput from '@/Components/DateInput.vue';
import type { Conversation, Customer, Product, Service } from '@/types/models';

const props = defineProps<{
    customer: Customer | null;
    conversation: Conversation | null;
    services: Service[];
    customers: Customer[];
}>();

const contactName =
    props.customer?.full_name ??
    props.conversation?.customer?.full_name ??
    props.conversation?.customer_display_name ??
    undefined;

const needsCustomerPicker = !props.customer && !props.conversation;

const form = useForm({
    service_id: null as number | null,
    service_name: '',
    description: '',
    customer_id: props.customer?.id ?? props.conversation?.customer?.id ?? null,
    customer_name: '',
    conversation_id: props.conversation?.id ?? null,
    appointment_date: '',
    start_time: '',
    duration_minutes: 60,
    price: '',
    deposit_amount: '',
    internal_notes: '',
    customer_notes: '',
});

const NEW_SERVICE = '__new__';
const serviceSelect = ref<number | string | null>(form.service_id);
const quickAddOpen = ref(false);

watch(serviceSelect, (value) => {
    if (value === NEW_SERVICE) {
        quickAddOpen.value = true;
        // Selection reverts once the modal closes without a save — the
        // <select> shouldn't stay stuck on the "+ Dodaj novo storitev" row.
        serviceSelect.value = form.service_id;
        return;
    }

    form.service_id = value as number | null;
});

watch(
    () => form.service_id,
    (id) => {
        const service = props.services.find((s) => s.id === id);
        if (!service) return;

        form.service_name = service.name;
        form.duration_minutes = service.default_duration_minutes;
        if (service.default_price !== null) form.price = String(service.default_price);
        if (service.default_deposit_amount !== null) form.deposit_amount = String(service.default_deposit_amount);
    },
);

function onServiceSaved(item: Product | Service) {
    form.service_id = item.id;
    serviceSelect.value = item.id;
}

const durationOptions = [15, 30, 45, 60, 75, 90, 120, 150, 180, 240];

function submit() {
    form.post(route('appointments.store'));
}
</script>

<template>
    <Head title="Nov termin" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Nov termin</h1>
        </template>

        <div class="mx-auto max-w-2xl px-4 py-6 sm:px-6 sm:py-8">
            <h1 class="text-2xl font-semibold text-neutral-900">Nov termin</h1>

            <div v-if="contactName" class="mt-3 flex items-center gap-2 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5">
                <Avatar :name="contactName" size="sm" />
                <div class="text-sm">
                    <span class="font-medium text-neutral-900">{{ contactName }}</span>
                    <span v-if="!customer" class="ml-1 text-neutral-500">— dodan bo kot nova stranka</span>
                </div>
            </div>

            <form
                class="mt-6 space-y-5 rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-4 sm:p-6"
                @submit.prevent="submit"
            >
                <div v-if="needsCustomerPicker">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Stranka</label>
                    <CustomerCombobox
                        v-model:customer-id="form.customer_id"
                        v-model:customer-name="form.customer_name"
                        :customers="customers"
                    />
                    <p v-if="form.errors.customer_id" class="mt-1 text-xs text-red-500">{{ form.errors.customer_id }}</p>
                    <p v-if="form.errors.customer_name" class="mt-1 text-xs text-red-500">{{ form.errors.customer_name }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Storitev</label>
                    <select
                        v-model="serviceSelect"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    >
                        <option :value="null">Izberi storitev (neobvezno)</option>
                        <option v-for="service in services" :key="service.id" :value="service.id">{{ service.name }}</option>
                        <option :value="NEW_SERVICE">+ Dodaj novo storitev</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Ime termina</label>
                    <input
                        v-model="form.service_name"
                        type="text"
                        placeholder="npr. Gel nohti"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    />
                    <p v-if="form.errors.service_name" class="mt-1 text-xs text-red-500">{{ form.errors.service_name }}</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Datum</label>
                        <DateInput v-model="form.appointment_date" />
                        <p v-if="form.errors.appointment_date" class="mt-1 text-xs text-red-500">{{ form.errors.appointment_date }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Ura</label>
                        <input
                            v-model="form.start_time"
                            type="time"
                            class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                        <p v-if="form.errors.start_time" class="mt-1 text-xs text-red-500">{{ form.errors.start_time }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Trajanje</label>
                    <select
                        v-model.number="form.duration_minutes"
                        class="w-full max-w-[10rem] rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    >
                        <option v-for="d in durationOptions" :key="d" :value="d">{{ d }} min</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Cena (neobvezno)</label>
                        <input
                            v-model="form.price"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
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
                        class="w-full rounded-md bg-[var(--color-ink-900)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50 sm:w-auto"
                    >
                        Rezerviraj termin
                    </button>
                </div>
            </form>
        </div>

        <CatalogItemModal v-model:open="quickAddOpen" kind="service" @saved="onServiceSaved" />
    </AppLayout>
</template>
