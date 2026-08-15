<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import Badge from '@/Components/Badge.vue';
import { formatDateTime } from '@/lib/format';

interface Stats {
    real_workspaces: number;
    demo_workspaces_active: number;
    demo_workspaces_awaiting_cleanup: number;
    total_users: number;
    instagram_connected: number;
    facebook_connected: number;
    integrations_in_error: number;
}

defineProps<{
    stats: Stats;
    recentlyFailedIntegrations: Array<{ id: number; workspace: { id: number; name: string } | null; provider: string; display_name: string | null; updated_at: string }>;
    newestRealWorkspaces: Array<{ id: number; name: string; created_at: string }>;
}>();

const tiles: Array<{ key: keyof Stats; label: string }> = [
    { key: 'real_workspaces', label: 'Realni delovni prostori' },
    { key: 'total_users', label: 'Uporabniki' },
    { key: 'demo_workspaces_active', label: 'Aktivne demo verzije' },
    { key: 'demo_workspaces_awaiting_cleanup', label: 'Demo za čiščenje' },
    { key: 'instagram_connected', label: 'Instagram povezav' },
    { key: 'facebook_connected', label: 'Messenger povezav' },
    { key: 'integrations_in_error', label: 'Integracije v napaki' },
];
</script>

<template>
    <Head title="Admin · Nadzorna plošča" />

    <AdminLayout>
        <h1 class="mb-4 text-lg font-semibold text-neutral-900">Nadzorna plošča</h1>

        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div v-for="tile in tiles" :key="tile.key" class="rounded-xl border border-neutral-200 bg-white p-4">
                <p class="text-xs text-neutral-500">{{ tile.label }}</p>
                <p class="mt-1 text-2xl font-semibold text-neutral-900">{{ stats[tile.key] }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <SectionCard title="Nedavno neuspele integracije">
                <p v-if="recentlyFailedIntegrations.length === 0" class="text-sm text-neutral-500">Trenutno ni integracij v napaki.</p>
                <ul v-else class="divide-y divide-neutral-100">
                    <li v-for="i in recentlyFailedIntegrations" :key="i.id" class="flex items-center justify-between py-2 text-sm">
                        <div>
                            <Link v-if="i.workspace" :href="route('admin.workspaces.show', i.workspace.id)" class="font-medium text-neutral-900 hover:underline">
                                {{ i.workspace.name }}
                            </Link>
                            <p class="text-xs text-neutral-500">{{ i.provider }} · {{ i.display_name ?? '—' }}</p>
                        </div>
                        <Badge color="#B91C1C" bg="#FEE2E2">napaka</Badge>
                    </li>
                </ul>
            </SectionCard>

            <SectionCard title="Najnovejši realni delovni prostori">
                <ul class="divide-y divide-neutral-100">
                    <li v-for="w in newestRealWorkspaces" :key="w.id" class="flex items-center justify-between py-2 text-sm">
                        <Link :href="route('admin.workspaces.show', w.id)" class="font-medium text-neutral-900 hover:underline">{{ w.name }}</Link>
                        <span class="text-xs text-neutral-500">{{ formatDateTime(w.created_at) }}</span>
                    </li>
                </ul>
            </SectionCard>
        </div>
    </AdminLayout>
</template>
