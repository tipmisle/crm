<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import Badge from '@/Components/Badge.vue';
import { formatDateTime } from '@/lib/format';

interface Workspace {
    id: number;
    name: string;
    timezone: string;
    currency: string;
    orders_enabled: boolean;
    appointments_enabled: boolean;
    is_demo: boolean;
    demo_expires_at: string | null;
    created_at: string;
}

const props = defineProps<{
    workspace: Workspace;
    owner: { id: number; name: string; email: string } | null;
    usage: Record<string, number>;
    integrations: Array<{
        id: number;
        provider: string;
        status: string;
        display_name: string | null;
        external_account_id: string | null;
        connected_at: string | null;
        last_synced_at: string | null;
        token_expires_at: string | null;
        scopes: string[] | null;
    }>;
    supportAccess: { expires_at: string; granted_by: string | null } | null;
    billing: {
        status: string;
        status_label: string;
        stripe_customer_id: string | null;
        stripe_subscription_id: string | null;
        cancel_at_period_end: boolean;
        ends_at: string | null;
        has_payment_problem: boolean;
    } | null;
}>();

const usageLabels: Record<string, string> = {
    members: 'Člani',
    customers: 'Stranke',
    conversations: 'Pogovori',
    messages: 'Sporočila',
    orders: 'Naročila',
    appointments: 'Termini',
    products: 'Izdelki',
    services: 'Storitve',
    follow_ups: 'Opomniki',
};

const form = useForm({});

function startSupportSession() {
    form.post(route('admin.workspaces.support.start', props.workspace.id));
}

function deleteDemo() {
    if (!confirm(`Izbriši demo delovni prostor "${props.workspace.name}"? Tega dejanja ni mogoče razveljaviti.`)) return;
    router.delete(route('admin.workspaces.destroy-demo', props.workspace.id));
}
</script>

<template>
    <Head :title="`Admin · ${workspace.name}`" />

    <AdminLayout>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h1 class="text-lg font-semibold text-neutral-900">{{ workspace.name }}</h1>
                <p class="text-xs text-neutral-500">
                    #{{ workspace.id }} · {{ workspace.timezone }} · {{ workspace.currency }}
                    <Badge v-if="workspace.is_demo" color="#9A3412" bg="#FFEDD5" class="ml-1">demo</Badge>
                </p>
            </div>
            <button
                v-if="workspace.is_demo"
                type="button"
                class="rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-100"
                @click="deleteDemo"
            >
                Izbriši demo delovni prostor
            </button>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <SectionCard title="Osnovno" class="lg:col-span-1">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-neutral-500">Lastnik</dt><dd>{{ owner?.name ?? '—' }} <span class="text-neutral-400">({{ owner?.email }})</span></dd></div>
                    <div class="flex justify-between"><dt class="text-neutral-500">Naročila</dt><dd>{{ workspace.orders_enabled ? 'vklopljeno' : 'izklopljeno' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-neutral-500">Termini</dt><dd>{{ workspace.appointments_enabled ? 'vklopljeno' : 'izklopljeno' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-neutral-500">Ustvarjen</dt><dd>{{ formatDateTime(workspace.created_at) }}</dd></div>
                    <div v-if="workspace.is_demo" class="flex justify-between"><dt class="text-neutral-500">Demo poteče</dt><dd>{{ formatDateTime(workspace.demo_expires_at) }}</dd></div>
                </dl>
            </SectionCard>

            <SectionCard title="Obseg uporabe" class="lg:col-span-2">
                <div class="grid grid-cols-3 gap-3 sm:grid-cols-5">
                    <div v-for="(value, key) in usage" :key="key">
                        <p class="text-xs text-neutral-500">{{ usageLabels[key] ?? key }}</p>
                        <p class="text-lg font-semibold text-neutral-900">{{ value }}</p>
                    </div>
                </div>
            </SectionCard>

            <SectionCard title="Integracije" class="lg:col-span-3">
                <p v-if="integrations.length === 0" class="text-sm text-neutral-500">Ni povezanih integracij.</p>
                <table v-else class="w-full text-sm">
                    <thead class="text-left text-xs text-neutral-500">
                        <tr>
                            <th class="py-1 font-medium">Ponudnik</th>
                            <th class="py-1 font-medium">Stanje</th>
                            <th class="py-1 font-medium">Račun</th>
                            <th class="py-1 font-medium">Povezan</th>
                            <th class="py-1 font-medium">Zadnja sinh.</th>
                            <th class="py-1 font-medium">Poteče</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <tr v-for="i in integrations" :key="i.id">
                            <td class="py-1.5">{{ i.provider }}</td>
                            <td class="py-1.5">
                                <Badge v-if="i.status === 'connected'" color="#166534" bg="#DCFCE7">povezano</Badge>
                                <Badge v-else-if="i.status === 'error'" color="#B91C1C" bg="#FEE2E2">napaka</Badge>
                                <Badge v-else color="#374151" bg="#F1F2F4">{{ i.status }}</Badge>
                            </td>
                            <td class="py-1.5">{{ i.display_name ?? i.external_account_id ?? '—' }}</td>
                            <td class="py-1.5 text-neutral-500">{{ formatDateTime(i.connected_at) }}</td>
                            <td class="py-1.5 text-neutral-500">{{ formatDateTime(i.last_synced_at) }}</td>
                            <td class="py-1.5 text-neutral-500">{{ formatDateTime(i.token_expires_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </SectionCard>

            <SectionCard v-if="billing" title="Naročnina" class="lg:col-span-3">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-neutral-500">Stanje</dt>
                        <dd>
                            <Badge v-if="billing.status === 'active'" color="#166534" bg="#DCFCE7">{{ billing.status_label }}</Badge>
                            <Badge v-else-if="billing.has_payment_problem" color="#B91C1C" bg="#FEE2E2">{{ billing.status_label }}</Badge>
                            <Badge v-else color="#374151" bg="#F1F2F4">{{ billing.status_label }}</Badge>
                        </dd>
                    </div>
                    <div class="flex justify-between"><dt class="text-neutral-500">Stripe stranka</dt><dd class="text-neutral-500">{{ billing.stripe_customer_id ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-neutral-500">Stripe naročnina</dt><dd class="text-neutral-500">{{ billing.stripe_subscription_id ?? '—' }}</dd></div>
                    <div v-if="billing.cancel_at_period_end" class="flex justify-between"><dt class="text-neutral-500">Poteče</dt><dd>{{ formatDateTime(billing.ends_at) }}</dd></div>
                </dl>
            </SectionCard>

            <SectionCard title="Dostop za podporo" class="lg:col-span-3">
                <div v-if="!supportAccess" class="text-sm text-neutral-500">
                    Podpora nima dostopa do vsebine tega delovnega prostora. Lastnik ga lahko odobri v Nastavitve → Podpora.
                </div>
                <div v-else class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-neutral-700">
                        Dostop odobren do <strong>{{ formatDateTime(supportAccess.expires_at) }}</strong>
                        <span v-if="supportAccess.granted_by" class="text-neutral-400"> · odobril(a) {{ supportAccess.granted_by }}</span>
                    </p>
                    <button
                        type="button"
                        class="rounded-md bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700"
                        @click="startSupportSession"
                    >
                        Začni sejo podpore
                    </button>
                </div>
            </SectionCard>
        </div>
    </AdminLayout>
</template>
