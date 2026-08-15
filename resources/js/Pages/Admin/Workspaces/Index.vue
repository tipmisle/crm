<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/Badge.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatDateTime } from '@/lib/format';

interface WorkspaceRow {
    id: number;
    name: string;
    is_demo: boolean;
    demo_expires_at: string | null;
    members_count: number;
    created_at: string;
}

const props = defineProps<{
    workspaces: { data: WorkspaceRow[]; links: any[] };
    filters: { q?: string; type?: string };
}>();

const q = ref(props.filters.q ?? '');

function search() {
    router.get(route('admin.workspaces.index'), { q: q.value, type: props.filters.type }, { preserveState: true });
}

function setType(type: string | undefined) {
    router.get(route('admin.workspaces.index'), { q: q.value, type }, { preserveState: true });
}
</script>

<template>
    <Head title="Admin · Delovni prostori" />

    <AdminLayout>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-semibold text-neutral-900">Delovni prostori</h1>
            <div class="flex items-center gap-2">
                <input
                    v-model="q"
                    type="text"
                    placeholder="Ime, ID ali e-pošta lastnika…"
                    class="w-64 rounded-md border border-neutral-300 px-3 py-1.5 text-sm"
                    @keyup.enter="search"
                />
                <select
                    :value="filters.type ?? ''"
                    class="rounded-md border border-neutral-300 px-2 py-1.5 text-sm"
                    @change="setType(($event.target as HTMLSelectElement).value || undefined)"
                >
                    <option value="">Vse</option>
                    <option value="real">Realni</option>
                    <option value="demo">Demo</option>
                </select>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
            <table class="w-full text-sm">
                <thead class="border-b border-neutral-100 bg-neutral-50 text-left text-xs text-neutral-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Ime</th>
                        <th class="px-4 py-2 font-medium">Tip</th>
                        <th class="px-4 py-2 font-medium">Člani</th>
                        <th class="px-4 py-2 font-medium">Ustvarjen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    <tr v-for="w in workspaces.data" :key="w.id" class="hover:bg-neutral-50">
                        <td class="px-4 py-2">
                            <Link :href="route('admin.workspaces.show', w.id)" class="font-medium text-neutral-900 hover:underline">{{ w.name }}</Link>
                            <span class="ml-1 text-xs text-neutral-400">#{{ w.id }}</span>
                        </td>
                        <td class="px-4 py-2">
                            <Badge v-if="w.is_demo" color="#9A3412" bg="#FFEDD5">demo</Badge>
                            <Badge v-else color="#166534" bg="#DCFCE7">realen</Badge>
                        </td>
                        <td class="px-4 py-2">{{ w.members_count }}</td>
                        <td class="px-4 py-2 text-neutral-500">{{ formatDateTime(w.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :links="workspaces.links" />
    </AdminLayout>
</template>
