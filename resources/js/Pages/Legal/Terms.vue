<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LegalLayout from '@/Layouts/LegalLayout.vue';

interface LegalConfig {
    terms_version: string;
    legal_email: string | null;
    governing_law: string;
    competent_court: string | null;
}

defineProps<{ legal: LegalConfig }>();

const toc = [
    { id: 'splosno', label: '1. Splošno' },
    { id: 'ponudnik', label: '2. Podatki o ponudniku' },
    { id: 'komu', label: '3. Komu je Beležka namenjena' },
    { id: 'opis', label: '4. Opis storitve' },
    { id: 'registracija', label: '5. Registracija in uporabniški račun' },
    { id: 'delovni-prostori', label: '6. Delovni prostori in člani' },
    { id: 'povezovanje', label: '7. Povezovanje zunanjih storitev' },
    { id: 'narocnina', label: '8. Naročnina in plačilo' },
    { id: 'dostop', label: '9. Dostop in uporaba storitve' },
    { id: 'obveznosti', label: '10. Obveznosti uporabnika' },
    { id: 'podatki-strank', label: '11. Podatki uporabnika in njegovih strank' },
    { id: 'varstvo-podatkov', label: '12. Varstvo osebnih podatkov' },
    { id: 'podpora', label: '13. Dostop podpore' },
    { id: 'razpolozljivost', label: '14. Razpoložljivost in spremembe storitve' },
    { id: 'meta', label: '15. Zunanje storitve / Meta' },
    { id: 'izvoz', label: '16. Izvoz podatkov' },
    { id: 'prenehanje', label: '17. Prenehanje uporabe' },
    { id: 'izbris', label: '18. Izbris delovnega prostora' },
    { id: 'lastnina', label: '19. Intelektualna lastnina' },
    { id: 'odgovornost', label: '20. Omejitev odgovornosti' },
    { id: 'spremembe', label: '21. Spremembe pogojev' },
    { id: 'pravo', label: '22. Veljavno pravo in reševanje sporov' },
    { id: 'kontakt', label: '23. Kontakt' },
];
</script>

