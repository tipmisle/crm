<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatDateTime, formatDate } from '@/lib/format';

interface ConversationRow {
    id: number;
    customer: { id: number; full_name: string } | null;
    customer_display_name: string | null;
    customer_username: string | null;
    status: string;
    last_message_at: string | null;
}

interface CustomerRow {
    id: number;
    full_name: string;
    email: string | null;
    phone: string | null;
}

interface OrderRow {
    id: number;
    order_number: string | null;
    title: string;
    status: string;
    customer: { id: number; full_name: string } | null;
    created_at: string;
}

interface AppointmentRow {
    id: number;
    appointment_number: string | null;
    service_name: string;
    status: string;
    appointment_date: string;
    customer: { id: number; full_name: string } | null;
}

const props = defineProps<{
    workspace: { id: number; name: string };
    conversations: ConversationRow[];
    customers: CustomerRow[];
    orders: OrderRow[];
    appointments: AppointmentRow[];
}>();

type Tab = 'conversations' | 'customers' | 'orders' | 'appointments';

const tabs: Array<{ key: Tab; label: string }> = [
    { key: 'conversations', label: 'Pogovori' },
    { key: 'customers', label: 'Stranke' },
    { key: 'orders', label: 'Naročila' },
    { key: 'appointments', label: 'Termini' },
];

const active = ref<Tab>('conversations');
</script>

<template>
    <Head :title="`Podpora · ${workspace.name}`" />

    <AdminLayout>
        <h1 class="mb-1 text-lg font-semibold text-neutral-900">Vsebina delovnega prostora</h1>
        <p class="mb-4 text-xs text-neutral-500">
            {{ workspace.name }} — samo za branje, med aktivno sejo podpore. Odpiranje posameznega zapisa se beleži v
            dnevnik revizije.
        </p>

        <div class="mb-4 flex gap-1 border-b border-neutral-200">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="border-b-2 px-3 py-2 text-sm font-medium"
                :class="active === tab.key ? 'border-neutral-900 text-neutral-900' : 'border-transparent text-neutral-500 hover:text-neutral-700'"
                @click="active = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <div v-if="active === 'conversations'" class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
            <table class="w-full text-sm">
                <thead class="border-b border-neutral-100 bg-neutral-50 text-left text-xs text-neutral-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Stranka</th>
                        <th class="px-4 py-2 font-medium">Stanje</th>
                        <th class="px-4 py-2 font-medium">Zadnje sporočilo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    <tr v-for="c in conversations" :key="c.id" class="hover:bg-neutral-50">
                        <td class="px-4 py-2">
                            <Link :href="route('admin.workspaces.support.conversation', [workspace.id, c.id])" class="font-medium text-neutral-900 hover:underline">
                                {{ c.customer?.full_name ?? c.customer_display_name ?? c.customer_username ?? 'Neznana stranka' }}
                            </Link>
                        </td>
                        <td class="px-4 py-2 text-neutral-600">{{ c.status }}</td>
                        <td class="px-4 py-2 text-neutral-500">{{ formatDateTime(c.last_message_at) }}</td>
                    </tr>
                    <tr v-if="conversations.length === 0"><td colspan="3" class="px-4 py-6 text-center text-neutral-400">Ni pogovorov.</td></tr>
                </tbody>
            </table>
        </div>

        <div v-else-if="active === 'customers'" class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
            <table class="w-full text-sm">
                <thead class="border-b border-neutral-100 bg-neutral-50 text-left text-xs text-neutral-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Ime</th>
                        <th class="px-4 py-2 font-medium">E-pošta</th>
                        <th class="px-4 py-2 font-medium">Telefon</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    <tr v-for="c in customers" :key="c.id" class="hover:bg-neutral-50">
                        <td class="px-4 py-2">
                            <Link :href="route('admin.workspaces.support.customer', [workspace.id, c.id])" class="font-medium text-neutral-900 hover:underline">
                                {{ c.full_name }}
                            </Link>
                        </td>
                        <td class="px-4 py-2 text-neutral-600">{{ c.email ?? '—' }}</td>
                        <td class="px-4 py-2 text-neutral-600">{{ c.phone ?? '—' }}</td>
                    </tr>
                    <tr v-if="customers.length === 0"><td colspan="3" class="px-4 py-6 text-center text-neutral-400">Ni strank.</td></tr>
                </tbody>
            </table>
        </div>

        <div v-else-if="active === 'orders'" class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
            <table class="w-full text-sm">
                <thead class="border-b border-neutral-100 bg-neutral-50 text-left text-xs text-neutral-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Naročilo</th>
                        <th class="px-4 py-2 font-medium">Stranka</th>
                        <th class="px-4 py-2 font-medium">Stanje</th>
                        <th class="px-4 py-2 font-medium">Ustvarjeno</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    <tr v-for="o in orders" :key="o.id" class="hover:bg-neutral-50">
                        <td class="px-4 py-2">
                            <Link :href="route('admin.workspaces.support.order', [workspace.id, o.id])" class="font-medium text-neutral-900 hover:underline">
                                {{ o.order_number ?? '#' + o.id }} · {{ o.title }}
                            </Link>
                        </td>
                        <td class="px-4 py-2 text-neutral-600">{{ o.customer?.full_name ?? '—' }}</td>
                        <td class="px-4 py-2 text-neutral-600">{{ o.status }}</td>
                        <td class="px-4 py-2 text-neutral-500">{{ formatDateTime(o.created_at) }}</td>
                    </tr>
                    <tr v-if="orders.length === 0"><td colspan="4" class="px-4 py-6 text-center text-neutral-400">Ni naročil.</td></tr>
                </tbody>
            </table>
        </div>

        <div v-else class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
            <table class="w-full text-sm">
                <thead class="border-b border-neutral-100 bg-neutral-50 text-left text-xs text-neutral-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Termin</th>
                        <th class="px-4 py-2 font-medium">Stranka</th>
                        <th class="px-4 py-2 font-medium">Stanje</th>
                        <th class="px-4 py-2 font-medium">Datum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    <tr v-for="a in appointments" :key="a.id" class="hover:bg-neutral-50">
                        <td class="px-4 py-2">
                            <Link :href="route('admin.workspaces.support.appointment', [workspace.id, a.id])" class="font-medium text-neutral-900 hover:underline">
                                {{ a.appointment_number ?? '#' + a.id }} · {{ a.service_name }}
                            </Link>
                        </td>
                        <td class="px-4 py-2 text-neutral-600">{{ a.customer?.full_name ?? '—' }}</td>
                        <td class="px-4 py-2 text-neutral-600">{{ a.status }}</td>
                        <td class="px-4 py-2 text-neutral-500">{{ formatDate(a.appointment_date) }}</td>
                    </tr>
                    <tr v-if="appointments.length === 0"><td colspan="4" class="px-4 py-6 text-center text-neutral-400">Ni terminov.</td></tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
