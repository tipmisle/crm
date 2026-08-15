<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';

defineProps<{
    workspace: { id: number; name: string };
    customer: { id: number; full_name: string; email: string | null; phone: string | null; notes: string | null };
}>();
</script>

<template>
    <Head title="Podpora · Stranka" />

    <AdminLayout>
        <h1 class="mb-1 text-lg font-semibold text-neutral-900">{{ customer.full_name }}</h1>
        <p class="mb-4 text-xs text-neutral-500">{{ workspace.name }} — ta vsebina je vidna samo med aktivno sejo podpore in je bila zabeležena v dnevniku revizije.</p>

        <SectionCard title="Podatki">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-neutral-500">E-pošta</dt><dd>{{ customer.email ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500">Telefon</dt><dd>{{ customer.phone ?? '—' }}</dd></div>
                <div v-if="customer.notes" class="pt-2">
                    <dt class="mb-1 text-neutral-500">Opombe</dt>
                    <dd class="whitespace-pre-line text-neutral-800">{{ customer.notes }}</dd>
                </div>
            </dl>
        </SectionCard>
    </AdminLayout>
</template>
