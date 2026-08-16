<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import { formatMoney } from '@/lib/format';
import type { Order } from '@/types/models';
import { Plus, Trash2 } from 'lucide-vue-next';

const props = defineProps<{
    order: Order;
    documentableType: 'order' | 'appointment';
    type: 'proforma' | 'invoice';
    settingsConfigured: boolean;
    nextNumberPreview: string;
    vatRegistered: boolean;
    pricesIncludeVat: boolean;
    defaultDueDate: string;
    recipient: {
        name: string | null;
        email: string | null;
        address_line: string | null;
        postal_code: string | null;
        city: string | null;
        country: string | null;
        tax_number: string | null;
    };
    defaultLineItems: Array<{ description: string; quantity: number; unit: string; unit_price: number; vat_rate: number }>;
}>();

const typeLabel = computed(() => (props.type === 'proforma' ? 'Predračun' : 'Račun'));

const form = useForm({
    type: props.type,
    issued_at: new Date().toISOString().slice(0, 10),
    service_date: '',
    due_date: props.defaultDueDate,
    recipient: {
        name: props.recipient.name ?? '',
        email: props.recipient.email ?? '',
        address_line: props.recipient.address_line ?? '',
        postal_code: props.recipient.postal_code ?? '',
        city: props.recipient.city ?? '',
        country: props.recipient.country ?? '',
        tax_number: props.recipient.tax_number ?? '',
    },
    save_recipient_to_customer: false,
    line_items: props.defaultLineItems.map((i) => ({ ...i })),
    notes: '',
});

function addLine() {
    form.line_items.push({ description: '', quantity: 1, unit: 'kos', unit_price: 0, vat_rate: props.vatRegistered ? 22 : 0 });
}

function removeLine(index: number) {
    form.line_items.splice(index, 1);
}

/**
 * Mirrors App\Services\Invoicing\SalesDocumentCalculationService exactly
 * (same gross-first extraction when pricesIncludeVat) so the on-screen
 * total never disagrees with what issuing the document will actually
 * persist. The server recomputes authoritatively on submit — this is
 * display-only.
 */
const lineBreakdown = computed(() =>
    form.line_items.map((item) => {
        const quantity = Number(item.quantity || 0);
        const unitPrice = Number(item.unit_price || 0);
        const vatRate = props.vatRegistered ? Number(item.vat_rate || 0) : 0;
        const lineAmount = quantity * unitPrice;

        if (vatRate <= 0) {
            return { net: lineAmount, vat: 0, gross: lineAmount };
        }

        if (props.pricesIncludeVat) {
            const gross = lineAmount;
            const net = gross / (1 + vatRate / 100);
            return { net, vat: gross - net, gross };
        }

        const net = lineAmount;
        const vat = net * (vatRate / 100);
        return { net, vat, gross: net + vat };
    }),
);

const subtotal = computed(() => lineBreakdown.value.reduce((sum, i) => sum + i.net, 0));
const vatTotal = computed(() => lineBreakdown.value.reduce((sum, i) => sum + i.vat, 0));
const total = computed(() => lineBreakdown.value.reduce((sum, i) => sum + i.gross, 0));
const priceModeLabel = computed(() => (props.vatRegistered ? (props.pricesIncludeVat ? 'z DDV' : 'brez DDV') : null));

function submit() {
    const routeName = props.documentableType === 'appointment' ? 'appointments.documents.store' : 'orders.documents.store';
    form.post(route(routeName, props.order.id));
}

const showRoute = computed(() =>
    route(props.documentableType === 'appointment' ? 'appointments.show' : 'orders.show', props.order.id),
);
</script>

