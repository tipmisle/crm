<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import Avatar from '@/Components/Avatar.vue';
import EditCustomerModal from '@/Components/EditCustomerModal.vue';
import CustomerQuickViewModal from '@/Components/CustomerQuickViewModal.vue';
import { Pencil, Eye, Phone, MapPin, Building2 } from 'lucide-vue-next';

interface ContactCardCustomer {
    id: number;
    full_name: string;
    email: string | null;
    phone: string | null;
    address_line?: string | null;
    postal_code?: string | null;
    city?: string | null;
    country?: string | null;
    tax_number?: string | null;
    is_business?: boolean;
    company_name?: string | null;
    vat_registered?: boolean;
    first_contacted_at?: string | null;
    notes: string | null;
}

const props = withDefaults(
    defineProps<{
        customer: ContactCardCustomer;
        title?: string;
        linkToCustomer?: boolean;
        showName?: boolean;
        showNotes?: boolean;
    }>(),
    {
        title: 'Stranka',
        linkToCustomer: true,
        showName: false,
        showNotes: false,
    },
);

const editOpen = ref(false);
const quickViewOpen = ref(false);

const address = computed(() => {
    const cityLine = [props.customer.postal_code, props.customer.city].filter(Boolean).join(' ');

    return [props.customer.address_line, cityLine].filter(Boolean).join(', ');
});
</script>

<template>
    <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-3">
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-semibold text-neutral-800 uppercase">{{ title }}</h3>
            <div class="flex items-center gap-2.5">
                <button
                    v-if="linkToCustomer"
                    type="button"
                    title="Hitri pregled stranke"
                    class="text-neutral-400 hover:text-neutral-700"
                    @click="quickViewOpen = true"
                >
                    <Eye :size="15" />
                </button>
                <button type="button" title="Uredi stranko" class="text-neutral-400 hover:text-neutral-700" @click="editOpen = true">
                    <Pencil :size="15" />
                </button>
            </div>
        </div>

        <component
            :is="linkToCustomer ? Link : 'div'"
            :href="linkToCustomer ? route('customers.show', customer.id) : undefined"
            class="mt-3 flex items-center gap-3 rounded-lg -mx-2 px-2 py-1.5"
            :class="linkToCustomer ? 'hover:bg-neutral-50' : ''"
        >
            <Avatar :name="customer.full_name" size="md" />
            <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                    <p class="truncate text-sm font-medium text-neutral-900">{{ customer.full_name }}</p>
                    <span
                        v-if="customer.is_business"
                        class="flex shrink-0 items-center gap-1 rounded-full bg-[var(--color-accent-50)] px-1.5 py-0.5 text-[10px] font-medium text-[var(--color-accent-700)]"
                    >
                        <Building2 :size="10" /> Podjetje
                    </span>
                </div>
                <p v-if="customer.company_name" class="truncate text-xs font-medium text-neutral-600">{{ customer.company_name }}</p>
                <p class="truncate text-xs text-neutral-500">{{ customer.email }}</p>
            </div>
        </component>

        <div v-if="customer.phone || address" class="mt-3 space-y-1.5 border-t border-neutral-100 pt-3">
            <p v-if="customer.phone" class="flex items-center gap-1.5 text-xs text-neutral-600">
                <Phone :size="12" class="shrink-0 text-neutral-400" /> {{ customer.phone }}
            </p>
            <p v-if="address" class="flex items-center gap-1.5 text-xs text-neutral-600">
                <MapPin :size="12" class="shrink-0 text-neutral-400" /> {{ address }}
            </p>
        </div>

        <p v-if="customer.notes" class="mt-3 rounded-md bg-neutral-50 p-2.5 text-xs text-neutral-600">{{ customer.notes }}</p>

        <slot name="footer" />
    </section>

    <EditCustomerModal :show="editOpen" :customer="customer" :show-name="showName" :show-notes="showNotes" @close="editOpen = false" />

    <CustomerQuickViewModal
        :show="quickViewOpen"
        :customer="customer"
        @close="quickViewOpen = false"
        @edit="
            quickViewOpen = false;
            editOpen = true;
        "
    />
</template>
