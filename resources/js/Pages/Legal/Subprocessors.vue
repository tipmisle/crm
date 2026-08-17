<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LegalLayout from '@/Layouts/LegalLayout.vue';

interface Provider {
    name: string;
    purpose: string;
    data: string;
    location: string | null;
    transfer_mechanism: string | null;
    transfer_more_info_url: string | null;
}

interface LegalConfig {
    subprocessors: Provider[];
    account_billing_providers: Provider[];
    legal_email: string | null;
}

defineProps<{ legal: LegalConfig }>();
</script>

<template>
    <Head title="Podobdelovalci — Beležka">
        <meta
            name="description"
            content="Seznam podobdelovalcev, ki obdelujejo podatke strank poslovnih uporabnikov, in ponudnikov, ki obdelujejo lastne podatke uporabnika Beležke."
        />
    </Head>

    <LegalLayout title="Podobdelovalci">
        <section id="podobdelovalci">
            <h2>1. Podobdelovalci, ki obdelujejo podatke VAŠIH strank</h2>
            <p>
                Ko podjetje uporablja Beležko za obdelavo podatkov o svojih strankah, Beležka nastopa kot obdelovalec
                (glej <a class="text-[var(--color-accent-600)] hover:underline" :href="route('legal.dpa')">Dogovor o obdelavi osebnih podatkov</a>).
                Spodnji seznam vsebuje izključno ponudnike, ki pri tem dejansko prejmejo podatke o vaših strankah
                (Article 28 podobdelovalci) — ne ponudnike, ki obdelujejo samo vaše lastne podatke kot uporabnika
                Beležke (ti so navedeni ločeno spodaj v 2. členu). Seznam vsebuje samo dejansko integrirane
                ponudnike storitev — ne dodajamo ponudnikov, dokler dejansko niso v uporabi.
            </p>

            <table class="mt-4">
                <thead>
                    <tr>
                        <th>Ponudnik</th>
                        <th>Namen</th>
                        <th>Vrsta podatkov (vaših strank)</th>
                        <th>Lokacija obdelave</th>
                        <th>Prenosni mehanizem (izven EGP)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="sp in legal.subprocessors" :key="sp.name">
                        <td>{{ sp.name }}</td>
                        <td>{{ sp.purpose }}</td>
                        <td>{{ sp.data }}</td>
                        <td>{{ sp.location ?? 'NEEDS OWNER INPUT' }}</td>
                        <td>
                            <a v-if="sp.transfer_mechanism && sp.transfer_more_info_url" :href="sp.transfer_more_info_url" target="_blank" rel="noopener" class="text-[var(--color-accent-600)] hover:underline">
                                {{ sp.transfer_mechanism }}
                            </a>
                            <span v-else-if="sp.transfer_mechanism">{{ sp.transfer_mechanism }}</span>
                            <span v-else>NEEDS OWNER INPUT</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p class="mt-6">
                Gostovanje aplikacije, podatkovne baze, varnostnih kopij in e-poštnega ponudnika (ponudniki, ki bodo
                dejansko hranili podatke vaših strank) na tem seznamu še ni, ker izbira produkcijske infrastrukture
                še ni dokončana — <strong>NEEDS OWNER INPUT</strong>. Take ponudnike bomo na ta seznam dodali takoj,
                ko bodo dejansko izbrani in v uporabi, še preden začnejo dejansko obdelovati vaše podatke.
            </p>

            <p class="mt-4">
                Meta Platforms, Inc. je pri povezovanju Instagrama in Facebook Messengerja lahko tudi samostojen
                upravljavec določenih podatkov (npr. za lastne varnostne in analitične namene) — to je urejeno v
                njihovih lastnih pogojih uporabe in politiki zasebnosti, ne v tem dokumentu.
            </p>

            <h3 class="mt-8">Obveščanje o novih ali zamenjanih podobdelovalcih</h3>
            <p>
                Ta stran ni edini mehanizem obveščanja o novih ali zamenjanih Article 28 podobdelovalcih. Preden
                Beležka za obdelavo podatkov vaših strank vključi novega ali zamenja obstoječega podobdelovalca, o
                tem po e-pošti in/ali z obvestilom v aplikaciji vnaprej obvesti lastnika delovnega prostora in mu da
                razumno možnost, da temu ugovarja, v skladu z Dogovorom o obdelavi osebnih podatkov (10. člen).
            </p>
        </section>

        <section id="lastni-ponudniki" class="mt-10">
            <h2>2. Ponudniki, ki obdelujejo VAŠE lastne podatke kot uporabnika Beležke</h2>
            <p>
                Spodnji ponudniki ne prejemajo podatkov o vaših strankah — obdelujejo izključno vaše lastne podatke
                kot uporabnika/delovnega prostora Beležke (npr. obračun naročnine, dostava push obvestil vam
                osebno). Niso Article 28 podobdelovalci vaših podatkov o strankah, so pa razkriti kot prejemniki v
                <a class="text-[var(--color-accent-600)] hover:underline" :href="route('legal.privacy')">Politiki zasebnosti</a>.
            </p>

            <table class="mt-4">
                <thead>
                    <tr>
                        <th>Ponudnik</th>
                        <th>Namen</th>
                        <th>Vrsta podatkov (vaših, kot uporabnika)</th>
                        <th>Lokacija obdelave</th>
                        <th>Prenosni mehanizem (izven EGP)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in legal.account_billing_providers" :key="p.name">
                        <td>{{ p.name }}</td>
                        <td>{{ p.purpose }}</td>
                        <td>{{ p.data }}</td>
                        <td>{{ p.location ?? 'NEEDS OWNER INPUT' }}</td>
                        <td>
                            <a v-if="p.transfer_mechanism && p.transfer_more_info_url" :href="p.transfer_more_info_url" target="_blank" rel="noopener" class="text-[var(--color-accent-600)] hover:underline">
                                {{ p.transfer_mechanism }}
                            </a>
                            <span v-else-if="p.transfer_mechanism">{{ p.transfer_mechanism }}</span>
                            <span v-else>NEEDS OWNER INPUT</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="mt-10">
            <p v-if="legal.legal_email" class="text-sm text-neutral-500">
                Vprašanja v zvezi s tem seznamom: <a class="text-[var(--color-accent-600)] hover:underline" :href="`mailto:${legal.legal_email}`">{{ legal.legal_email }}</a>.
            </p>
        </section>
    </LegalLayout>
</template>
