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

interface ExternalPlatform {
    name: string;
    purpose: string;
    role_note: string;
}

interface LegalConfig {
    subprocessors: Provider[];
    account_billing_providers: Provider[];
    external_platforms: ExternalPlatform[];
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
                    <tr v-if="!legal.subprocessors.length">
                        <td colspan="5" class="text-neutral-400">Trenutno ni objavljenih ponudnikov v tej kategoriji.</td>
                    </tr>
                    <tr v-for="sp in legal.subprocessors" :key="sp.name">
                        <td>{{ sp.name }}</td>
                        <td>{{ sp.purpose }}</td>
                        <td>{{ sp.data }}</td>
                        <td>{{ sp.location ?? '—' }}</td>
                        <td>
                            <a v-if="sp.transfer_mechanism && sp.transfer_more_info_url" :href="sp.transfer_more_info_url" target="_blank" rel="noopener" class="text-[var(--color-accent-600)] hover:underline">
                                {{ sp.transfer_mechanism }}
                            </a>
                            <span v-else-if="sp.transfer_mechanism">{{ sp.transfer_mechanism }}</span>
                            <span v-else>—</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p class="mt-6">
                Ta seznam se dopolnjuje sproti, kot se v produkcijo dodajajo dejanski ponudniki, ki hranijo ali
                obdelujejo podatke vaših strank (npr. gostovanje aplikacije, podatkovna baza, varnostne kopije,
                e-poštni ponudnik) — preden dejansko začnejo obdelovati vaše podatke.
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
                        <td>{{ p.location ?? '—' }}</td>
                        <td>
                            <a v-if="p.transfer_mechanism && p.transfer_more_info_url" :href="p.transfer_more_info_url" target="_blank" rel="noopener" class="text-[var(--color-accent-600)] hover:underline">
                                {{ p.transfer_mechanism }}
                            </a>
                            <span v-else-if="p.transfer_mechanism">{{ p.transfer_mechanism }}</span>
                            <span v-else>—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section id="zunanje-platforme" class="mt-10">
            <h2>3. Zunanje platforme, ki jih poveže delovni prostor sam</h2>
            <p>
                Nekatere povezave (npr. Instagram Direct ali Facebook Messenger) delovni prostor vzpostavi sam, s
                svojim uporabniškim računom pri tej platformi. Beležka teh ponudnikov ne uvršča med Article 28
                podobdelovalce, dokler njihova vloga za to konkretno funkcijo ni potrjena iz njihovih lastnih
                veljavnih pogojev uporabe.
            </p>

            <table class="mt-4">
                <thead>
                    <tr>
                        <th>Platforma</th>
                        <th>Namen povezave</th>
                        <th>Vloga</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="ep in legal.external_platforms" :key="ep.name">
                        <td>{{ ep.name }}</td>
                        <td>{{ ep.purpose }}</td>
                        <td>{{ ep.role_note }}</td>
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