<template>
    <Head :title="`${typeLabel} · ${order.order_number}`" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="showRoute" class="text-sm text-neutral-400 hover:text-neutral-600">{{ order.order_number }}</Link>
                <span class="text-neutral-300">/</span>
                <span class="text-sm font-semibold text-neutral-900">Izstavi {{ typeLabel.toLowerCase() }}</span>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-4 px-4 py-6 sm:px-6">
            <p v-if="!settingsConfigured" class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Najprej nastavi
                <Link :href="route('settings.invoicing.edit')" class="font-medium underline">podatke o računih</Link>
                (naziv podjetja in IBAN).
            </p>

            <SectionCard :title="typeLabel" :subtitle="`Naslednja številka: ${nextNumberPreview}`">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs text-neutral-500">Datum izdaje</label>
                        <input v-model="form.issued_at" type="date" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs text-neutral-500">Datum opravljene storitve (neobvezno)</label>
                        <input v-model="form.service_date" type="date" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs text-neutral-500">Rok plačila</label>
                        <input v-model="form.due_date" type="date" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none" />
                    </div>
                </div>
            </SectionCard>

            <SectionCard title="Prejemnik">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="block text-xs text-neutral-500">Ime / naziv</label>
                        <input v-model="form.recipient.name" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none" :class="form.errors['recipient.name'] && 'border-red-300'" />
                        <p v-if="form.errors['recipient.name']" class="mt-1 text-xs text-red-600">{{ form.errors['recipient.name'] }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-neutral-500">E-pošta</label>
                        <input v-model="form.recipient.email" type="email" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs text-neutral-500">Davčna št. (neobvezno)</label>
                        <input v-model="form.recipient.tax_number" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs text-neutral-500">Naslov</label>
                        <input v-model="form.recipient.address_line" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs text-neutral-500">Poštna številka</label>
                        <input v-model="form.recipient.postal_code" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs text-neutral-500">Kraj</label>
                        <input v-model="form.recipient.city" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none" />
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <input id="save_recipient" v-model="form.save_recipient_to_customer" type="checkbox" class="h-4 w-4 rounded border-neutral-300" />
                    <label for="save_recipient" class="text-sm text-neutral-700">Shrani podatke pri stranki</label>
                </div>
            </SectionCard>

            <SectionCard title="Postavke" :subtitle="priceModeLabel ? `Cene na postavkah so vnesene ${priceModeLabel}.` : undefined">
                <div class="space-y-3">
                    <div v-for="(item, index) in form.line_items" :key="index" class="grid grid-cols-12 items-end gap-2">
                        <div class="col-span-4">
                            <label class="block text-xs text-neutral-500">Opis</label>
                            <input v-model="item.description" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-2 py-1.5 text-sm outline-none" />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs text-neutral-500">Količina</label>
                            <input v-model.number="item.quantity" type="number" step="0.01" class="mt-1 w-full rounded-md border border-neutral-200 px-2 py-1.5 text-sm outline-none" />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs text-neutral-500">Enota</label>
                            <input v-model="item.unit" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-2 py-1.5 text-sm outline-none" />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs text-neutral-500">Cena/enoto{{ priceModeLabel ? ` (${priceModeLabel})` : '' }}</label>
                            <input v-model.number="item.unit_price" type="number" step="0.01" class="mt-1 w-full rounded-md border border-neutral-200 px-2 py-1.5 text-sm outline-none" />
                        </div>
                        <div v-if="vatRegistered" class="col-span-1">
                            <label class="block text-xs text-neutral-500">DDV %</label>
                            <input v-model.number="item.vat_rate" type="number" step="1" class="mt-1 w-full rounded-md border border-neutral-200 px-2 py-1.5 text-sm outline-none" />
                        </div>
                        <div class="col-span-1 flex justify-end">
                            <button type="button" class="rounded-md p-1.5 text-neutral-400 hover:bg-red-50 hover:text-red-600" @click="removeLine(index)">
                                <Trash2 :size="15" />
                            </button>
                        </div>
                    </div>

                    <button type="button" class="flex items-center gap-1.5 text-sm font-medium text-[var(--color-accent-500)] hover:underline" @click="addLine">
                        <Plus :size="14" /> Dodaj postavko
                    </button>

                    <p v-if="form.errors.line_items" class="text-xs text-red-600">{{ form.errors.line_items }}</p>
                </div>

                <div class="mt-5 ml-auto w-full max-w-xs space-y-1.5 text-sm">
                    <div v-if="vatRegistered" class="flex justify-between text-neutral-500">
                        <span>Osnova</span><span>{{ formatMoney(subtotal) }}</span>
                    </div>
                    <div v-if="vatRegistered" class="flex justify-between text-neutral-500">
                        <span>DDV</span><span>{{ formatMoney(vatTotal) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-neutral-200 pt-1.5 font-semibold text-neutral-900">
                        <span>Skupaj</span><span>{{ formatMoney(total) }}</span>
                    </div>
                </div>
            </SectionCard>

            <SectionCard title="Opombe (neobvezno)">
                <textarea v-model="form.notes" rows="2" class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none" />
            </SectionCard>

            <p v-if="Object.keys(form.errors).length" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                Preveri vnesene podatke — nekaj polj ni veljavnih.
            </p>

            <div class="flex items-center gap-3">
                <button
                    type="button"
                    :disabled="form.processing || !settingsConfigured"
                    class="rounded-md bg-[var(--color-ink-900)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                    @click="submit"
                >
                    Izdaj {{ typeLabel.toLowerCase() }}
                </button>
                <Link :href="showRoute" class="text-sm text-neutral-500 hover:text-neutral-700">Prekliči</Link>
            </div>
        </div>
    </AppLayout>
</template>
