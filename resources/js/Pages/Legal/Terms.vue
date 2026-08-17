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
    { id: 'elektronska-pogodba', label: '8. Elektronsko sklenitev pogodbe' },
    { id: 'narocnina', label: '9. Naročnina in plačilo' },
    { id: 'prodajni-dokumenti', label: '10. Izdajanje prodajnih dokumentov' },
    { id: 'dostop', label: '11. Dostop in uporaba storitve' },
    { id: 'obveznosti', label: '12. Obveznosti uporabnika' },
    { id: 'podatki-strank', label: '13. Podatki uporabnika in njegovih strank' },
    { id: 'varstvo-podatkov', label: '14. Varstvo osebnih podatkov' },
    { id: 'podpora', label: '15. Dostop podpore' },
    { id: 'razpolozljivost', label: '16. Razpoložljivost in spremembe storitve' },
    { id: 'meta', label: '17. Zunanje storitve / Meta' },
    { id: 'izvoz', label: '18. Izvoz podatkov' },
    { id: 'prenehanje', label: '19. Prenehanje uporabe' },
    { id: 'izbris', label: '20. Izbris delovnega prostora' },
    { id: 'lastnina', label: '21. Intelektualna lastnina' },
    { id: 'odgovornost', label: '22. Omejitev odgovornosti' },
    { id: 'spremembe', label: '23. Spremembe pogojev' },
    { id: 'pravo', label: '24. Veljavno pravo in reševanje sporov' },
    { id: 'kontakt', label: '25. Kontakt' },
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
                Ti pogoji poslovanja urejajo uporabo storitve Beležka — plačljive spletne aplikacije (SaaS) za
                upravljanje sporočil, strank, naročil, terminov in prodajnih dokumentov, namenjene poslovnim
                uporabnikom. Z ustvarjanjem uporabniškega računa potrdite, da ste te pogoje prebrali in se z njimi
                strinjate, skupaj z Dogovorom o obdelavi osebnih podatkov (DPA).
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
                izdelkov/storitev, opomnike (vključno s push obvestili v brskalniku), izdajanje prodajnih dokumentov
                (računi, predračuni, dobropisi/knjigovodske korekcije, zunanji PDF dokumenti) in njihovo pošiljanje
                prek povezanih pogovorov s strankami, ter izvoz podatkov delovnega prostora. Nabor funkcij se lahko
                sčasoma spreminja in dopolnjuje. Podrobnosti o prodajnih dokumentih so v 10. členu spodaj.
            </p>
        </section>

        <section id="registracija">
            <h2>5. Registracija in uporabniški račun</h2>
            <p>
                Za uporabo storitve je potrebna registracija z imenom, e-poštnim naslovom in geslom. Odgovorni ste za
                točnost svojih podatkov in za varovanje gesla. Z registracijo se samodejno ustvari en delovni
                prostor, katerega lastnik ste. Registracija sama po sebi ne pomeni aktivne naročnine — za dostop do
                storitve je potrebna tudi aktivacija naročnine po 9. členu spodaj.
            </p>
        </section>

        <section id="delovni-prostori">
            <h2>6. Delovni prostori in člani</h2>
            <p>
                Delovni prostor predstavlja vaše podjetje znotraj Beležke in vsebuje vse njegove stranke, pogovore,
                naročila, termine in prodajne dokumente. Lastnik delovnega prostora ima nad njim polni nadzor,
                vključno z upravljanjem naročnine, izvozom in izbrisom podatkov.
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

        <section id="elektronska-pogodba">
            <h2>8. Elektronsko sklenitev pogodbe</h2>
            <p>
                Pogodbeno razmerje med vami in Beležko se sklene elektronsko, po naslednjem postopku: (1)
                registracija uporabniškega računa, (2) izrecna potrditev strinjanja s temi pogoji poslovanja in
                Dogovorom o obdelavi osebnih podatkov ob registraciji, (3) aktivacija naročnine, kot je opisano v 9.
                členu, in (4) potrditev plačila prek Stripe Checkout, po kateri prejmete potrdilo o zaključenem
                nakupu. Celoten postopek poteka v slovenskem jeziku. Ta pogoji in Dogovor o obdelavi osebnih podatkov
                sta vam kadarkoli javno dostopna na tej spletni strani za tiskanje in shranjevanje (npr. v PDF prek
                brskalnika). Pred dokončno potrditvijo plačila pri Stripe imate možnost preveriti in popraviti
                vnesene podatke o plačilu, preden je transakcija zaključena.
            </p>
        </section>

        <section id="narocnina">
            <h2>9. Naročnina in plačilo</h2>
            <p>
                Beležka je plačljiva storitev za poslovne uporabnike. Uporaba storitve je vezana na eno mesečno
                naročnino delovnega prostora ("Naročnina"), ki se samodejno obnavlja vsak obračunski mesec, dokler je
                ne prekličete. Cena, ki velja za vas, je prikazana pred zaključkom nakupa na strani aktivacije
                naročnine oziroma na naši spletni strani, skupaj z jasno navedbo, ali cena vključuje DDV/davke ali
                ne — glede na dejansko konfiguracijo obračuna. Če na strani ni prikazane cene, plačilo ni mogoče,
                dokler cena ni objavljena.
            </p>
            <p>
                Plačilo poteka izključno prek Stripe Checkout (varne, gostovane plačilne strani) in se upravlja prek
                Stripe Customer Portal, kjer lahko posodobite način plačila in upravljate naročnino. Beležka ne vidi
                in ne shranjuje podatkov o vaši plačilni kartici — te obdeluje izključno Stripe. Naročnino lahko
                kadarkoli prekličete prek Stripe Customer Portal; ob preklicu dostop do storitve ostane na voljo do
                konca že plačanega obračunskega obdobja, nato pa se ne obnovi več.
            </p>
            <p>
                <strong>Preklic naročnine ne pomeni izbrisa podatkov delovnega prostora.</strong> Ob prenehanju
                naročnine (potek ali preklic) se dostop do storitve omeji, podatki delovnega prostora pa ostanejo
                shranjeni — izbris delovnega prostora je ločen, izrecen postopek, opisan v 20. členu spodaj, ki ga
                mora lastnik delovnega prostora sprožiti posebej.
            </p>
            <p>
                Če plačilo ob obnovitvi naročnine ni uspešno, se lahko dostop do storitve omeji v skladu z dejansko
                veljavno politiko obravnave neuspelih plačil, dokler plačila ne poravnate ali ne posodobite načina
                plačila. Storitev trenutno ne ponuja brezplačnega preizkusnega obdobja (free trial), razen če je
                tako izrecno navedeno na strani aktivacije naročnine.
            </p>
        </section>

        <section id="prodajni-dokumenti">
            <h2>10. Izdajanje prodajnih dokumentov</h2>
            <p>
                Beležka vam omogoča izdajanje računov, predračunov in dobropisov/knjigovodskih korekcij (storno), kot
                tudi prilaganje in pošiljanje zunanjih PDF dokumentov, vključno s pošiljanjem teh dokumentov strankam
                prek povezanih pogovorov v Beležki.
            </p>
            <p>
                <strong>Beležka je pri tem operativno orodje, ne računovodski ali davčno-svetovalni servis.</strong>
                Za vsebinsko in oblikovno pravilnost izdanih dokumentov, njihovo skladnost z veljavnimi
                računovodskimi in davčnimi predpisi (vključno z zakonsko predpisano vsebino dokumentov), zakonsko
                hrambo teh dokumentov ter za morebitne obveznosti fiskalizacije (npr. po predpisih FURS) ste
                odgovorni izključno vi kot poslovni uporabnik, ki dokumente izdaja. Beležka ne trdi in ne jamči, da
                izpolnjuje zahteve fiskalizacije ali druge specifične davčno-računovodske zahteve, razen če je to
                izrecno navedeno drugje v teh pogojih.
            </p>
        </section>

        <section id="dostop">
            <h2>11. Dostop in uporaba storitve</h2>
            <p>
                Do storitve dostopate prek spletnega brskalnika. Za nemoteno delovanje priporočamo posodobljen
                brskalnik in stabilno internetno povezavo.
            </p>
        </section>

        <section id="obveznosti">
            <h2>12. Obveznosti uporabnika</h2>
            <p>
                Storitve ne smete uporabljati za nezakonite namene, pošiljanje neželenih sporočil, kršenje pravic
                tretjih oseb ali nalaganje vsebin, do katerih nimate pravic. Odgovorni ste za vsebino, ki jo vnesete
                ali pošljete prek storitve, vključno z vsebino izdanih prodajnih dokumentov.
            </p>
        </section>

        <section id="podatki-strank">
            <h2>13. Podatki uporabnika in njegovih strank</h2>
            <p>
                Podatki, ki jih o svojih strankah vnesete v Beležko (imena, kontakti, sporočila, naročila, opombe,
                prodajni dokumenti), ostanejo v vaši lasti. Pri tem ste vi (poslovni uporabnik) upravljavec teh
                podatkov, Beležka pa jih obdeluje v vašem imenu kot obdelovalec — podrobno urejeno v
                <a class="text-[var(--color-accent-600)] hover:underline" :href="route('legal.dpa')">Dogovoru o obdelavi osebnih podatkov</a>.
                Sami ste odgovorni za to, da imate za obdelavo podatkov svojih strank ustrezno pravno podlago.
            </p>
        </section>

        <section id="varstvo-podatkov">
            <h2>14. Varstvo osebnih podatkov</h2>
            <p>
                Za podatke, ki jih Beležka obdeluje kot upravljavec (npr. vaš uporabniški račun in podatki o
                obračunu naročnine), velja
                <a class="text-[var(--color-accent-600)] hover:underline" :href="route('legal.privacy')">Politika zasebnosti</a>.
            </p>
        </section>

        <section id="podpora">
            <h2>15. Dostop podpore</h2>
            <p>
                Podpora Beležke nima stalnega dostopa do vsebine vaših pogovorov in strank. Če je za rešitev težave
                potreben vpogled v vaš delovni prostor, ga morate izrecno in začasno dovoliti; dostop lahko
                kadarkoli prekličete, vsak tak vpogled pa se beleži.
            </p>
        </section>

        <section id="razpolozljivost">
            <h2>16. Razpoložljivost in spremembe storitve</h2>
            <p>
                Trudimo se zagotavljati zanesljivo in stalno delovanje storitve, vendar brez zajamčenega SLA
                (dogovorjene ravni razpoložljivosti), razen če je to posebej pisno dogovorjeno. Storitev lahko
                občasno nadgrajujemo, kar lahko pomeni krajše načrtovane prekinitve.
            </p>
        </section>

        <section id="meta">
            <h2>17. Zunanje storitve / Meta</h2>
            <p>
                Instagram in Facebook Messenger sta storitvi družbe Meta Platforms, Inc. in nista pod nadzorom
                Beležke. Razpoložljivost, spremembe vmesnika ali politik teh storitev so izven našega nadzora in
                lahko vplivajo na delovanje povezanih kanalov v Beležki.
            </p>
        </section>

        <section id="izvoz">
            <h2>18. Izvoz podatkov</h2>
            <p>
                Kot lastnik delovnega prostora lahko kadarkoli izvozite podatke svojega delovnega prostora v
                strojno berljivi obliki, prav tako lahko izvozite ali izbrišete/anonimizirate podatke posamezne
                stranke — glejte nastavitve delovnega prostora.
            </p>
        </section>

        <section id="prenehanje">
            <h2>19. Prenehanje uporabe</h2>
            <p>
                Uporabo storitve lahko kadarkoli prenehate z izbrisom svojega uporabniškega računa. Če ste edini
                član svojega delovnega prostora, se ob izbrisu računa izbriše tudi delovni prostor; če ima delovni
                prostor tudi druge člane, izbris računa ni mogoč, dokler lastništva ne prenesete ali delovnega
                prostora ne izbrišete. Prenehanje uporabniškega računa ni enako preklicu naročnine (9. člen) —
                priporočamo, da pred izbrisom računa najprej uredite naročnino prek Stripe Customer Portal.
            </p>
        </section>

        <section id="izbris">
            <h2>20. Izbris delovnega prostora</h2>
            <p>
                Lastnik delovnega prostora lahko naroči njegov izbris — ta postopek je ločen od preklica naročnine
                (9. člen) in ga je treba sprožiti izrecno. Delovni prostor se najprej označi za izbris, podatke pa je
                mogoče v določenem obdobju za obnovitev še vedno obnoviti s preklicem izbrisa. Po izteku tega obdobja
                se podatki delovnega prostora trajno izbrišejo iz aktivne aplikacije. Ne obljubljamo trenutnega
                izbrisa iz varnostnih kopij, kadar te obstajajo.
            </p>
        </section>

        <section id="lastnina">
            <h2>21. Intelektualna lastnina</h2>
            <p>
                Programska oprema, oblikovanje in znamka Beležka so last ponudnika. Uporaba storitve vam ne
                podeljuje nobenih pravic nad njimi, razen pravice do uporabe storitve v skladu s temi pogoji.
            </p>
        </section>

        <section id="odgovornost">
            <h2>22. Omejitev odgovornosti</h2>
            <p>
                Beležka je orodje, ki ga uporablja vaše podjetje — za lastno razmerje do svojih strank, za vsebino in
                zakonitost izdanih prodajnih dokumentov ter za zakonitost svojega poslovanja ste odgovorni sami. V
                mejah, ki jih dopušča veljavno pravo, Beležka ne odgovarja za posredno škodo (npr. izgubljeni
                dobiček) niti za škodo, nastalo zaradi delovanja ali nedelovanja zunanjih storitev (npr. Meta,
                Stripe), na katere nimamo vpliva. Ta omejitev ne izključuje odgovornosti, ki je po zakonu ni mogoče
                izključiti.
            </p>
        </section>

        <section id="spremembe">
            <h2>23. Spremembe pogojev</h2>
            <p>
                Te pogoje lahko občasno posodobimo. Datum zadnje spremembe je naveden na vrhu strani. Pri
                pomembnejših spremembah vas bomo o tem obvestili in po potrebi zaprosili za ponovno potrditev.
            </p>
        </section>

        <section id="pravo">
            <h2>24. Veljavno pravo in reševanje sporov</h2>
            <p>
                Za te pogoje velja pravo {{ legal.governing_law }}. Morebitne spore bomo najprej poskušali rešiti
                sporazumno.
                <span v-if="legal.competent_court">Za spore, ki jih ni mogoče rešiti sporazumno, je pristojno {{ legal.competent_court }}.</span>
            </p>
        </section>

        <section id="kontakt">
            <h2>25. Kontakt</h2>
            <p v-if="legal.legal_email">
                Za vprašanja v zvezi s temi pogoji nas kontaktirajte na
                <a class="text-[var(--color-accent-600)] hover:underline" :href="`mailto:${legal.legal_email}`">{{ legal.legal_email }}</a>.
            </p>
        </section>
    </LegalLayout>
</template>
