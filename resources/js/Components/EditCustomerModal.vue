<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import CustomerTypeToggle from '@/Components/CustomerTypeToggle.vue';
import { UserRound, Mail, Phone, MapPin, Hash, Building2, Globe, Receipt, StickyNote } from 'lucide-vue-next';

// Only the fields this form actually edits — kept narrower than the full
// Customer type so it also accepts lighter customer shapes (e.g. Inbox's
// conversation.customer context object) without a structural mismatch.
interface EditableCustomer {
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
    notes: string | null;
}

const props = withDefaults(
    defineProps<{
        show: boolean;
        customer: EditableCustomer;
        showName?: boolean;
        showNotes?: boolean;
    }>(),
    {
        showName: false,
        showNotes: false,
    },
);

const emit = defineEmits<{ close: [] }>();

const form = useForm({
    full_name: props.customer.full_name,
    email: props.customer.email ?? '',
    phone: props.customer.phone ?? '',
    address_line: props.customer.address_line ?? '',
    postal_code: props.customer.postal_code ?? '',
    city: props.customer.city ?? '',
    country: props.customer.country ?? '',
    tax_number: props.customer.tax_number ?? '',
    is_business: props.customer.is_business ?? false,
    company_name: props.customer.company_name ?? '',
    vat_registered: props.customer.vat_registered ?? false,
    notes: props.customer.notes ?? '',
});

watch(
    () => props.show,
    (show) => {
        if (!show) return;
        form.full_name = props.customer.full_name;
        form.email = props.customer.email ?? '';
        form.phone = props.customer.phone ?? '';
        form.address_line = props.customer.address_line ?? '';
        form.postal_code = props.customer.postal_code ?? '';
        form.city = props.customer.city ?? '';
        form.country = props.customer.country ?? '';
        form.tax_number = props.customer.tax_number ?? '';
        form.is_business = props.customer.is_business ?? false;
        form.company_name = props.customer.company_name ?? '';
        form.vat_registered = props.customer.vat_registered ?? false;
        form.notes = props.customer.notes ?? '';
        form.clearErrors();
    },
);

