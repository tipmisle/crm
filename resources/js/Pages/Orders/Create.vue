<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CatalogItemModal from '@/Components/CatalogItemModal.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import CustomerCombobox from '@/Components/CustomerCombobox.vue';
import CustomerContactCard from '@/Components/CustomerContactCard.vue';
import DateInput from '@/Components/DateInput.vue';
import TimeInput from '@/Components/TimeInput.vue';
import { formatMoney } from '@/lib/format';
import type { Conversation, Customer, Product, Service } from '@/types/models';
import { PackagePlus, Plus, Trash2 } from 'lucide-vue-next';

const props = defineProps<{
    customer: Customer | null;
    conversation: Conversation | null;
    products: Product[];
    customers: Customer[];
    selectedProductId?: number | null;
}>();

const needsCustomerPicker = !props.customer && !props.conversation;

const selectedCustomer = computed<Customer | null>(
    () => props.customer ?? props.conversation?.customer ?? props.customers.find((c) => c.id === form.customer_id) ?? null,
);

function changeCustomer() {
    form.customer_id = null;
    form.customer_name = '';
}

function blankItem() {
    const product = props.products.find((p) => p.id === props.selectedProductId);
    return {
        catalog_item_id: product?.id ?? (null as number | null),
        title: product?.name ?? '',
        quantity: 1,
        unit_price: product?.default_price !== null && product?.default_price !== undefined ? Number(product.default_price) : 0,
    };
}

const form = useForm({
    title: props.products.find((p) => p.id === props.selectedProductId)?.name ?? '',
    description: '',
    customer_id: props.customer?.id ?? props.conversation?.customer?.id ?? null,
    customer_name: '',
    conversation_id: props.conversation?.id ?? null,
    due_date: '',
    due_time: '',
    internal_notes: '',
    customer_notes: '',
    items: [blankItem()],
});

const total = computed(() => form.items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_price || 0), 0));

const NEW_PRODUCT = '__new__';
const quickAddOpen = ref(false);
const quickAddRowIndex = ref<number | null>(null);

function addItem() {
    form.items.push({ catalog_item_id: null, title: '', quantity: 1, unit_price: 0 });
}

function removeItem(index: number) {
    form.items.splice(index, 1);
}

function onProductSelectChange(index: number, event: Event) {
    const raw = (event.target as HTMLSelectElement).value;

    if (raw === NEW_PRODUCT) {
        (event.target as HTMLSelectElement).value = String(form.items[index].catalog_item_id ?? '');
        quickAddRowIndex.value = index;
        quickAddOpen.value = true;
        return;
    }

    onProductSelect(index, raw ? Number(raw) : null);
}

function onProductSelect(index: number, productId: number | null) {
    const item = form.items[index];
    item.catalog_item_id = productId;

    const product = props.products.find((p) => p.id === productId);
    if (product) {
        item.title = product.name;
        if (product.default_price !== null) item.unit_price = Number(product.default_price);
    }
}

