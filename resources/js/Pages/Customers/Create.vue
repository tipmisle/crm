<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CustomerTypeToggle from '@/Components/CustomerTypeToggle.vue';

const form = useForm({
    full_name: '',
    email: '',
    phone: '',
    address_line: '',
    postal_code: '',
    city: '',
    country: '',
    tax_number: '',
    is_business: false,
    company_name: '',
    vat_registered: false,
    notes: '',
});

function submit() {
    form.post(route('customers.store'));
}
</script>

<template>
    <Head title="Nova stranka" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Nova stranka</h1>
        </template>

        <div class="mx-auto max-w-2xl px-4 py-6 sm:px-6 sm:py-8">
            <h1 class="text-2xl font-semibold text-neutral-900">Nova stranka</h1>

            <form class="mt-6 space-y-5" @submit.prevent="submit">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Vrsta stranke</label>
                    <CustomerTypeToggle v-model="form.is_business" />
                </div>

                <div v-if="form.is_business">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Ime podjetja</label>
                    <input
                        v-model="form.company_name"
                        type="text"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    />
                    <p v-if="form.errors.company_name" class="mt-1 text-xs text-red-500">{{ form.errors.company_name }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Ime</label>
                    <input
                        v-model="form.full_name"
                        type="text"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    />
                    <p v-if="form.errors.full_name" class="mt-1 text-xs text-red-500">{{ form.errors.full_name }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">E-pošta</label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Telefon</label>
                    <input
                        v-model="form.phone"
                        type="text"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    />
                    <p v-if="form.errors.phone" class="mt-1 text-xs text-red-500">{{ form.errors.phone }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Naslov</label>
                    <input
                        v-model="form.address_line"
                        type="text"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    />
                    <p v-if="form.errors.address_line" class="mt-1 text-xs text-red-500">{{ form.errors.address_line }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Poštna številka</label>
                        <input
                            v-model="form.postal_code"
                            type="text"
                            class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                        <p v-if="form.errors.postal_code" class="mt-1 text-xs text-red-500">{{ form.errors.postal_code }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kraj</label>
                        <input
                            v-model="form.city"
                            type="text"
                            class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                        <p v-if="form.errors.city" class="mt-1 text-xs text-red-500">{{ form.errors.city }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Država</label>
                        <input
                            v-model="form.country"
                            type="text"
                            class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                        <p v-if="form.errors.country" class="mt-1 text-xs text-red-500">{{ form.errors.country }}</p>
                    </div>
                    <div v-if="form.is_business">
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Davčna številka</label>
                        <input
                            v-model="form.tax_number"
                            type="text"
                            class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
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

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Opombe</label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-md bg-[var(--color-ink-900)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                >
                    Ustvari stranko
                </button>
            </form>
        </div>
    </AppLayout>
</template>
