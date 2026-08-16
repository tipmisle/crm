<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import Avatar from '@/Components/Avatar.vue';
import EditCustomerModal from '@/Components/EditCustomerModal.vue';
import type { Customer } from '@/types/models';
import { Pencil } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        customer: Customer;
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

const address = computed(() => {
    const cityLine = [props.customer.postal_code, props.customer.city].filter(Boolean).join(' ');

    return [props.customer.address_line, cityLine].filter(Boolean).join(', ');
});
</script>

<template>
    <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-semibold text-neutral-500 uppercase">{{ title }}</h3>
            <button type="button" class="flex items-center gap-1 text-xs font-medium text-neutral-500 hover:text-neutral-700" @click="editOpen = true">
                <Pencil :size="12" /> Uredi
            </button>
        </div>

        <component
            :is="linkToCustomer ? Link : 'div'"
            :href="linkToCustomer ? route('customers.show', customer.id) : undefined"
            class="mt-3 flex items-center gap-3 rounded-lg -mx-2 px-2 py-1.5"
            :class="linkToCustomer ? 'hover:bg-neutral-50' : ''"
        >
            <Avatar :name="customer.full_name" size="md" />
            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-neutral-900">{{ customer.full_name }}</p>
                <p class="truncate text-xs text-neutral-500">{{ customer.email }}</p>
            </div>
        </component>

        <div v-if="customer.phone || address" class="mt-3 space-y-1 border-t border-neutral-100 pt-3">
            <p v-if="customer.phone" class="text-xs text-neutral-600">{{ customer.phone }}</p>
            <p v-if="address" class="text-xs text-neutral-600">{{ address }}</p>
        </div>

        <p v-if="customer.notes" class="mt-3 rounded-md bg-neutral-50 p-2.5 text-xs text-neutral-600">{{ customer.notes }}</p>
    </section>

    <EditCustomerModal :show="editOpen" :customer="customer" :show-name="showName" :show-notes="showNotes" @close="editOpen = false" />
</template>
