<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LegalLayout from '@/Layouts/LegalLayout.vue';

interface LegalConfig {
    privacy_version: string;
    legal_email: string | null;
    dpo_contact: string | null;
    supervisory_authority_name: string;
    supervisory_authority_url: string;
}

defineProps<{ legal: LegalConfig }>();

const toc = [
    { id: 'upravljavec', label: '1. Kdo je upravljavec' },
    { id: 'podatki', label: '2. Katere podatke obdelujemo' },
    { id: 'registracija', label: '3. Registracija in uporabniški račun' },
    { id: 'nameni', label: '4. Namen obdelave' },
    { id: 'podlage', label: '5. Pravne podlage' },
    { id: 'podpora', label: '6. Podpora' },
    { id: 'varnostni-podatki', label: '7. Varnostni in revizijski podatki' },
    { id: 'demo', label: '8. Demo' },
    { id: 'placila', label: '9. Plačila' },
    { id: 'analitika', label: '10. Analitika / sledenje' },
    { id: 'prejemniki', label: '11. Prejemniki in podobdelovalci' },
    { id: 'prenosi', label: '12. Prenosi v tretje države' },
    { id: 'hramba', label: '13. Hramba podatkov' },
    { id: 'pravice', label: '14. Pravice posameznika' },
    { id: 'pritozba', label: '15. Pravica do pritožbe' },
    { id: 'varnost', label: '16. Varnost' },
    { id: 'spremembe', label: '17. Spremembe politike' },
    { id: 'kontakt', label: '18. Kontakt' },
];
</script>