<template>
    <Head title="Pogoji poslovanja — Beležka">
        <meta name="description" content="Pogoji poslovanja za uporabo storitve Beležka." />
    </Head>

    <LegalLayout title="Pogoji poslovanja" :updated-at="legal.terms_version" :toc="toc">
        <section id="splosno">
            <h2>1. Splošno</h2>
            <p>
                Ti pogoji poslovanja urejajo uporabo storitve Beležka — spletne aplikacije za upravljanje sporočil,
                strank, naročil in terminov. Z ustvarjanjem uporabniškega računa potrdite, da ste te pogoje prebrali
                in se z njimi strinjate, skupaj z Dogovorom o obdelavi osebnih podatkov.
            </p>
        </section>

        <section id="ponudnik">
            <h2>2. Podatki o ponudniku</h2>
            <p>
                Storitev zagotavlja ponudnik, katerega polni podatki (naziv, sedež, matična in davčna številka) so
                objavljeni na strani
                <a class="text-[var(--color-accent-600)] hover:underline" :href="route('legal.provider')">Podatki o ponudniku</a>.
                V teh pogojih je ponudnik imenovan "Beležka".
            </p>
        </section>

        <section id="komu">
            <h2>3. Komu je Beležka namenjena</h2>
            <p>
                Beležka je namenjena poslovnim uporabnikom (podjetjem, samostojnim podjetnikom, obrtnikom) za lastno
                poslovno uporabo — ne fizičnim osebam, ki bi storitev uporabljale izključno za zasebne, nepoklicne
                namene. Z registracijo potrjujete, da storitev uporabljate v okviru svoje gospodarske ali poklicne
                dejavnosti.
            </p>
        </section>

        <section id="opis">
            <h2>4. Opis storitve</h2>
            <p>
                Beležka omogoča: sprejemanje in odgovarjanje na sporočila prek povezanih kanalov (Instagram Direct,
                Facebook Messenger), evidenco strank in njihove zgodovine, upravljanje naročil in terminov, katalog
                izdelkov/storitev, opomnike (vključno s push obvestili v brskalniku) in izvoz podatkov delovnega
                prostora. Nabor funkcij se lahko sčasoma spreminja in dopolnjuje.
            </p>
        </section>

        <section id="registracija">
            <h2>5. Registracija in uporabniški račun</h2>
            <p>
                Za uporabo storitve je potrebna registracija z imenom, e-poštnim naslovom in geslom. Odgovorni ste za
                točnost svojih podatkov in za varovanje gesla. Z registracijo se samodejno ustvari en delovni
                prostor, katerega lastnik ste.
            </p>
        </section>

        <section id="delovni-prostori">
            <h2>6. Delovni prostori in člani</h2>
            <p>
                Delovni prostor predstavlja vaše podjetje znotraj Beležke in vsebuje vse njegove stranke, pogovore,
                naročila in termine. Lastnik delovnega prostora ima nad njim polni nadzor, vključno z izvozom in
                izbrisom podatkov.
            </p>
        </section>

        <section id="povezovanje">
            <h2>7. Povezovanje zunanjih storitev</h2>
            <p>
                Beležka omogoča povezavo z Instagramom in Facebook Messengerjem prek uradnega Meta vmesnika (Graph
                API). Za povezavo morate imeti ustrezne pravice nad povezanim Instagram/Facebook računom in se
                strinjati tudi s pogoji uporabe Meta.
            </p>
        </section>

        <section id="narocnina">
            <h2>8. Naročnina in plačilo</h2>
            <p>
                Registracija in uporaba storitve sta trenutno brezplačni — Beležka še ne obračunava naročnine in
                nima vzpostavljenega plačilnega sistema. Ko bomo uvedli plačljive pakete, bomo te pogoje ustrezno
                dopolnili in vas o tem pravočasno obvestili, preden bi vam bilo karkoli zaračunano.
            </p>
        </section>

        <section id="dostop">
            <h2>9. Dostop in uporaba storitve</h2>
            <p>
                Do storitve dostopate prek spletnega brskalnika. Za nemoteno delovanje priporočamo posodobljen
                brskalnik in stabilno internetno povezavo.
            </p>
        </section>

        <section id="obveznosti">
            <h2>10. Obveznosti uporabnika</h2>
            <p>
                Storitve ne smete uporabljati za nezakonite namene, pošiljanje neželenih sporočil, kršenje pravic
                tretjih oseb ali nalaganje vsebin, do katerih nimate pravic. Odgovorni ste za vsebino, ki jo vnesete
                ali pošljete prek storitve.
            </p>
        </section>

        <section id="podatki-strank">
            <h2>11. Podatki uporabnika in njegovih strank</h2>
            <p>
                Podatki, ki jih o svojih strankah vnesete v Beležko (imena, kontakti, sporočila, naročila, opombe),
                ostanejo v vaši lasti. Pri tem ste vi (poslovni uporabnik) upravljavec teh podatkov, Beležka pa jih
                obdeluje v vašem imenu kot obdelovalec — podrobno urejeno v
                <a class="text-[var(--color-accent-600)] hover:underline" :href="route('legal.dpa')">Dogovoru o obdelavi osebnih podatkov</a>.
                Sami ste odgovorni za to, da imate za obdelavo podatkov svojih strank ustrezno pravno podlago.
            </p>
        </section>

        <section id="varstvo-podatkov">
            <h2>12. Varstvo osebnih podatkov</h2>
            <p>
                Za podatke, ki jih Beležka obdeluje kot upravljavec (npr. vaš uporabniški račun), velja
                <a class="text-[var(--color-accent-600)] hover:underline" :href="route('legal.privacy')">Politika zasebnosti</a>.
            </p>
        </section>

        <section id="podpora">
            <h2>13. Dostop podpore</h2>
            <p>
                Podpora Beležke nima stalnega dostopa do vsebine vaših pogovorov in strank. Če je za rešitev težave
                potreben vpogled v vaš delovni prostor, ga morate izrecno in začasno dovoliti; dostop lahko
                kadarkoli prekličete, vsak tak vpogled pa se beleži.
            </p>
        </section>

        <section id="razpolozljivost">
            <h2>14. Razpoložljivost in spremembe storitve</h2>
            <p>
                Trudimo se zagotavljati zanesljivo in stalno delovanje storitve, vendar brez zajamčenega SLA
                (dogovorjene ravni razpoložljivosti) za brezplačno uporabo. Storitev lahko občasno nadgrajujemo, kar
                lahko pomeni krajše načrtovane prekinitve.
            </p>
        </section>

        <section id="meta">
            <h2>15. Zunanje storitve / Meta</h2>
            <p>
                Instagram in Facebook Messenger sta storitvi družbe Meta Platforms, Inc. in nista pod nadzorom
                Beležke. Razpoložljivost, spremembe vmesnika ali politik teh storitev so izven našega nadzora in
                lahko vplivajo na delovanje povezanih kanalov v Beležki.
            </p>
        </section>

        <section id="izvoz">
            <h2>16. Izvoz podatkov</h2>
            <p>
                Kot lastnik delovnega prostora lahko kadarkoli izvozite podatke svojega delovnega prostora v
                strojno berljivi obliki, prav tako lahko izvozite ali izbrišete/anonimizirate podatke posamezne
                stranke — glejte nastavitve delovnega prostora.
            </p>
        </section>

        <section id="prenehanje">
            <h2>17. Prenehanje uporabe</h2>
            <p>
                Uporabo storitve lahko kadarkoli prenehate z izbrisom svojega uporabniškega računa. Če ste edini
                član svojega delovnega prostora, se ob izbrisu računa izbriše tudi delovni prostor; če ima delovni
                prostor tudi druge člane, izbris računa ni mogoč, dokler lastništva ne prenesete ali delovnega
                prostora ne izbrišete.
            </p>
        </section>

        <section id="izbris">
            <h2>18. Izbris delovnega prostora</h2>
            <p>
                Lastnik delovnega prostora lahko naroči njegov izbris. Delovni prostor se najprej označi za izbris,
                podatke pa je mogoče v določenem obdobju za obnovitev še vedno obnoviti s preklicem izbrisa. Po
                izteku tega obdobja se podatki delovnega prostora trajno izbrišejo iz aktivne aplikacije. Ne
                obljubljamo trenutnega izbrisa iz varnostnih kopij, kadar te obstajajo.
            </p>
        </section>

        <section id="lastnina">
            <h2>19. Intelektualna lastnina</h2>
            <p>
                Programska oprema, oblikovanje in znamka Beležka so last ponudnika. Uporaba storitve vam ne
                podeljuje nobenih pravic nad njimi, razen pravice do uporabe storitve v skladu s temi pogoji.
            </p>
        </section>

        <section id="odgovornost">
            <h2>20. Omejitev odgovornosti</h2>
            <p>
                Beležka je orodje, ki ga uporablja vaše podjetje — za lastno razmerje do svojih strank in za
                zakonitost svojega poslovanja ste odgovorni sami. V mejah, ki jih dopušča veljavno pravo, Beležka ne
                odgovarja za posredno škodo (npr. izgubljeni dobiček) niti za škodo, nastalo zaradi delovanja ali
                nedelovanja zunanjih storitev (npr. Meta), na katere nimamo vpliva. Ta omejitev ne izključuje
                odgovornosti, ki je po zakonu ni mogoče izključiti.
            </p>
        </section>

        <section id="spremembe">
            <h2>21. Spremembe pogojev</h2>
            <p>
                Te pogoje lahko občasno posodobimo. Datum zadnje spremembe je naveden na vrhu strani. Pri
                pomembnejših spremembah vas bomo o tem obvestili in po potrebi zaprosili za ponovno potrditev.
            </p>
        </section>

        <section id="pravo">
            <h2>22. Veljavno pravo in reševanje sporov</h2>
            <p>
                Za te pogoje velja pravo {{ legal.governing_law }}. Morebitne spore bomo najprej poskušali rešiti
                sporazumno.
                <span v-if="legal.competent_court">Za spore, ki jih ni mogoče rešiti sporazumno, je pristojno {{ legal.competent_court }}.</span>
            </p>
        </section>

        <section id="kontakt">
            <h2>23. Kontakt</h2>
            <p v-if="legal.legal_email">
                Za vprašanja v zvezi s temi pogoji nas kontaktirajte na
                <a class="text-[var(--color-accent-600)] hover:underline" :href="`mailto:${legal.legal_email}`">{{ legal.legal_email }}</a>.
            </p>
        </section>
    </LegalLayout>
</template>
