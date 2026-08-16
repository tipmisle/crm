<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LegalLayout from '@/Layouts/LegalLayout.vue';

interface LegalConfig {
    cookie_version: string;
    legal_email: string | null;
}

defineProps<{ legal: LegalConfig }>();

const toc = [
    { id: 'uvod', label: 'Uvod' },
    { id: 'kaj-so', label: 'Kaj so piškotki' },
    { id: 'seznam', label: 'Piškotki, ki jih uporabljamo' },
    { id: 'soglasje', label: 'Zakaj ne potrebujemo soglasja' },
    { id: 'analitika', label: 'Sledenje in analitika' },
    { id: 'pisave', label: 'Pisave tretjih ponudnikov' },
    { id: 'push', label: 'Push obvestila' },
    { id: 'spremembe', label: 'Spremembe politike' },
    { id: 'kontakt', label: 'Kontakt' },
];
</script>

<template>
    <Head title="Politika piškotkov — Beležka">
        <meta name="description" content="Kateri piškotki in podobne tehnologije se uporabljajo na strani Beležka." />
    </Head>

    <LegalLayout title="Politika piškotkov" :updated-at="legal.cookie_version" :toc="toc">
        <section id="uvod">
            <h2>Uvod</h2>
            <p>
                Ta stran ne uporablja sledilnih ali oglaševalskih piškotkov. Uporabljamo izključno piškotke, ki so
                nujno potrebni za delovanje storitve. Zato na strani ne prikazujemo pasice za soglasje k piškotkom —
                spodaj pojasnimo, zakaj.
            </p>
        </section>

        <section id="kaj-so">
            <h2>Kaj so piškotki</h2>
            <p>
                Piškotki so majhne besedilne datoteke, ki jih spletna stran shrani v vaš brskalnik, da si zapomni
                določene informacije (na primer, da ste prijavljeni).
            </p>
        </section>

        <section id="seznam">
            <h2>Piškotki, ki jih uporabljamo</h2>
            <table>
                <thead>
                    <tr>
                        <th>Ime</th>
                        <th>Namen</th>
                        <th>Trajanje</th>
                        <th>Nujno potreben</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Piškotek seje (npr. <code>belezka_session</code>)</td>
                        <td>Vzdrževanje prijave in seje med obiskom strani</td>
                        <td>Do izteka seje oz. neaktivnosti</td>
                        <td>Da</td>
                    </tr>
                    <tr>
                        <td><code>XSRF-TOKEN</code></td>
                        <td>Zaščita pred CSRF napadi (ponarejenimi zahtevami v vašem imenu)</td>
                        <td>Do izteka seje</td>
                        <td>Da</td>
                    </tr>
                </tbody>
            </table>
            <p>To sta edina piškotka, ki ju stran nastavi. Ne uporabljamo lokalnega shranjevanja (localStorage/sessionStorage) za sledenje.</p>
        </section>

        <section id="soglasje">
            <h2>Zakaj ne potrebujemo soglasja</h2>
            <p>
                Oba zgornja piškotka sta nujno potrebna za delovanje storitve, ki jo izrecno zahtevate (prijava,
                varna uporaba obrazcev) — zakon (ZEKom-2, ki prenaša evropsko direktivo o zasebnosti in elektronskih
                komunikacijah) za takšne, strogo nujne piškotke ne zahteva vašega predhodnega soglasja.
            </p>
        </section>

        <section id="analitika">
            <h2>Sledenje in analitika</h2>
            <p>
                Trenutno ne uporabljamo Google Analytics, Google Tag Managerja, Meta Pixla ali podobnih orodij za
                sledenje obiskovalcem. V kodi strani obstaja pripravljena, a neaktivna kljuka za morebitno prihodnjo
                analitiko — dokler ni povezana z dejanskim orodjem, ne pošilja nobenih podatkov nikamor. Če bomo v
                prihodnje dodali analitiko ali oglaševalske piškotke, bomo pred njihovo uporabo pridobili vaše
                soglasje in to politiko ustrezno posodobili.
            </p>
        </section>

        <section id="pisave">
            <h2>Pisave tretjih ponudnikov</h2>
            <p>
                Stran nalaga pisavo Rubik neposredno s strežnikov Google Fonts. Pri tem vaš brskalnik naveže
                povezavo na Google-ove strežnike, kar Googlu razkrije vaš naslov IP kot stranski učinek nalaganja
                pisave. To ni piškotek in ne služi sledenju, ga pa transparentno razkrivamo.
            </p>
        </section>

        <section id="push">
            <h2>Push obvestila</h2>
            <p>
                Če se prijavite na push obvestila v brskalniku (npr. za opomnike o zapadlih nalogah), gre za ločen,
                na dovoljenju temelječ mehanizem brskalnika — ne za piškotek. Več o tem v
                <a class="text-[var(--color-accent-600)] hover:underline" :href="route('legal.privacy')">Politiki zasebnosti</a>.
            </p>
        </section>

        <section id="spremembe">
            <h2>Spremembe politike piškotkov</h2>
            <p>
                To politiko lahko občasno posodobimo, na primer če dodamo novo orodje ali funkcijo. Datum zadnje
                spremembe je naveden na vrhu strani.
            </p>
        </section>

        <section id="kontakt">
            <h2>Kontakt</h2>
            <p v-if="legal.legal_email">
                Vprašanja v zvezi s piškotki lahko naslovite na
                <a class="text-[var(--color-accent-600)] hover:underline" :href="`mailto:${legal.legal_email}`">{{ legal.legal_email }}</a>.
            </p>
        </section>
    </LegalLayout>
</template>
