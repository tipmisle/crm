<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import Toggle from '@/Components/Toggle.vue';

interface InvoiceSettingsData {
    id: number;
    company_name: string | null;
    address_line: string | null;
    postal_code: string | null;
    city: string | null;
    country: string | null;
    tax_number: string | null;
    vat_registered: boolean;
    prices_include_vat: boolean;
    email: string | null;
    phone: string | null;
    iban: string | null;
    bank_name: string | null;
    default_payment_deadline_days: number;
    place_of_issue: string | null;
    footer_text: string | null;
    vat_exempt_note: string | null;
    invoice_prefix: string;
    invoice_next_number: number;
    proforma_prefix: string;
    proforma_next_number: number;
}

const props = defineProps<{
    isOwner: boolean;
    settings: InvoiceSettingsData;
    logoUrl: string | null;
    nextInvoicePreview: string;
    nextProformaPreview: string;
}>();

const form = useForm({
    company_name: props.settings.company_name ?? '',
    address_line: props.settings.address_line ?? '',
    postal_code: props.settings.postal_code ?? '',
    city: props.settings.city ?? '',
    country: props.settings.country ?? '',
    tax_number: props.settings.tax_number ?? '',
    vat_registered: props.settings.vat_registered,
    prices_include_vat: props.settings.prices_include_vat,
    email: props.settings.email ?? '',
    phone: props.settings.phone ?? '',
    iban: props.settings.iban ?? '',
    bank_name: props.settings.bank_name ?? '',
    default_payment_deadline_days: props.settings.default_payment_deadline_days,
    place_of_issue: props.settings.place_of_issue ?? '',
    footer_text: props.settings.footer_text ?? '',
    vat_exempt_note: props.settings.vat_exempt_note ?? '',
    invoice_prefix: props.settings.invoice_prefix,
    proforma_prefix: props.settings.proforma_prefix,
    invoice_last_issued_number: null as number | null,
    proforma_last_issued_number: null as number | null,
});

function save() {
    form.patch(route('settings.invoicing.update'), { preserveScroll: true });
}

const nextInvoicePreview = computed(() => `${form.invoice_prefix}${(form.invoice_last_issued_number ?? props.settings.invoice_next_number - 1) + 1}`);
const nextProformaPreview = computed(() => `${form.proforma_prefix}${(form.proforma_last_issued_number ?? props.settings.proforma_next_number - 1) + 1}`);

const logoInput = ref<HTMLInputElement | null>(null);
const logoForm = useForm<{ logo: File | null }>({ logo: null });

function onLogoChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (!file) return;
    logoForm.logo = file;
    logoForm.post(route('settings.invoicing.logo.update'), {
        preserveScroll: true,
        onSuccess: () => {
            logoForm.reset();
            if (logoInput.value) logoInput.value.value = '';
        },
    });
}

