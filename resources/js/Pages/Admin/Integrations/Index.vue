<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/Badge.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatDateTime } from '@/lib/format';

interface Row {
    id: number;
    workspace: { id: number; name: string; is_demo: boolean } | null;
    provider: string;
    status: string;
    display_name: string | null;
    external_account_id: string | null;
    connected_at: string | null;
    last_synced_at: string | null;
    token_expires_at: string | null;
}

const props = defineProps<{ integrations: { data: Row[]; links: any[] }; filters: { status?: string } }>();

function setStatus(status: string | undefined) {
    router.get(route('admin.integrations.index'), { status }, { preserveState: true });
}

function clearError(row: Row) {
    if (!confirm('Počisti napako te integracije?')) return;
    router.post(route('admin.integrations.clear-error', row.id));
}
</script>

<template>
    <Head title="Admin · Integracije" />

    <AdminLayout>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-semibold text-neutral-900">Integracije</h1>
            <select
                :value="filters.status ?? ''"
                class="rounded-md border border-neutral-300 px-2 py-1.5 text-sm"
                @change="setStatus(($event.target as HTMLSelectElement).value || undefined)"
            >
                <option value="">Vsa stanja</option>
                <option value="connected">povezano</option>
                <option value="error">napaka</option>
                <option value="disconnected">odklopljeno</option>
            </select>
        </div>

        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
            <table class="w-full text-sm">
                <thead class="border-b border-neutral-100 bg-neutral-50 text-left text-xs text-neutral-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Delovni prostor</th>
                        <th class="px-4 py-2 font-medium">Ponudnik</th>
                        <th class="px-4 py-2 font-medium">Stanje</th>
                        <th class="px-4 py-2 font-medium">Povezan</th>
                        <th class="px-4 py-2 font-medium">Zadnja sinh.</th>
                        <th class="px-4 py-2 font-medium">Poteče</th>
                        <th class="px-4 py-2 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    <tr v-for="i in integrations.data" :key="i.id" class="hover:bg-neutral-50">
                        <td class="px-4 py-2">
                            <Link v-if="i.workspace" :href="route('admin.workspaces.show', i.workspace.id)" class="font-medium text-neutral-900 hover:underline">{{ i.workspace.name }}</Link>
                        </td>
                        <td class="px-4 py-2">{{ i.provider }} <span class="text-neutral-400">{{ i.display_name }}</span></td>
                        <td class="px-4 py-2">
                            <Badge v-if="i.status === 'connected'" color="#166534" bg="#DCFCE7">povezano</Badge>
                            <Badge v-else-if="i.status === 'error'" color="#B91C1C" bg="#FEE2E2">napaka</Badge>
                            <Badge v-else color="#374151" bg="#F1F2F4">{{ i.status }}</Badge>
                        </td>
                        <td class="px-4 py-2 text-neutral-500">{{ formatDateTime(i.connected_at) }}</td>
                        <td class="px-4 py-2 text-neutral-500">{{ formatDateTime(i.last_synced_at) }}</td>
                        <td class="px-4 py-2 text-neutral-500">{{ formatDateTime(i.token_expires_at) }}</td>
                        <td class="px-4 py-2 text-right">
                            <button v-if="i.status === 'error'" type="button" class="text-xs font-medium text-[var(--color-accent-500)] hover:underline" @click="clearError(i)">
                                Počisti napako
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :links="integrations.links" />
    </AdminLayout>
</template>
