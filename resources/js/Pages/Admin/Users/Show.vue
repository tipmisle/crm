<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import Badge from '@/Components/Badge.vue';
import { formatDateTime } from '@/lib/format';

const props = defineProps<{
    user: {
        id: number;
        name: string;
        email: string;
        created_at: string;
        email_verified_at: string | null;
        is_demo: boolean;
        is_active: boolean;
        deactivated_at: string | null;
        is_platform_admin: boolean;
    };
    memberships: Array<{ id: number; name: string; is_demo: boolean }>;
}>();

function toggleActive() {
    const action = props.user.is_active ? 'deaktivirati' : 'ponovno aktivirati';
    if (!confirm(`Ali res želiš ${action} ta račun?`)) return;

    router.post(route(props.user.is_active ? 'admin.users.deactivate' : 'admin.users.reactivate', props.user.id));
}
</script>

<template>
    <Head :title="`Admin · ${user.name}`" />

    <AdminLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-lg font-semibold text-neutral-900">{{ user.name }}</h1>
                <p class="text-sm text-neutral-500">{{ user.email }}</p>
            </div>
            <button
                type="button"
                class="rounded-md border px-3 py-1.5 text-sm font-medium"
                :class="user.is_active ? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100' : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'"
                @click="toggleActive"
            >
                {{ user.is_active ? 'Deaktiviraj' : 'Ponovno aktiviraj' }}
            </button>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <SectionCard title="Podatki o računu">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-neutral-500">ID</dt><dd>#{{ user.id }}</dd></div>
                    <div class="flex justify-between"><dt class="text-neutral-500">Ustvarjen</dt><dd>{{ formatDateTime(user.created_at) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-neutral-500">E-pošta potrjena</dt><dd>{{ user.email_verified_at ? formatDateTime(user.email_verified_at) : 'ne' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-neutral-500">Stanje</dt><dd><Badge v-if="user.is_active" color="#166534" bg="#DCFCE7">aktiven</Badge><Badge v-else color="#B91C1C" bg="#FEE2E2">deaktiviran</Badge></dd></div>
                    <div class="flex justify-between"><dt class="text-neutral-500">Platform admin</dt><dd>{{ user.is_platform_admin ? 'da' : 'ne' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-neutral-500">Demo uporabnik</dt><dd>{{ user.is_demo ? 'da' : 'ne' }}</dd></div>
                </dl>
            </SectionCard>

            <SectionCard title="Članstvo v delovnih prostorih">
                <p v-if="memberships.length === 0" class="text-sm text-neutral-500">Brez članstev.</p>
                <ul v-else class="divide-y divide-neutral-100 text-sm">
                    <li v-for="m in memberships" :key="m.id" class="flex items-center justify-between py-1.5">
                        {{ m.name }}
                        <Badge v-if="m.is_demo" color="#9A3412" bg="#FFEDD5">demo</Badge>
                    </li>
                </ul>
            </SectionCard>
        </div>
    </AdminLayout>
</template>