function removeLogo() {
    router.delete(route('settings.invoicing.logo.destroy'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Nastavitve · Računi in plačila" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Računi in plačila</h1>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 sm:py-8">
            <p v-if="!isOwner" class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Samo lastnik delovnega prostora lahko ureja nastavitve računov.
            </p>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
            <SectionCard title="Podatki o podjetju">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="block text-xs text-neutral-500">Naziv podjetja</label>
                        <input v-model="form.company_name" :disabled="!isOwner" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none disabled:bg-neutral-50" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs text-neutral-500">Naslov</label>
                        <input v-model="form.address_line" :disabled="!isOwner" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none disabled:bg-neutral-50" />
                    </div>
                    <div>
                        <label class="block text-xs text-neutral-500">Poštna številka</label>
                        <input v-model="form.postal_code" :disabled="!isOwner" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none disabled:bg-neutral-50" />
                    </div>
                    <div>
                        <label class="block text-xs text-neutral-500">Kraj</label>
                        <input v-model="form.city" :disabled="!isOwner" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none disabled:bg-neutral-50" />
                    </div>
                    <div>
                        <label class="block text-xs text-neutral-500">Država</label>
                        <input v-model="form.country" :disabled="!isOwner" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none disabled:bg-neutral-50" />
                    </div>
                    <div>
                        <label class="block text-xs text-neutral-500">Davčna / ID št.</label>
                        <input v-model="form.tax_number" :disabled="!isOwner" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none disabled:bg-neutral-50" />
                    </div>
                    <div>
                        <label class="block text-xs text-neutral-500">E-pošta</label>
                        <input v-model="form.email" :disabled="!isOwner" type="email" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none disabled:bg-neutral-50" />
                    </div>
                    <div>
                        <label class="block text-xs text-neutral-500">Telefon</label>
                        <input v-model="form.phone" :disabled="!isOwner" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none disabled:bg-neutral-50" />
                    </div>
                    <div class="sm:col-span-2 flex items-center gap-2.5">
                        <Toggle v-model="form.vat_registered" :disabled="!isOwner" />
                        <span class="text-sm text-neutral-700">Zavezanec za DDV</span>
                    </div>
                    <div v-if="!form.vat_registered" class="sm:col-span-2">
                        <label class="block text-xs text-neutral-500">Opomba o neobdavčitvi z DDV</label>
                        <textarea v-model="form.vat_exempt_note" :disabled="!isOwner" rows="2" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none disabled:bg-neutral-50" />
                    </div>
                    <div v-if="form.vat_registered" class="sm:col-span-2 rounded-md border border-neutral-200 px-3 py-2.5">
                        <div class="flex items-start gap-2.5">
                            <Toggle v-model="form.prices_include_vat" :disabled="!isOwner" class="mt-0.5" />
                            <div>
                                <span class="text-sm text-neutral-700">Vnesene cene vključujejo DDV</span>
                                <p class="mt-0.5 text-xs text-neutral-500">
                                    Če vpišeš 80 €, bo končni znesek računa 80 € — DDV se izračuna iz vpisanega
                                    zneska, ne prišteje zraven.
                                </p>
                                <p v-if="!form.prices_include_vat" class="mt-1 text-xs text-amber-700">
                                    Izklopljeno: vpisana cena je osnova brez DDV, DDV se prišteje zraven, zato bo
                                    končni znesek računa višji od vpisane cene.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <SectionCard title="Plačilo">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="block text-xs text-neutral-500">IBAN</label>
                        <input v-model="form.iban" :disabled="!isOwner" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none disabled:bg-neutral-50" />
                    </div>
                    <div>
                        <label class="block text-xs text-neutral-500">Banka</label>
                        <input v-model="form.bank_name" :disabled="!isOwner" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none disabled:bg-neutral-50" />
                    </div>
                    <div>
                        <label class="block text-xs text-neutral-500">Privzet rok plačila (dni)</label>
                        <input v-model.number="form.default_payment_deadline_days" :disabled="!isOwner" type="number" min="0" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none disabled:bg-neutral-50" />
                    </div>
                    <div>
                        <label class="block text-xs text-neutral-500">Kraj izdaje</label>
                        <input v-model="form.place_of_issue" :disabled="!isOwner" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none disabled:bg-neutral-50" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs text-neutral-500">Besedilo v nogi dokumenta (neobvezno)</label>
                        <textarea v-model="form.footer_text" :disabled="!isOwner" rows="2" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none disabled:bg-neutral-50" />
                    </div>
                </div>
            </SectionCard>

            <SectionCard title="Oštevilčevanje" subtitle="Ločeni zaporedji za račune in predračune">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs text-neutral-500">Predpona — računi</label>
                        <input v-model="form.invoice_prefix" :disabled="!isOwner" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none disabled:bg-neutral-50" />
                        <p class="mt-1 text-xs text-neutral-500">Naslednji: {{ nextInvoicePreview }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-neutral-500">Zadnja izdana številka (neobvezno)</label>
                        <input v-model.number="form.invoice_last_issued_number" :disabled="!isOwner" type="number" min="0" placeholder="npr. 12" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none disabled:bg-neutral-50" />
                    </div>
                    <div>
                        <label class="block text-xs text-neutral-500">Predpona — predračuni</label>
                        <input v-model="form.proforma_prefix" :disabled="!isOwner" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none disabled:bg-neutral-50" />
                        <p class="mt-1 text-xs text-neutral-500">Naslednji: {{ nextProformaPreview }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-neutral-500">Zadnja izdana številka (neobvezno)</label>
                        <input v-model.number="form.proforma_last_issued_number" :disabled="!isOwner" type="number" min="0" placeholder="npr. 7" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none disabled:bg-neutral-50" />
                    </div>
                </div>
                <p class="mt-3 text-xs text-neutral-400">
                    "Zadnja izdana številka" nastavi začetno točko štetja (npr. če si doslej račune izdajal drugje) — ne
                    zmanjša že doseženega števca in ne vpliva na že izdane dokumente.
                </p>
            </SectionCard>

            <div v-if="isOwner" class="flex items-center gap-3">
                <button
                    type="button"
                    :disabled="form.processing"
                    class="rounded-md bg-[var(--color-ink-900)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                    @click="save"
                >
                    Shrani nastavitve
                </button>
            </div>
                </div>

                <div class="lg:col-span-1">
                    <div class="lg:sticky lg:top-6">
                        <SectionCard title="Logotip" subtitle="Prikazan na dokumentih in v predogledu">
                            <div class="flex items-center gap-4">
                                <img v-if="logoUrl" :src="logoUrl" alt="Logotip" class="h-16 w-auto rounded border border-neutral-200 object-contain p-1" />
                                <div v-else class="flex h-16 w-24 items-center justify-center rounded border border-dashed border-neutral-300 text-xs text-neutral-400">
                                    Brez logotipa
                                </div>
                            </div>
                            <div v-if="isOwner" class="mt-3 flex flex-col items-start gap-2">
                                <input ref="logoInput" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onLogoChange" />
                                <button
                                    type="button"
                                    class="rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50"
                                    @click="logoInput?.click()"
                                >
                                    Izberi datoteko
                                </button>
                                <button v-if="logoUrl" type="button" class="text-xs font-medium text-red-600 hover:underline" @click="removeLogo">
                                    Odstrani logotip
                                </button>
                            </div>
                        </SectionCard>

                        <div class="mt-4 space-y-2">
                            <a
                                :href="route('settings.invoicing.preview', { type: 'invoice' })"
                                target="_blank"
                                rel="noopener"
                                class="block rounded-md border border-neutral-200 px-4 py-2 text-center text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                            >
                                Predogled vzorčnega računa →
                            </a>
                            <a
                                :href="route('settings.invoicing.preview', { type: 'proforma' })"
                                target="_blank"
                                rel="noopener"
                                class="block rounded-md border border-neutral-200 px-4 py-2 text-center text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                            >
                                Predogled vzorčnega predračuna →
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
