<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import CatalogItemModal from '@/Components/CatalogItemModal.vue';
import { formatMoney } from '@/lib/format';
import { Plus, Pencil, Trash2 } from 'lucide-vue-next';
import type { PageProps } from '@/types';
import type { Product, Service } from '@/types/models';

const props = defineProps<{
    products: Product[];
    services: Service[];
}>();

const page = usePage<PageProps>();
const workspace = computed(() => page.props.workspace);

const showBothTabs = computed(() => !!workspace.value?.orders_enabled && !!workspace.value?.appointments_enabled);
const activeTab = ref<'products' | 'services'>(workspace.value?.orders_enabled ? 'products' : 'services');

const modalOpen = ref(false);
const modalKind = ref<'product' | 'service'>('product');
const modalItem = ref<Product | Service | null>(null);

function openCreate(kind: 'product' | 'service') {
    modalKind.value = kind;
    modalItem.value = null;
    modalOpen.value = true;
}

function openEdit(kind: 'product' | 'service', item: Product | Service) {
    modalKind.value = kind;
    modalItem.value = item;
    modalOpen.value = true;
}

function toggleActive(kind: 'product' | 'service', item: Product | Service) {
    const routeName = kind === 'product' ? 'products.update' : 'services.update';
    router.patch(route(routeName, item.id), { active: !item.active }, { preserveScroll: true });
}

function destroy(kind: 'product' | 'service', item: Product | Service) {
    const label = kind === 'product' ? 'produkt' : 'storitev';
    if (!confirm(`Izbriši ${label} "${item.name}"?`)) return;

    const routeName = kind === 'product' ? 'products.destroy' : 'services.destroy';
    router.delete(route(routeName, item.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Ponudba" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Ponudba</h1>
        </template>

        <div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6 sm:py-8">
            <div>
                <h1 class="text-2xl font-semibold text-neutral-900">Ponudba</h1>
                <p class="mt-1 text-sm text-neutral-500">Kar tvoje podjetje prodaja — nastavi enkrat, uporabi pri vsakem naročilu ali terminu.</p>
            </div>

            <div v-if="showBothTabs" class="flex gap-1 rounded-lg bg-neutral-100 p-1 text-sm font-medium">
                <button
                    type="button"
                    class="flex-1 rounded-md px-3 py-1.5 transition"
                    :class="activeTab === 'products' ? 'bg-white text-neutral-900 shadow-sm' : 'text-neutral-500 hover:text-neutral-700'"
                    @click="activeTab = 'products'"
                >
                    Produkti
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-md px-3 py-1.5 transition"
                    :class="activeTab === 'services' ? 'bg-white text-neutral-900 shadow-sm' : 'text-neutral-500 hover:text-neutral-700'"
                    @click="activeTab = 'services'"
                >
                    Storitve
                </button>
            </div>

            <SectionCard
                v-if="!showBothTabs ? workspace?.orders_enabled : activeTab === 'products'"
                title="Produkti"
                subtitle="Izdelki, ki jih ponujaš strankam ob naročilu"
            >
                <template #actions>
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-md bg-[var(--color-ink-900)] px-3 py-1.5 text-sm font-medium text-white hover:bg-[var(--color-ink-800)]"
                        @click="openCreate('product')"
                    >
                        <Plus :size="14" /> Dodaj produkt
                    </button>
                </template>

                <div class="space-y-2">
                    <div
                        v-for="product in products"
                        :key="product.id"
                        class="flex items-center justify-between rounded-lg border border-neutral-200 px-4 py-3"
                        :class="!product.active && 'opacity-50'"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-neutral-900">{{ product.name }}</p>
                            <p class="text-xs text-neutral-500">
                                <span v-if="product.default_price !== null">{{ formatMoney(product.default_price) }}</span>
                                <span v-if="product.default_deposit_amount !== null"> · ara {{ formatMoney(product.default_deposit_amount) }}</span>
                                <span v-if="!product.active" class="ml-1">· neaktiven</span>
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <button type="button" class="text-neutral-400 hover:text-neutral-700" @click="openEdit('product', product)">
                                <Pencil :size="14" />
                            </button>
                            <button
                                type="button"
                                class="text-xs font-medium text-neutral-500 hover:text-neutral-700"
                                @click="toggleActive('product', product)"
                            >
                                {{ product.active ? 'Deaktiviraj' : 'Aktiviraj' }}
                            </button>
                            <button type="button" class="text-neutral-400 hover:text-red-600" @click="destroy('product', product)">
                                <Trash2 :size="14" />
                            </button>
                        </div>
                    </div>
                    <p v-if="!products.length" class="rounded-lg border border-dashed border-neutral-200 px-4 py-3 text-sm text-neutral-400">
                        Še ni produktov.
                    </p>
                </div>
            </SectionCard>

            <SectionCard
                v-if="!showBothTabs ? workspace?.appointments_enabled : activeTab === 'services'"
                title="Storitve"
                subtitle="Vnaprej pripravljene storitve za hitro rezervacijo terminov"
            >
                <template #actions>
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-md bg-[var(--color-ink-900)] px-3 py-1.5 text-sm font-medium text-white hover:bg-[var(--color-ink-800)]"
                        @click="openCreate('service')"
                    >
                        <Plus :size="14" /> Dodaj storitev
                    </button>
                </template>

                <div class="space-y-2">
                    <div
                        v-for="service in services"
                        :key="service.id"
                        class="flex items-center justify-between rounded-lg border border-neutral-200 px-4 py-3"
                        :class="!service.active && 'opacity-50'"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-neutral-900">{{ service.name }}</p>
                            <p class="text-xs text-neutral-500">
                                {{ service.default_duration_minutes }} min
                                <span v-if="service.default_price !== null"> · {{ formatMoney(service.default_price) }}</span>
                                <span v-if="service.default_deposit_amount !== null"> · ara {{ formatMoney(service.default_deposit_amount) }}</span>
                                <span v-if="!service.active" class="ml-1">· neaktivna</span>
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <button type="button" class="text-neutral-400 hover:text-neutral-700" @click="openEdit('service', service)">
                                <Pencil :size="14" />
                            </button>
                            <button
                                type="button"
                                class="text-xs font-medium text-neutral-500 hover:text-neutral-700"
                                @click="toggleActive('service', service)"
                            >
                                {{ service.active ? 'Deaktiviraj' : 'Aktiviraj' }}
                            </button>
                            <button type="button" class="text-neutral-400 hover:text-red-600" @click="destroy('service', service)">
                                <Trash2 :size="14" />
                            </button>
                        </div>
                    </div>
                    <p v-if="!services.length" class="rounded-lg border border-dashed border-neutral-200 px-4 py-3 text-sm text-neutral-400">
                        Še ni storitev.
                    </p>
                </div>
            </SectionCard>
        </div>

        <CatalogItemModal v-model:open="modalOpen" :kind="modalKind" :item="modalItem" />
    </AppLayout>
</template>
