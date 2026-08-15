<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatDateTime } from '@/lib/format';

interface Entry {
    id: number;
    event: string;
    actor: { id: number; name: string; email: string } | null;
    actor_type: string;
    workspace_id: number | null;
    target_type: string | null;
    target_id: number | null;
    ip_address: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
}

const props = defineProps<{ entries: { data: Entry[]; links: any[] }; filters: { event?: string; workspace_id?: string } }>();

const event = ref(props.filters.event ?? '');

function search() {
    router.get(route('admin.audit-log.index'), { event: event.value }, { preserveState: true });
}
</script>

<template>
    <Head title="Admin · Dnevnik revizije" />

    <AdminLayout>
        <div class="mb-4 flex items-center justify-between gap-3">
            <h1 class="text-lg font-semibold text-neutral-900">Dnevnik revizije</h1>
            <input
                v-model="event"
                type="text"
                placeholder="Filtriraj po dogodku…"
                class="w-64 rounded-md border border-neutral-300 px-3 py-1.5 text-sm"
                @keyup.enter="search"
            />
        </div>

        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
            <table class="w-full text-sm">
                <thead class="border-b border-neutral-100 bg-neutral-50 text-left text-xs text-neutral-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Čas</th>
                        <th class="px-4 py-2 font-medium">Dogodek</th>
                        <th class="px-4 py-2 font-medium">Izvajalec</th>
                        <th class="px-4 py-2 font-medium">Delovni prostor</th>
                        <th class="px-4 py-2 font-medium">Cilj</th>
                        <th class="px-4 py-2 font-medium">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    <tr v-for="e in entries.data" :key="e.id" class="hover:bg-neutral-50">
                        <td class="px-4 py-2 whitespace-nowrap text-neutral-500">{{ formatDateTime(e.created_at) }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ e.event }}</td>
                        <td class="px-4 py-2">{{ e.actor?.name ?? e.actor_type }}</td>
                        <td class="px-4 py-2 text-neutral-500">{{ e.workspace_id ?? '—' }}</td>
                        <td class="px-4 py-2 text-neutral-500">{{ e.target_type?.split('\\').pop() ?? '—' }}<span v-if="e.target_id">#{{ e.target_id }}</span></td>
                        <td class="px-4 py-2 text-neutral-400">{{ e.ip_address ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :links="entries.links" />
    </AdminLayout>
</template>
