<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import Avatar from '@/Components/Avatar.vue';
import { formatDate } from '@/lib/format';
import { Building2, ArrowUpRight, Pencil, X } from 'lucide-vue-next';

interface QuickViewCustomer {
    id: number;
    full_name: string;
    email: string | null;
    phone: string | null;
    address_line?: string | null;
    postal_code?: string | null;
    city?: string | null;
    country?: string | null;
    tax_number?: string | null;
    is_business?: boolean;
    company_name?: string | null;
    vat_registered?: boolean;
    first_contacted_at?: string | null;
    notes: string | null;
}

defineProps<{ show: boolean; customer: QuickViewCustomer }>();
const emit = defineEmits<{ close: []; edit: [] }>();
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="p-6">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <Avatar :name="customer.full_name" size="lg" />
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <p class="truncate text-base font-semibold text-neutral-900">{{ customer.full_name }}</p>
                            <span
                                v-if="customer.is_business"
                                class="flex shrink-0 items-center gap-1 rounded-full bg-[var(--color-accent-50)] px-1.5 py-0.5 text-[10px] font-medium text-[var(--color-accent-700)]"
                            >
                                <Building2 :size="10" /> Podjetje
                            </span>
                        </div>
                        <p v-if="customer.first_contacted_at" class="text-xs text-neutral-500">
                            Stranka od {{ formatDate(customer.first_contacted_at) }}
                        </p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <button type="button" title="Uredi stranko" class="text-neutral-400 hover:text-neutral-700" @click="emit('edit')">
                        <Pencil :size="16" />
                    </button>
                    <button type="button" title="Zapri" class="text-neutral-400 hover:text-neutral-700" @click="emit('close')">
                        <X :size="18" />
                    </button>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 border-t border-neutral-100 pt-5 md:grid-cols-2 md:gap-10">
                <div class="space-y-3">
                    <div v-if="customer.full_name">
                        <p class="text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Ime</p>
                        <p class="mt-0.5 text-sm text-neutral-800">{{ customer.full_name }}</p>
                    </div>

                    <div v-if="customer.email">
                        <p class="text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Email</p>
                        <p class="mt-0.5 text-sm text-neutral-800">{{ customer.email }}</p>
                    </div>

                    <div v-if="customer.phone">
                        <p class="text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Telefonska</p>
                        <p class="mt-0.5 text-sm text-neutral-800">{{ customer.phone }}</p>
                    </div>

                    <div v-if="customer.notes">
                        <p class="text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Opombe</p>
                        <p class="mt-1 rounded-md bg-neutral-50 p-3 text-sm text-neutral-600">{{ customer.notes }}</p>
                    </div>

                    <p v-if="!customer.email && !customer.phone" class="text-sm text-neutral-400">Ni dodatnih kontaktnih podatkov.</p>
                </div>

                <div class="space-y-3 md:border-l md:border-neutral-100 md:pl-10">
                    <div v-if="customer.is_business && customer.company_name">
                        <p class="text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Ime podjetja</p>
                        <p class="mt-0.5 text-sm text-neutral-800">{{ customer.company_name }}</p>
                    </div>

                    <div v-if="customer.address_line">
                        <p class="text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Ulica in hišna številka</p>
                        <p class="mt-0.5 text-sm text-neutral-800">{{ customer.address_line }}</p>
                    </div>

                    <div v-if="customer.postal_code || customer.city" class="grid grid-cols-2 gap-3">
                        <div v-if="customer.postal_code">
                            <p class="text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Poštna št.</p>
                            <p class="mt-0.5 text-sm text-neutral-800">{{ customer.postal_code }}</p>
                        </div>
                        <div v-if="customer.city">
                            <p class="text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Kraj</p>
                            <p class="mt-0.5 text-sm text-neutral-800">{{ customer.city }}</p>
                        </div>
                    </div>

                    <div v-if="customer.country">
                        <p class="text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Država</p>
                        <p class="mt-0.5 text-sm text-neutral-800">{{ customer.country }}</p>
                    </div>

                    <div v-if="customer.is_business && customer.tax_number">
                        <p class="text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Davčna številka</p>
                        <p class="mt-0.5 text-sm text-neutral-800">{{ customer.tax_number }}</p>
                    </div>

                    <div v-if="customer.is_business">
                        <p class="text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Zavezanec za DDV</p>
                        <p class="mt-0.5 text-sm text-neutral-800">{{ customer.vat_registered ? 'Da' : 'Ne' }}</p>
                    </div>

                    <p v-if="!customer.is_business && !customer.address_line && !customer.city" class="text-sm text-neutral-400">
                        Ni naslova.
                    </p>
                </div>
            </div>

            <div class="mt-6 flex justify-end border-t border-neutral-100 pt-4">
                <Link
                    :href="route('customers.show', customer.id)"
                    class="flex items-center gap-1 text-sm font-medium text-[var(--color-accent-600)] hover:text-[var(--color-accent-700)]"
                >
                    Celoten profil <ArrowUpRight :size="14" />
                </Link>
            </div>
        </div>
    </Modal>
</template>