<template>
    <Head title="Politika zasebnosti — Beležka">
        <meta name="description" content="Kako Beležka obdeluje osebne podatke svojih uporabnikov." />
    </Head>

    <LegalLayout title="Politika zasebnosti" :updated-at="legal.privacy_version" :toc="toc">
        <section id="upravljavec">
            <h2>1. Kdo je upravljavec</h2>
            <p>
                Za osebne podatke, ki jih obdelujemo o vas kot uporabniku Beležke (registracija, prijava, podpora),
                je upravljavec ponudnik storitve — podatki so navedeni na strani
                <a class="text-[var(--color-accent-600)] hover:underline" :href="route('legal.provider')">Podatki o ponudniku</a>.
            </p>
        </section>

        <section id="podatki">
            <h2>2. Katere podatke obdelujemo</h2>
            <p>
                O vas kot uporabniku obdelujemo: ime, e-poštni naslov, zgoščeno (hash) vrednost gesla, IP naslov in
                podatke o napravi/brskalniku pri varnostnih dogodkih, ter — če to omogočite — endpoint push
                naročnine za obveščanje v brskalniku. Ločeno od tega vaše podjetje samo vnaša podatke o svojih
                strankah (glejte 3. člen Pogojev poslovanja in Dogovor o obdelavi osebnih podatkov) — za te podatke
                Beležka ni upravljavec, glejte 3. člen spodaj.
            </p>
        </section>

        <section id="registracija">
            <h2>3. Registracija in uporabniški račun</h2>
            <p>
                Ko sta Beležka upravljavec in kdaj obdelovalec: za vaš uporabniški račun (ime, e-pošta, geslo,
                nastavitve) smo upravljavec — mi določamo namen in način obdelave. Za podatke, ki jih vi (poslovni
                uporabnik) vnesete o svojih strankah, ste upravljavec vi, Beležka pa jih v vašem imenu obdeluje kot
                obdelovalec. Če želi vaša stranka uveljaviti pravice glede podatkov, ki jih o njej hrani vaše
                podjetje, naj se praviloma obrne neposredno na vas; Beležka vam pri tem omogoča orodja (izvoz,
                izbris/anonimizacija podatkov stranke) za odziv na tako zahtevo.
            </p>
        </section>

        <section id="nameni">
            <h2>4. Namen obdelave</h2>
            <p>
                Vaše podatke obdelujemo za: ustvarjanje in vodenje uporabniškega računa, zagotavljanje delovanja
                storitve, komunikacijo v zvezi s podporo, varnost in preprečevanje zlorab ter (če to omogočite)
                pošiljanje push obvestil o opomnikih.
            </p>
        </section>

        <section id="podlage">
            <h2>5. Pravne podlage</h2>
            <p>
                Obdelavo podatkov v zvezi z vašim računom izvajamo na podlagi izvajanja pogodbe (uporaba storitve, ki
                jo ste zahtevali z registracijo), varnostne in revizijske podatke obdelujemo na podlagi zakonitega
                interesa (preprečevanje zlorab, zagotavljanje varnosti), za push obvestila pa na podlagi vaše
                privolitve (dovoljenje brskalnika), ki jo lahko kadarkoli prekličete.
            </p>
        </section>

        <section id="podpora">
            <h2>6. Podpora</h2>
            <p>
                Če nam pišete v zvezi s podporo, obdelamo vsebino vaše komunikacije, da vam lahko pomagamo. Podpora
                nima stalnega dostopa do vsebine vaših pogovorov s strankami — tak dostop mora lastnik delovnega
                prostora izrecno in začasno odobriti, vsak dostop pa se beleži.
            </p>
        </section>

        <section id="varnostni-podatki">
            <h2>7. Varnostni in revizijski podatki</h2>
            <p>
                Za varnost sistema beležimo varnostno-revizijsko sled (kdo, kdaj, katero dejanje) — ti zapisi ne
                vsebujejo vsebine vaših sporočil ali opomb, temveč samo identifikatorje in dejanja.
            </p>
        </section>

        <section id="demo">
            <h2>8. Demo</h2>
            <p>
                Preizkus demo različice ne zahteva registracije in ne zbira nobenih vaših osebnih podatkov — ustvari
                se ločen, umeten demo delovni prostor z vzorčnimi (izmišljenimi) podatki, ki se samodejno izbriše
                nekaj ur po ustvarjanju. Demo ne omogoča prave povezave z Instagramom ali Messengerjem.
            </p>
        </section>

        <section id="placila">
            <h2>9. Plačila</h2>
            <p>
                Storitev trenutno ne obračunava plačil in nima vzpostavljenega plačilnega sistema, zato v tej fazi ne
                obdelujemo plačilnih podatkov.
            </p>
        </section>

        <section id="analitika">
            <h2>10. Analitika / sledenje</h2>
            <p>
                Na spletni strani trenutno ne uporabljamo orodij za analitiko ali sledenje obiskovalcem. Podrobnosti
                (vključno s piškotki, ki jih dejansko uporabljamo) najdete v
                <a class="text-[var(--color-accent-600)] hover:underline" :href="route('legal.cookies')">Politiki piškotkov</a>.
            </p>
        </section>

        <section id="prejemniki">
            <h2>11. Prejemniki in podobdelovalci</h2>
            <p>
                Vaših podatkov ne prodajamo. Za delovanje storitve se lahko zanašamo na omejeno število
                podobdelovalcev — celoten, ažuren seznam je na voljo na strani
                <a class="text-[var(--color-accent-600)] hover:underline" :href="route('legal.subprocessors')">Podobdelovalci</a>.
            </p>
        </section>

        <section id="prenosi">
            <h2>12. Prenosi v tretje države</h2>
            <p>
                Nekateri ponudniki, na katere se zanašamo (npr. Meta Platforms, Inc.), lahko podatke obdelujejo tudi
                izven Evropskega gospodarskega prostora. Ustrezen mehanizem za tak prenos (npr. standardne pogodbene
                klavzule) določa posamezni ponudnik v svojih lastnih pogojih; ko bomo izbrali dodatne ponudnike
                (npr. za gostovanje), bomo to politiko ustrezno dopolnili.
            </p>
        </section>

        <section id="hramba">
            <h2>13. Hramba podatkov</h2>
            <p>
                Podatke vašega računa hranimo, dokler je vaš račun aktiven. Ob izbrisu delovnega prostora se podatki
                po vnaprej določenem obdobju za obnovitev trajno izbrišejo iz aktivne aplikacije. Varnostno-revizijski
                zapisi se hranijo ločeno in dlje, saj služijo varnosti in odgovornosti; obdobje hrambe teh zapisov
                še ni dokončno določeno.
            </p>
        </section>

        <section id="pravice">
            <h2>14. Pravice posameznika</h2>
            <p>
                Glede podatkov, za katere smo upravljavec (vaš uporabniški račun), imate pravico do dostopa,
                popravka, izbrisa, omejitve obdelave, prenosljivosti podatkov in ugovora obdelavi, kadar ta temelji
                na zakonitem interesu. Privolitev za push obvestila lahko kadarkoli prekličete v nastavitvah
                brskalnika.
            </p>
        </section>

        <section id="pritozba">
            <h2>15. Pravica do pritožbe pri Informacijskem pooblaščencu</h2>
            <p>
                Če menite, da obdelava vaših osebnih podatkov krši predpise, imate pravico vložiti pritožbo pri
                <a class="text-[var(--color-accent-600)] hover:underline" :href="legal.supervisory_authority_url" target="_blank" rel="noopener">
                    {{ legal.supervisory_authority_name }}
                </a>.
            </p>
        </section>

        <section id="varnost">
            <h2>16. Varnost</h2>
            <p>
                Za zaščito podatkov uporabljamo ukrepe kot so: šifriranje posebej občutljivih vsebin (npr. besedila
                sporočil in opomb strank) na strežniku, ločevanje podatkov med delovnimi prostori, omejen in
                nadzorovan dostop podpore ter beleženje varnostno pomembnih dogodkov. To niso absolutna jamstva —
                nobenega sistema ni mogoče označiti za "100 % varnega", zato tega tudi ne trdimo.
            </p>
        </section>

        <section id="spremembe">
            <h2>17. Spremembe politike</h2>
            <p>
                To politiko lahko občasno posodobimo. Datum zadnje spremembe je naveden na vrhu strani.
            </p>
        </section>

        <section id="kontakt">
            <h2>18. Kontakt</h2>
            <p v-if="legal.legal_email">
                Za vprašanja v zvezi z obdelavo osebnih podatkov nas kontaktirajte na
                <a class="text-[var(--color-accent-600)] hover:underline" :href="`mailto:${legal.legal_email}`">{{ legal.legal_email }}</a
                >.<span v-if="legal.dpo_contact"> Pooblaščena oseba za varstvo podatkov: {{ legal.dpo_contact }}.</span>
            </p>
        </section>
    </LegalLayout>
</template>
