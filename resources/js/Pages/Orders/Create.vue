<script setup lang="ts">
import { ref, watch } from 'vue';
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
    products: Product[];
    customers: Customer[];
    selectedProductId?: number | null;
}>();

const contactName =
    props.customer?.full_name ??
    props.conversation?.customer?.full_name ??
    props.conversation?.customer_display_name ??
    undefined;

const needsCustomerPicker = !props.customer && !props.conversation;

const form = useForm({
    catalog_item_id: props.selectedProductId ?? (null as number | null),
    title: '',
    description: '',
    customer_id: props.customer?.id ?? props.conversation?.customer?.id ?? null,
    customer_name: '',
    conversation_id: props.conversation?.id ?? null,
    due_date: '',
    due_time: '',
    price: '',
    deposit_amount: '',
    internal_notes: '',
    customer_notes: '',
});

const NEW_PRODUCT = '__new__';
const productSelect = ref<number | string | null>(form.catalog_item_id);
const quickAddOpen = ref(false);

watch(productSelect, (value) => {
    if (value === NEW_PRODUCT) {
        quickAddOpen.value = true;
        // Selection reverts once the modal closes without a save — the
        // <select> shouldn't stay stuck on the "+ Dodaj nov produkt" row.
        productSelect.value = form.catalog_item_id;
        return;
    }

    form.catalog_item_id = value as number | null;
});

// Only (re)populate defaults when the selected product itself changes — a
// manual edit to price/deposit/title afterwards must never be clobbered by
// an unrelated field change (see Appointments/Create's service watcher).
watch(
    () => form.catalog_item_id,
    (id) => {
        const product = props.products.find((p) => p.id === id);
        if (!product) return;

        form.title = product.name;
        if (product.default_price !== null) form.price = String(product.default_price);
        if (product.default_deposit_amount !== null) form.deposit_amount = String(product.default_deposit_amount);
        if (product.description) form.description = product.description;
    },
    { immediate: true },
);

function onProductSaved(item: Product | Service) {
    form.catalog_item_id = item.id;
    productSelect.value = item.id;
}

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

        <div class="mx-auto max-w-2xl px-4 py-6 sm:px-6 sm:py-8">
            <h1 class="text-2xl font-semibold text-neutral-900">Novo naročilo</h1>

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
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Produkt</label>
                    <select
                        v-model="productSelect"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    >
                        <option :value="null">Izberi produkt (neobvezno)</option>
                        <option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }}</option>
                        <option :value="NEW_PRODUCT">+ Dodaj nov produkt</option>
                    </select>
                </div>

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

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Rok</label>
                        <DateInput v-model="form.due_date" />
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

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                        class="w-full rounded-md bg-[var(--color-ink-900)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50 sm:w-auto"
                    >
                        Ustvari naročilo
                    </button>
                </div>
            </form>
        </div>

        <CatalogItemModal v-model:open="quickAddOpen" kind="product" @saved="onProductSaved" />
    </AppLayout>
</template>
