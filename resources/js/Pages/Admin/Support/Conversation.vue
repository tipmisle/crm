<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import { formatDateTime } from '@/lib/format';

interface Message {
    id: number;
    sender_type: string;
    body: string | null;
    sent_at: string | null;
}

defineProps<{
    workspace: { id: number; name: string };
    conversation: {
        id: number;
        customer_display_name: string | null;
        customer_username: string | null;
        customer: { id: number; full_name: string } | null;
        messages: Message[];
    };
}>();
</script>

<template>
    <Head title="Podpora · Pogovor" />

    <AdminLayout>
        <h1 class="mb-1 text-lg font-semibold text-neutral-900">
            Pogovor #{{ conversation.id }} · {{ conversation.customer?.full_name ?? conversation.customer_display_name ?? 'Neznana stranka' }}
        </h1>
        <p class="mb-4 text-xs text-neutral-500">{{ workspace.name }} — ta vsebina je vidna samo med aktivno sejo podpore in je bila zabeležena v dnevniku revizije.</p>

        <SectionCard title="Sporočila">
            <ul class="space-y-3">
                <li v-for="m in conversation.messages" :key="m.id" class="text-sm">
                    <p class="text-xs text-neutral-400">{{ m.sender_type }} · {{ formatDateTime(m.sent_at) }}</p>
                    <p class="text-neutral-800">{{ m.body ?? '📎 priloga' }}</p>
                </li>
            </ul>
        </SectionCard>
    </AdminLayout>
</template>
