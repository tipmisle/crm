<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import { formatMoney } from '@/lib/format';

defineProps<{
    workspace: { id: number; name: string };
    order: {
        id: number;
        order_number: string;
        title: string;
        price: string | number;
        status: string;
        customer: { id: number; full_name: string } | null;
        notes: Array<{ id: number; body: string }>;
    };
}>();
</script>

<template>
    <Head title="Podpora · Naročilo" />

    <AdminLayout>
        <h1 class="mb-1 text-lg font-semibold text-neutral-900">{{ order.title }} · {{ order.order_number }}</h1>
        <p class="mb-4 text-xs text-neutral-500">{{ workspace.name }} — ta vsebina je vidna samo med aktivno sejo podpore in je bila zabeležena v dnevniku revizije.</p>

        <SectionCard title="Podrobnosti">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-neutral-500">Stranka</dt><dd>{{ order.customer?.full_name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500">Cena</dt><dd>{{ formatMoney(order.price) }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500">Stanje</dt><dd>{{ order.status }}</dd></div>
            </dl>
        </SectionCard>
    </AdminLayout>
</template>
