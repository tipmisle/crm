<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LegalLayout from '@/Layouts/LegalLayout.vue';

interface Subprocessor {
    name: string;
    purpose: string;
    data: string;
    location: string | null;
}

interface LegalConfig {
    subprocessors: Subprocessor[];
}

defineProps<{ legal: LegalConfig }>();
</script>

<template>
    <Head title="Podobdelovalci — Beležka">
        <meta name="description" content="Seznam podobdelovalcev, ki jih Beležka uporablja pri obdelavi osebnih podatkov v imenu poslovnih uporabnikov." />
    </Head>

    <LegalLayout title="Podobdelovalci">
        <section>
            <p>
                Ko podjetje uporablja Beležko za obdelavo podatkov o svojih strankah, Beležka nastopa kot obdelovalec
                (glej <a class="text-[var(--color-accent-600)] hover:underline" :href="route('legal.dpa')">Dogovor o obdelavi osebnih podatkov</a>).
                Pri tem se lahko za posamezne dele obdelave zanašamo na spodaj navedene podobdelovalce. Seznam
                vsebuje samo dejansko integrirane ponudnike storitev — ne dodajamo ponudnikov, dokler dejansko niso
                v uporabi.
            </p>

            <table class="mt-4">
                <thead>
                    <tr>
                        <th>Ponudnik</th>
                        <th>Namen</th>
                        <th>Vrsta podatkov</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="sp in legal.subprocessors" :key="sp.name">
                        <td>{{ sp.name }}</td>
                        <td>{{ sp.purpose }}</td>
                        <td>{{ sp.data }}</td>
                    </tr>
                </tbody>
            </table>

            <p class="mt-6">
                Trenutno gostovanje, e-poštnega ponudnika in morebitne druge infrastrukturne ponudnike storitev
                (npr. gostovanje podatkovne baze, varnostne kopije) še izbiramo in jih na tem seznamu zato še ni.
                Seznam bomo posodobili takoj, ko bomo izbrali dodatne ponudnike storitev.
            </p>

            <p class="mt-4">
                Meta Platforms, Inc. je pri povezovanju Instagrama in Facebook Messengerja lahko tudi samostojen
                upravljavec določenih podatkov (npr. za lastne varnostne in analitične namene) — to je urejeno v
                njihovih lastnih pogojih uporabe in politiki zasebnosti, ne v tem dokumentu.
            </p>
        </section>
    </LegalLayout>
</template>
