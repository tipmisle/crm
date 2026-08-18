<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatDateTime } from '@/lib/format';

interface FeatureRequest {
    id: number;
    subject: string;
    message: string;
    status: 'open' | 'planned' | 'done';
    created_at: string;
    workspace: { id: number; name: string } | null;
    user: { id: number; name: string; email: string } | null;
}

defineProps<{
    requests: { data: FeatureRequest[]; links: any[] };
    filters: { status?: string };
}>();

const statusLabel: Record<string, string> = {
    open: 'Predlagano',
    planned: 'Načrtovano',
    done: 'Izvedeno',
};

const statusClass: Record<string, string> = {
    open: 'bg-neutral-100 text-neutral-600',
    planned: 'bg-amber-50 text-amber-700',
    done: 'bg-emerald-50 text-emerald-700',
};

const statusOptions: Array<FeatureRequest['status']> = ['open', 'planned', 'done'];

const expanded = ref<number | null>(null);

function toggle(id: number) {
    expanded.value = expanded.value === id ? null : id;
}

function filterByStatus(status: string) {
    router.get(route('admin.feature-requests.index'), status ? { status } : {}, { preserveState: true });
}

function setStatus(request: FeatureRequest, status: string) {
    router.patch(route('admin.feature-requests.update', request.id), { status }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Admin · Predlogi za nove funkcionalnosti" />

    <AdminLayout>
        <div class="mb-4 flex items-center justify-between gap-3">
            <h1 class="text-lg font-semibold text-neutral-900">Predlogi za nove funkcionalnosti</h1>
            <select
                :value="filters.status ?? ''"
                class="rounded-md border border-neutral-300 px-3 py-1.5 text-sm"
                @change="filterByStatus(($event.target as HTMLSelectElement).value)"
            >
                <option value="">Vsi statusi</option>
                <option v-for="s in statusOptions" :key="s" :value="s">{{ statusLabel[s] }}</option>
            </select>
        </div>

        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
            <table class="w-full text-sm">
                <thead class="border-b border-neutral-100 bg-neutral-50 text-left text-xs text-neutral-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Čas</th>
                        <th class="px-4 py-2 font-medium">Naslov</th>
                        <th class="px-4 py-2 font-medium">Delovni prostor</th>
                        <th class="px-4 py-2 font-medium">Predlagal</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                        <th class="px-4 py-2 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    <template v-for="r in requests.data" :key="r.id">
                        <tr class="cursor-pointer hover:bg-neutral-50" @click="toggle(r.id)">
                            <td class="px-4 py-2 whitespace-nowrap text-neutral-500">{{ formatDateTime(r.created_at) }}</td>
                            <td class="px-4 py-2 text-neutral-800">{{ r.subject }}</td>
                            <td class="px-4 py-2 text-neutral-500">{{ r.workspace?.name ?? '—' }}</td>
                            <td class="px-4 py-2 text-neutral-500">{{ r.user?.name ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass[r.status]">
                                    {{ statusLabel[r.status] }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right" @click.stop>
                                <select
                                    :value="r.status"
                                    class="rounded-md border border-neutral-300 px-2 py-1 text-xs"
                                    @change="setStatus(r, ($event.target as HTMLSelectElement).value)"
                                >
                                    <option v-for="s in statusOptions" :key="s" :value="s">{{ statusLabel[s] }}</option>
                                </select>
                            </td>
                        </tr>
                        <tr v-if="expanded === r.id" class="bg-neutral-50">
                            <td colspan="6" class="px-4 py-3 text-sm text-neutral-700">
                                <p class="whitespace-pre-wrap">{{ r.message }}</p>
                                <p v-if="r.user" class="mt-1 text-xs text-neutral-400">{{ r.user.email }}</p>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="requests.data.length === 0">
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-neutral-400">Ni predlogov.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :links="requests.links" />
    </AdminLayout>
</template>
