<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/Badge.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatDateTime } from '@/lib/format';

interface UserRow {
    id: number;
    name: string;
    email: string;
    created_at: string;
    email_verified_at: string | null;
    is_demo: boolean;
    is_active: boolean;
    is_platform_admin: boolean;
    current_workspace: { id: number; name: string } | null;
}

const props = defineProps<{
    users: { data: UserRow[]; links: any[] };
    filters: { q?: string };
}>();

const q = ref(props.filters.q ?? '');

function search() {
    router.get(route('admin.users.index'), { q: q.value }, { preserveState: true });
}
</script>

<template>
    <Head title="Admin · Uporabniki" />

    <AdminLayout>
        <div class="mb-4 flex items-center justify-between gap-3">
            <h1 class="text-lg font-semibold text-neutral-900">Uporabniki</h1>
            <input
                v-model="q"
                type="text"
                placeholder="Ime ali e-pošta…"
                class="w-64 rounded-md border border-neutral-300 px-3 py-1.5 text-sm"
                @keyup.enter="search"
            />
        </div>

        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
            <table class="w-full text-sm">
                <thead class="border-b border-neutral-100 bg-neutral-50 text-left text-xs text-neutral-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Uporabnik</th>
                        <th class="px-4 py-2 font-medium">Delovni prostor</th>
                        <th class="px-4 py-2 font-medium">Stanje</th>
                        <th class="px-4 py-2 font-medium">Ustvarjen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    <tr v-for="u in users.data" :key="u.id" class="hover:bg-neutral-50">
                        <td class="px-4 py-2">
                            <Link :href="route('admin.users.show', u.id)" class="font-medium text-neutral-900 hover:underline">{{ u.name }}</Link>
                            <p class="text-xs text-neutral-500">{{ u.email }}</p>
                        </td>
                        <td class="px-4 py-2 text-neutral-600">{{ u.current_workspace?.name ?? '—' }}</td>
                        <td class="px-4 py-2 space-x-1">
                            <Badge v-if="u.is_platform_admin" color="#1D4ED8" bg="#DBEAFE">admin</Badge>
                            <Badge v-if="u.is_demo" color="#9A3412" bg="#FFEDD5">demo</Badge>
                            <Badge v-if="!u.is_active" color="#B91C1C" bg="#FEE2E2">deaktiviran</Badge>
                            <Badge v-if="!u.email_verified_at" color="#374151" bg="#F1F2F4">nepotrjen e-mail</Badge>
                        </td>
                        <td class="px-4 py-2 text-neutral-500">{{ formatDateTime(u.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :links="users.links" />
    </AdminLayout>
</template>