function submit() {
    form.patch(route('customers.update', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <Modal :show="show" max-width="3xl" @close="emit('close')">
        <form class="p-6" @submit.prevent="submit">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[var(--color-accent-50)] text-[var(--color-accent-600)]">
                    <UserRound :size="20" />
                </div>
                <div>
                    <h2 class="text-base font-semibold text-neutral-900">Uredi podatke stranke</h2>
                    <p class="text-xs text-neutral-500">Kontaktni in naslovni podatki</p>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2 md:gap-10">
                <div>
                    <div class="space-y-3">
                        <div>
                            <CustomerTypeToggle v-model="form.is_business" />
                        </div>

                        <div v-if="showName">
                            <label class="mb-1 block text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Ime</label>
                            <div class="relative">
                                <UserRound :size="15" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-neutral-400" />
                                <input
                                    v-model="form.full_name"
                                    type="text"
                                    class="w-full rounded-md border border-neutral-200 bg-white py-2 pr-3 pl-9 text-sm outline-none focus:border-[var(--color-accent-400)]"
                                />
                            </div>
                            <p v-if="form.errors.full_name" class="mt-1 text-xs text-red-500">{{ form.errors.full_name }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Email</label>
                            <div class="relative">
                                <Mail :size="15" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-neutral-400" />
                                <input
                                    v-model="form.email"
                                    type="email"
                                    class="w-full rounded-md border border-neutral-200 bg-white py-2 pr-3 pl-9 text-sm outline-none focus:border-[var(--color-accent-400)]"
                                />
                            </div>
                            <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Telefonska</label>
                            <div class="relative">
                                <Phone :size="15" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-neutral-400" />
                                <input
                                    v-model="form.phone"
                                    type="text"
                                    class="w-full rounded-md border border-neutral-200 bg-white py-2 pr-3 pl-9 text-sm outline-none focus:border-[var(--color-accent-400)]"
                                />
                            </div>
                            <p v-if="form.errors.phone" class="mt-1 text-xs text-red-500">{{ form.errors.phone }}</p>
                        </div>

                        <div v-if="showNotes">
                            <label class="mb-1 block text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Opombe</label>
                            <div class="relative">
                                <StickyNote :size="15" class="pointer-events-none absolute top-3 left-3 text-neutral-400" />
                                <textarea
                                    v-model="form.notes"
                                    rows="3"
                                    class="w-full rounded-md border border-neutral-200 bg-white py-2 pr-3 pl-9 text-sm outline-none focus:border-[var(--color-accent-400)]"
                                />
                            </div>
                            <p v-if="form.errors.notes" class="mt-1 text-xs text-red-500">{{ form.errors.notes }}</p>
                        </div>
                    </div>
                </div>

                <div class="md:border-l md:border-neutral-100 md:pl-10">
                    <div class="space-y-3">
                        <div v-if="form.is_business">
                            <label class="mb-1 block text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Ime podjetja</label>
                            <div class="relative">
                                <Building2 :size="15" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-neutral-400" />
                                <input
                                    v-model="form.company_name"
                                    type="text"
                                    class="w-full rounded-md border border-neutral-200 bg-white py-2 pr-3 pl-9 text-sm outline-none focus:border-[var(--color-accent-400)]"
                                />
                            </div>
                            <p v-if="form.errors.company_name" class="mt-1 text-xs text-red-500">{{ form.errors.company_name }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Ulica in hišna številka</label>
                            <div class="relative">
                                <MapPin :size="15" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-neutral-400" />
                                <input
                                    v-model="form.address_line"
                                    type="text"
                                    class="w-full rounded-md border border-neutral-200 bg-white py-2 pr-3 pl-9 text-sm outline-none focus:border-[var(--color-accent-400)]"
                                />
                            </div>
                            <p v-if="form.errors.address_line" class="mt-1 text-xs text-red-500">{{ form.errors.address_line }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Poštna št.</label>
                                <div class="relative">
                                    <Hash :size="15" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-neutral-400" />
                                    <input
                                        v-model="form.postal_code"
                                        type="text"
                                        class="w-full rounded-md border border-neutral-200 bg-white py-2 pr-3 pl-9 text-sm outline-none focus:border-[var(--color-accent-400)]"
                                    />
                                </div>
                                <p v-if="form.errors.postal_code" class="mt-1 text-xs text-red-500">{{ form.errors.postal_code }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Kraj</label>
                                <div class="relative">
                                    <Building2 :size="15" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-neutral-400" />
                                    <input
                                        v-model="form.city"
                                        type="text"
                                        class="w-full rounded-md border border-neutral-200 bg-white py-2 pr-3 pl-9 text-sm outline-none focus:border-[var(--color-accent-400)]"
                                    />
                                </div>
                                <p v-if="form.errors.city" class="mt-1 text-xs text-red-500">{{ form.errors.city }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Država</label>
                            <div class="relative">
                                <Globe :size="15" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-neutral-400" />
                                <input
                                    v-model="form.country"
                                    type="text"
                                    class="w-full rounded-md border border-neutral-200 bg-white py-2 pr-3 pl-9 text-sm outline-none focus:border-[var(--color-accent-400)]"
                                />
                            </div>
                            <p v-if="form.errors.country" class="mt-1 text-xs text-red-500">{{ form.errors.country }}</p>
                        </div>

                        <div v-if="form.is_business">
                            <label class="mb-1 block text-[11px] font-medium tracking-wide text-neutral-400 uppercase">Davčna številka</label>
                            <div class="relative">
                                <Receipt :size="15" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-neutral-400" />
                                <input
                                    v-model="form.tax_number"
                                    type="text"
                                    class="w-full rounded-md border border-neutral-200 bg-white py-2 pr-3 pl-9 text-sm outline-none focus:border-[var(--color-accent-400)]"
                                />
                            </div>
                            <p v-if="form.errors.tax_number" class="mt-1 text-xs text-red-500">{{ form.errors.tax_number }}</p>

                            <label
                                class="mt-2 flex cursor-pointer items-center justify-between gap-3 rounded-md border border-neutral-200 px-3 py-2 transition select-none"
                                :class="form.vat_registered ? 'border-[var(--color-accent-300)] bg-[var(--color-accent-50)]' : 'hover:bg-neutral-50'"
                            >
                                <span class="text-sm text-neutral-700">Zavezanec za DDV?</span>
                                <span
                                    class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors"
                                    :class="form.vat_registered ? 'bg-[var(--color-ink-900)]' : 'bg-neutral-300'"
                                >
                                    <span
                                        class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform"
                                        :class="form.vat_registered ? 'translate-x-4.5' : 'translate-x-1'"
                                    />
                                </span>
                                <input v-model="form.vat_registered" type="checkbox" class="sr-only" />
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2 border-t border-neutral-100 pt-4">
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-100" @click="emit('close')">
                    Prekliči
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-md bg-[var(--color-ink-900)] px-3 py-1.5 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                >
                    Shrani
                </button>
            </div>
        </form>
    </Modal>
</template>
