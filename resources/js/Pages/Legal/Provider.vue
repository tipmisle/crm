<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LegalLayout from '@/Layouts/LegalLayout.vue';

interface LegalConfig {
    company_name: string | null;
    company_legal_form: string | null;
    registered_address: string | null;
    registration_number: string | null;
    tax_number: string | null;
    vat_registered: boolean;
    vat_number: string | null;
    legal_email: string | null;
    support_email: string | null;
    dpo_contact: string | null;
    supervisory_authority_name: string;
    supervisory_authority_url: string;
}

const props = defineProps<{ legal: LegalConfig }>();

const rows = [
    { label: 'Naziv', value: props.legal.company_name },
    { label: 'Pravna oblika', value: props.legal.company_legal_form },
    { label: 'Sedež', value: props.legal.registered_address },
    { label: 'Matična številka', value: props.legal.registration_number },
    { label: 'Davčna številka', value: props.legal.tax_number },
    { label: 'ID za DDV', value: props.legal.vat_registered ? props.legal.vat_number : null },
    { label: 'E-pošta za pravna vprašanja', value: props.legal.legal_email },
    { label: 'Pooblaščena oseba za varstvo podatkov', value: props.legal.dpo_contact },
].filter((row) => row.value);
</script>

<template>
    <Head title="Podatki o ponudniku — Beležka">
        <meta name="description" content="Podatki o ponudniku storitve Beležka." />
    </Head>

    <LegalLayout title="Podatki o ponudniku">
        <section>
            <p>
                Storitev Beležka zagotavlja spodaj navedeni ponudnik. Ti podatki so objavljeni skladno z zahtevami o
                razkritju identitete ponudnika elektronskega poslovanja.
            </p>

            <table class="mt-4">
                <tbody>
                    <tr v-for="row in rows" :key="row.label">
                        <th>{{ row.label }}</th>
                        <td>{{ row.value }}</td>
                    </tr>
                </tbody>
            </table>

            <p v-if="!rows.length" class="mt-4 text-neutral-500">
                Podatki o ponudniku še niso objavljeni.
            </p>

            <p class="mt-6">
                Pristojni nadzorni organ za varstvo osebnih podatkov v Sloveniji je
                <a :href="legal.supervisory_authority_url" class="text-[var(--color-accent-600)] hover:underline" target="_blank" rel="noopener">
                    {{ legal.supervisory_authority_name }}
                </a>.
            </p>
        </section>
    </LegalLayout>
</template>