function onProductSaved(item: Product | Service) {
    if (quickAddRowIndex.value === null) return;

    onProductSelect(quickAddRowIndex.value, item.id);
    quickAddRowIndex.value = null;
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

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-neutral-900">Novo naročilo</h1>
            </div>

            <form @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div class="space-y-6 lg:col-span-2">
                        <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                            <h3 class="text-xs font-semibold text-neutral-800 uppercase">Podrobnosti naročila</h3>

                            <div class="mt-3">
                                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Naslov naročila</label>
                                <input
                                    v-model="form.title"
                                    type="text"
                                    placeholder="npr. Torta za rojstni dan – tema samorog"
                                    class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                                />
                                <p v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</p>
                            </div>

                            <div class="mt-4">
                                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Opis</label>
                                <textarea
                                    v-model="form.description"
                                    rows="3"
                                    class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                                />
                            </div>
                        </section>

                        <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                            <h3 class="text-xs font-semibold text-neutral-800 uppercase">Postavke</h3>

                            <div class="mt-3 space-y-3">
                                <div v-for="(item, index) in form.items" :key="index" class="grid grid-cols-12 items-end gap-2">
                                    <div class="col-span-3">
                                        <label class="block text-xs text-neutral-500">Produkt</label>
                                        <select
                                            :value="item.catalog_item_id"
                                            class="mt-1 w-full rounded-md border border-neutral-200 px-2 py-1.5 text-sm outline-none"
                                            @change="onProductSelectChange(index, $event)"
                                        >
                                            <option :value="null">Brez produkta</option>
                                            <option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }}</option>
                                            <option :value="NEW_PRODUCT">+ Dodaj nov produkt</option>
                                        </select>
                                    </div>
                                    <div class="col-span-4">
                                        <label class="block text-xs text-neutral-500">Naziv postavke</label>
                                        <input v-model="item.title" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-2 py-1.5 text-sm outline-none" />
                                        <p v-if="form.errors[`items.${index}.title`]" class="mt-1 text-xs text-red-500">{{ form.errors[`items.${index}.title`] }}</p>
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs text-neutral-500">Količina</label>
                                        <input v-model.number="item.quantity" type="number" step="0.01" min="0.01" class="mt-1 w-full rounded-md border border-neutral-200 px-2 py-1.5 text-sm outline-none" />
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs text-neutral-500">Cena/kos</label>
                                        <MoneyInput v-model="item.unit_price" class="mt-1 w-full rounded-md border border-neutral-200 px-2 py-1.5 text-sm outline-none" />
                                    </div>
                                    <div class="col-span-1 flex justify-end">
                                        <button
                                            type="button"
                                            :disabled="form.items.length === 1"
                                            class="rounded-md p-1.5 text-neutral-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-30"
                                            @click="removeItem(index)"
                                        >
                                            <Trash2 :size="15" />
                                        </button>
                                    </div>
                                </div>

                                <button type="button" class="flex items-center gap-1.5 text-sm font-medium text-[var(--color-accent-500)] hover:underline" @click="addItem">
                                    <Plus :size="14" /> Dodaj postavko
                                </button>

                                <p v-if="form.errors.items" class="text-xs text-red-600">{{ form.errors.items }}</p>
                            </div>

                            <div class="mt-4 flex justify-end border-t border-neutral-200 pt-3 text-sm font-semibold text-neutral-900">
                                <span>Skupaj: {{ formatMoney(total) }}</span>
                            </div>
                        </section>

                        <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                            <h3 class="text-xs font-semibold text-neutral-800 uppercase">Opombe</h3>

                            <div class="mt-3">
                                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Opombe stranke</label>
                                <textarea
                                    v-model="form.customer_notes"
                                    rows="2"
                                    placeholder="Karkoli ti je stranka povedala — alergije, želje…"
                                    class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                                />
                            </div>

                            <div class="mt-4">
                                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Interne opombe</label>
                                <textarea
                                    v-model="form.internal_notes"
                                    rows="2"
                                    placeholder="Opombe samo zate"
                                    class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                                />
                            </div>
                        </section>
                    </div>

                    <div class="space-y-5">
                        <CustomerContactCard v-if="selectedCustomer" :customer="selectedCustomer" :link-to-customer="Boolean(props.customer || props.conversation?.customer)">
                            <template v-if="needsCustomerPicker" #footer>
                                <button
                                    type="button"
                                    class="mt-3 text-xs font-medium text-neutral-500 hover:text-neutral-700 hover:underline"
                                    @click="changeCustomer"
                                >
                                    Zamenjaj stranko
                                </button>
                            </template>
                        </CustomerContactCard>

                        <section v-if="!selectedCustomer" class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                            <h3 class="text-xs font-semibold text-neutral-800 uppercase">Stranka</h3>

                            <div v-if="form.customer_name" class="mt-3 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                                <span class="font-medium text-neutral-900">{{ form.customer_name }}</span>
                                <span class="ml-1 text-neutral-500">— dodan bo kot nova stranka</span>
                            </div>

                            <div class="mt-3">
                                <CustomerCombobox
                                    v-model:customer-id="form.customer_id"
                                    v-model:customer-name="form.customer_name"
                                    :customers="customers"
                                />
                                <p v-if="form.errors.customer_id" class="mt-1 text-xs text-red-500">{{ form.errors.customer_id }}</p>
                                <p v-if="form.errors.customer_name" class="mt-1 text-xs text-red-500">{{ form.errors.customer_name }}</p>
                            </div>
                        </section>

                        <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                            <h3 class="text-xs font-semibold text-neutral-800 uppercase">Rok</h3>
                            <div class="mt-2 space-y-2">
                                <DateInput v-model="form.due_date" />
                                <TimeInput v-model="form.due_time" />
                            </div>
                        </section>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex w-full items-center justify-center gap-1.5 rounded-md bg-[var(--color-ink-900)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                        >
                            <PackagePlus :size="15" /> Ustvari naročilo
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <CatalogItemModal v-model:open="quickAddOpen" kind="product" @saved="onProductSaved" />
    </AppLayout>
</template>
