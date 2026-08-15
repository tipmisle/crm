<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { onClickOutside } from '@vueuse/core';
import { Search } from 'lucide-vue-next';
import type { Customer } from '@/types/models';

const props = defineProps<{
    customers: Customer[];
    customerId: number | null;
    customerName: string;
}>();

const emit = defineEmits<{
    (e: 'update:customerId', value: number | null): void;
    (e: 'update:customerName', value: string): void;
}>();

const selected = computed(() => props.customers.find((c) => c.id === props.customerId) ?? null);

const query = ref(selected.value?.full_name ?? props.customerName ?? '');
const open = ref(false);
const root = ref<HTMLElement | null>(null);

watch(
    () => props.customerId,
    (id) => {
        if (id === null) return;
        const customer = props.customers.find((c) => c.id === id);
        if (customer) query.value = customer.full_name;
    },
);

const results = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.customers.slice(0, 8);
    return props.customers.filter((c) => c.full_name.toLowerCase().includes(q)).slice(0, 8);
});

const exactMatch = computed(() => props.customers.some((c) => c.full_name.toLowerCase() === query.value.trim().toLowerCase()));

function onInput() {
    open.value = true;
    emit('update:customerId', null);
    emit('update:customerName', query.value.trim());
}

function selectCustomer(customer: Customer) {
    query.value = customer.full_name;
    open.value = false;
    emit('update:customerId', customer.id);
    emit('update:customerName', '');
}

function useTypedAsNew() {
    open.value = false;
    emit('update:customerId', null);
    emit('update:customerName', query.value.trim());
}

onClickOutside(root, () => {
    open.value = false;
});
</script>

<template>
    <div ref="root" class="relative">
        <div class="relative">
            <Search :size="15" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-neutral-400" />
            <input
                v-model="query"
                type="text"
                placeholder="Poišči ali vpiši ime stranke…"
                class="w-full rounded-md border border-neutral-200 py-2 pl-8 pr-3 text-sm outline-none focus:border-neutral-400"
                @input="onInput"
                @focus="open = true"
            />
        </div>

        <div
            v-if="open && (results.length || query.trim())"
            class="absolute z-10 mt-1 w-full overflow-hidden rounded-md border border-neutral-200 bg-white shadow-lg"
        >
            <ul class="max-h-56 overflow-y-auto py-1">
                <li v-for="c in results" :key="c.id">
                    <button
                        type="button"
                        class="flex w-full cursor-pointer items-center justify-between px-3 py-2 text-left text-sm text-neutral-700 hover:bg-neutral-50"
                        @click="selectCustomer(c)"
                    >
                        <span class="font-medium text-neutral-900">{{ c.full_name }}</span>
                        <span v-if="c.phone" class="text-xs text-neutral-400">{{ c.phone }}</span>
                    </button>
                </li>
            </ul>
            <button
                v-if="query.trim() && !exactMatch"
                type="button"
                class="flex w-full cursor-pointer items-center gap-1.5 border-t border-neutral-100 px-3 py-2 text-left text-sm font-medium text-neutral-900 hover:bg-neutral-50"
                @click="useTypedAsNew"
            >
                + Dodaj novo stranko "{{ query.trim() }}"
            </button>
        </div>
    </div>
</template>
