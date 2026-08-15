<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import { formatMoney, formatDate } from '@/lib/format';

defineProps<{
    workspace: { id: number; name: string };
    appointment: {
        id: number;
        appointment_number: string | null;
        service_name: string;
        appointment_date: string;
        start_time: string;
        status: string;
        price: string | number | null;
        customer: { id: number; full_name: string } | null;
        internal_notes: string | null;
        customer_notes: string | null;
    };
}>();
</script>

<template>
    <Head title="Podpora · Termin" />

    <AdminLayout>
        <h1 class="mb-1 text-lg font-semibold text-neutral-900">{{ appointment.service_name }} · {{ appointment.appointment_number ?? '#' + appointment.id }}</h1>
        <p class="mb-4 text-xs text-neutral-500">{{ workspace.name }} — ta vsebina je vidna samo med aktivno sejo podpore in je bila zabeležena v dnevniku revizije.</p>

        <SectionCard title="Podrobnosti">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-neutral-500">Stranka</dt><dd>{{ appointment.customer?.full_name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500">Datum</dt><dd>{{ formatDate(appointment.appointment_date) }} ob {{ appointment.start_time }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500">Cena</dt><dd>{{ appointment.price != null ? formatMoney(appointment.price) : '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500">Stanje</dt><dd>{{ appointment.status }}</dd></div>
                <div v-if="appointment.internal_notes" class="pt-2">
                    <dt class="mb-1 text-neutral-500">Interne opombe</dt>
                    <dd class="whitespace-pre-line text-neutral-800">{{ appointment.internal_notes }}</dd>
                </div>
                <div v-if="appointment.customer_notes" class="pt-2">
                    <dt class="mb-1 text-neutral-500">Opombe stranke</dt>
                    <dd class="whitespace-pre-line text-neutral-800">{{ appointment.customer_notes }}</dd>
                </div>
            </dl>
        </SectionCard>
    </AdminLayout>
</template>
