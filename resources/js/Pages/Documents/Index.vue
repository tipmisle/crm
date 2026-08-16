<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatMoney, formatDate } from '@/lib/format';
import type { SalesDocument } from '@/types/models';
import { Search, FileText } from 'lucide-vue-next';

const props = defineProps<{
    documents: { data: SalesDocument[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: {
        search?: string;
        type?: string;
        sent?: string;
        source?: string;
        issued_from?: string;
        issued_to?: string;
    };
}>();

const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? '');
const sent = ref(props.filters.sent ?? '');
const source = ref(props.filters.source ?? '');
const issuedFrom = ref(props.filters.issued_from ?? '');
const issuedTo = ref(props.filters.issued_to ?? '');

function applyFilters() {
    router.get(
        route('documents.index'),
        {
            search: search.value || undefined,
            type: type.value || undefined,
            sent: sent.value || undefined,
            source: source.value || undefined,
            issued_from: issuedFrom.value || undefined,
            issued_to: issuedTo.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

let debounce: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 300);
});
watch([type, sent, source, issuedFrom, issuedTo], applyFilters);

function typeLabel(doc: SalesDocument): string {
    return doc.type === 'proforma' ? 'Predračun' : doc.type === 'invoice' ? 'Račun' : doc.type === 'storno' ? 'Dobropis (storno)' : 'Drugo';
}

function subjectRoute(doc: SalesDocument): string | null {
    if (doc.order) return route('orders.show', doc.order.id);
    if (doc.appointment) return route('appointments.show', doc.appointment.id);
    return null;
}

function subjectLabel(doc: SalesDocument): string | null {
    if (doc.order) return `${doc.order.order_number} · ${doc.order.title}`;
    if (doc.appointment) return `${doc.appointment.appointment_number} · ${doc.appointment.service_name}`;
    return null;
}
</script>

<template>
    <Head title="Dokumenti" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Dokumenti</h1>
        </template>

        <div class="mx-auto max-w-6xl space-y-5 px-4 py-6 sm:px-6 sm:py-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-neutral-900">Dokumenti</h1>
                    <p class="mt-1 text-sm text-neutral-500">Vsi izdani in priloženi dokumenti iz naročil in terminov na enem mestu.</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative min-w-56 flex-1">
                    <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Iskanje po številki, stranki, naročilu/terminu…"
                        class="w-full rounded-md border border-neutral-200 py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-400"
                    />
                </div>

                <select v-model="type" class="rounded-md border border-neutral-200 px-3 py-2 text-sm text-neutral-600 outline-none">
                    <option value="">Vse vrste</option>
                    <option value="invoice">Račun</option>
                    <option value="proforma">Predračun</option>
                    <option value="storno">Dobropis (storno)</option>
                    <option value="other">Drugo</option>
                </select>

                <select v-model="sent" class="rounded-md border border-neutral-200 px-3 py-2 text-sm text-neutral-600 outline-none">
                    <option value="">Poslano — vsi</option>
                    <option value="sent">Poslano</option>
                    <option value="not_sent">Ni poslano</option>
                </select>

                <select v-model="source" class="rounded-md border border-neutral-200 px-3 py-2 text-sm text-neutral-600 outline-none">
                    <option value="">Vir — vsi</option>
                    <option value="issued">Izdano v Beležki</option>
                    <option value="external">Zunanji dokument</option>
                </select>

                <input v-model="issuedFrom" type="date" class="rounded-md border border-neutral-200 px-3 py-2 text-sm text-neutral-600 outline-none" />
                <span class="text-xs text-neutral-400">–</span>
                <input v-model="issuedTo" type="date" class="rounded-md border border-neutral-200 px-3 py-2 text-sm text-neutral-600 outline-none" />
            </div>

            <div v-if="documents.data.length" class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04]">
                <table class="w-full text-sm">
                    <thead class="border-b border-neutral-100 bg-neutral-50 text-left text-xs font-medium text-neutral-500">
                        <tr>
                            <th class="px-4 py-2.5">Dokument</th>
                            <th class="px-4 py-2.5">Vrsta</th>
                            <th class="px-4 py-2.5">Stranka</th>
                            <th class="px-4 py-2.5">Povezano</th>
                            <th class="px-4 py-2.5">Izdano</th>
                            <th class="px-4 py-2.5">Znesek</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5">Poslano</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="doc in documents.data" :key="doc.id" class="border-b border-neutral-50 last:border-0 hover:bg-neutral-50">
                            <td class="px-4 py-3">
                                <a
                                    :href="route('documents.download', doc.id)"
                                    target="_blank"
                                    class="flex items-center gap-1.5 font-medium text-neutral-800 hover:text-[var(--color-accent-600)] hover:underline"
                                    title="Odpri / prenesi dokument"
                                >
                                    <FileText :size="14" class="shrink-0 text-neutral-400" />
                                    {{ doc.document_number ?? doc.external_document_number ?? '—' }}
                                </a>
                                <p v-if="doc.type === 'storno' && doc.corrects_document" class="mt-0.5 text-xs text-neutral-400">
                                    Storno računa {{ doc.corrects_document.document_number }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ typeLabel(doc) }}
                                <span v-if="doc.source === 'external'" class="ml-1 text-xs text-neutral-400">(zunanji)</span>
                            </td>
                            <td class="px-4 py-3">
                                <Link v-if="doc.customer" :href="route('customers.show', doc.customer.id)" class="text-neutral-700 hover:text-[var(--color-accent-600)] hover:underline">
                                    {{ doc.customer.full_name }}
                                </Link>
                                <span v-else class="text-neutral-400">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <Link v-if="subjectRoute(doc)" :href="subjectRoute(doc)!" class="text-neutral-600 hover:text-[var(--color-accent-600)] hover:underline">
                                    {{ subjectLabel(doc) }}
                                </Link>
                                <span v-else class="text-neutral-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-neutral-500">{{ formatDate(doc.issued_at) }}</td>
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ formatMoney(doc.total) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="doc.status_label"
                                    class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700"
                                >
                                    {{ doc.status_label }}
                                </span>
                                <span v-else class="text-neutral-500">Izdano</span>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="doc.sent_at" class="text-neutral-600">{{ formatDate(doc.sent_at) }}</span>
                                <span v-else class="text-neutral-400">Ni poslano</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState v-else title="Še ni dokumentov." description="Predračune in račune izdaš neposredno iz naročila ali termina — tu se zbirajo vsi skupaj.">
                <template #icon><FileText :size="28" /></template>
            </EmptyState>

            <Pagination :links="documents.links" />
        </div>
    </AppLayout>
</template>
