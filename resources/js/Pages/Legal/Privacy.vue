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

interface RetentionConfig {
    workspace_grace_days: number;
    export_hours: number;
    audit_log_days: number | null;
}

defineProps<{ legal: LegalConfig; retention: RetentionConfig }>();

const toc = [
    { id: 'upravljavec', label: '1. Kdo je upravljavec' },
    { id: 'podatki', label: '2. Katere podatke obdelujemo' },
    { id: 'registracija', label: '3. Registracija in uporabniški račun' },
    { id: 'obvezni-podatki', label: '4. Obvezni podatki za sklenitev pogodbe' },
    { id: 'nameni', label: '5. Namen obdelave' },
    { id: 'podlage', label: '6. Pravne podlage' },
    { id: 'avtomatizirano', label: '7. Avtomatizirano odločanje in oblikovanje profilov' },
    { id: 'podpora', label: '8. Podpora' },
    { id: 'varnostni-podatki', label: '9. Varnostni in revizijski podatki' },
    { id: 'demo', label: '10. Demo' },
    { id: 'placila', label: '11. Plačila in obračun naročnine' },
    { id: 'analitika', label: '12. Analitika / sledenje' },
    { id: 'prejemniki', label: '13. Prejemniki in podobdelovalci' },
    { id: 'prenosi', label: '14. Prenosi v tretje države' },
    { id: 'hramba', label: '15. Hramba podatkov' },
    { id: 'pravice', label: '16. Pravice posameznika' },
    { id: 'pritozba', label: '17. Pravica do pritožbe' },
    { id: 'varnost', label: '18. Varnost' },
    { id: 'spremembe', label: '19. Spremembe politike' },
    { id: 'kontakt', label: '20. Kontakt' },
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
                Za osebne podatke, ki jih obdelujemo o vas kot uporabniku Beležke (registracija, prijava, obračun
                naročnine, podpora), je upravljavec ponudnik storitve — podatki so navedeni na strani
                <a class="text-[var(--color-accent-600)] hover:underline" :href="route('legal.provider')">Podatki o ponudniku</a>.
                Ta politika je obvestilo o obdelavi osebnih podatkov, ne pogodbeni dokument, ki bi ga bilo treba
                posebej sprejeti — ob registraciji izrecno sprejmete le Pogoje poslovanja in Dogovor o obdelavi
                osebnih podatkov (DPA).
            </p>
        </section>

        <section id="podatki">
            <h2>2. Katere podatke obdelujemo</h2>
            <p>O vas kot uporabniku Beležke (kot upravljavec) obdelujemo naslednje kategorije podatkov:</p>
            <ul>
                <li><strong>Podatki o računu/profilu:</strong> ime, e-poštni naslov, zgoščena (hash) vrednost gesla.</li>
                <li><strong>Podatki o delovnem prostoru/podjetju:</strong> naziv delovnega prostora in nastavitve, ki jih vnesete kot lastnik/član (npr. podatki za izdajanje prodajnih dokumentov — naziv podjetja, naslov, davčna številka, logotip).</li>
                <li><strong>Identifikatorji naročnine in plačil:</strong> Stripe ID stranke in naročnine, status naročnine, obračunski e-poštni naslov — glejte 11. člen; podatkov o plačilni kartici sami ne shranjujemo.</li>
                <li><strong>Zapisi o pravni privolitvi:</strong> kateri različici Pogojev poslovanja in DPA ste ob registraciji sprejeli in kdaj.</li>
                <li><strong>Podatki podpore, varnosti in revizijske sledi:</strong> vsebina vaše komunikacije s podporo; IP naslov in podatki o napravi/brskalniku pri varnostnih dogodkih; dnevnik pomembnejših dejanj (kdo, kdaj, katero dejanje) — glejte 9. člen.</li>
                <li><strong>Endpoint push naročnine:</strong> če v brskalniku omogočite push obvestila za opomnike.</li>
                <li><strong>Tehnični podatki o seji:</strong> IP naslov, podatki o brskalniku/napravi in piškotki, potrebni za delovanje in varnost seje — glejte <a class="text-[var(--color-accent-600)] hover:underline" :href="route('legal.cookies')">Politiko piškotkov</a>.</li>
            </ul>
            <p>
                Ločeno od tega vaše podjetje samo vnaša podatke o svojih strankah (glejte 3. člen Pogojev poslovanja
                in Dogovor o obdelavi osebnih podatkov) — za te podatke Beležka ni upravljavec, glejte 3. člen
                spodaj.
            </p>
        </section>

        <section id="registracija">
            <h2>3. Registracija in uporabniški račun</h2>
            <p>
                Ko sta Beležka upravljavec in kdaj obdelovalec: za vaš uporabniški račun in obračun naročnine (ime,
                e-pošta, geslo, nastavitve, identifikatorji naročnine) smo upravljavec — mi določamo namen in način
                obdelave. Za podatke, ki jih vi (poslovni uporabnik) vnesete o svojih strankah, ste upravljavec vi,
                Beležka pa jih v vašem imenu obdeluje kot obdelovalec. Če želi vaša stranka uveljaviti pravice glede
                podatkov, ki jih o njej hrani vaše podjetje, naj se praviloma obrne neposredno na vas; Beležka vam
                pri tem omogoča orodja (izvoz, izbris/anonimizacija podatkov stranke) za odziv na tako zahtevo.
            </p>
        </section>

        <section id="obvezni-podatki">
            <h2>4. Obvezni podatki za sklenitev pogodbe</h2>
            <p>
                Ime, e-poštni naslov in geslo so obvezni podatki za sklenitev pogodbe o uporabi Beležke — brez njih
                registracija ni mogoča. Za aktivacijo plačljive naročnine (9. člen Pogojev poslovanja) je nujna tudi
                uspešna obdelava plačila prek Stripe Checkout; brez tega dostop do plačljivih funkcij storitve ni
                mogoč, čeprav uporabniški račun ostane ustvarjen. Podatki o delovnem prostoru za izdajanje prodajnih
                dokumentov (npr. naziv podjetja, naslov, davčna številka) so potrebni samo, če to funkcijo želite
                uporabljati — brez njih lahko preostanek storitve še vedno uporabljate.
            </p>
        </section>

        <section id="nameni">
            <h2>5. Namen obdelave</h2>
            <p>
                Vaše podatke obdelujemo za: ustvarjanje in vodenje uporabniškega računa, aktivacijo in obračun
                naročnine, zagotavljanje delovanja storitve, komunikacijo v zvezi s podporo, varnost in
                preprečevanje zlorab ter (če to omogočite) pošiljanje push obvestil o opomnikih.
            </p>
        </section>

        <section id="podlage">
            <h2>6. Pravne podlage</h2>
            <p>
                Obdelavo podatkov v zvezi z vašim računom in naročnino izvajamo na podlagi izvajanja pogodbe (uporaba
                storitve, ki ste jo zahtevali z registracijo in aktivacijo naročnine), varnostne in revizijske
                podatke obdelujemo na podlagi zakonitega interesa (preprečevanje zlorab, zagotavljanje varnosti), za
                push obvestila pa na podlagi vaše privolitve (dovoljenje brskalnika), ki jo lahko kadarkoli
                prekličete. Zapise o pravni privolitvi (sprejem Pogojev/DPA) hranimo na podlagi zakonitega interesa
                dokazovanja sklenjenega dogovora.
            </p>
        </section>

        <section id="avtomatizirano">
            <h2>7. Avtomatizirano odločanje in oblikovanje profilov</h2>
            <p>
                Beležka v zvezi z vašim uporabniškim računom ne izvaja avtomatiziranega odločanja, ki bi imelo
                pravne učinke v zvezi z vami ali na podoben način bistveno vplivalo na vas, niti oblikovanja profilov
                v ta namen (člen 22 GDPR se torej ne uporablja).
            </p>
        </section>

        <section id="podpora">
            <h2>8. Podpora</h2>
            <p>
                Če nam pišete v zvezi s podporo, obdelamo vsebino vaše komunikacije, da vam lahko pomagamo. Podpora
                nima stalnega dostopa do vsebine vaših pogovorov s strankami — tak dostop mora lastnik delovnega
                prostora izrecno in začasno odobriti, vsak dostop pa se beleži.
            </p>
        </section>

        <section id="varnostni-podatki">
            <h2>9. Varnostni in revizijski podatki</h2>
            <p>
                Za varnost sistema beležimo varnostno-revizijsko sled (kdo, kdaj, katero dejanje) — ti zapisi ne
                vsebujejo vsebine vaših sporočil ali opomb, temveč samo identifikatorje in dejanja.
            </p>
        </section>

        <section id="demo">
            <h2>10. Demo</h2>
            <p>
                Preizkus demo različice ne zahteva registracije in ne zahteva nobenih identifikacijskih podatkov o
                vas — ustvari se ločen, umeten demo delovni prostor z vzorčnimi (izmišljenimi) podatki, ki se
                samodejno izbriše nekaj ur po ustvarjanju. Demo ne omogoča prave povezave z Instagramom ali
                Messengerjem in nikoli ne ustvari Stripe naročnika ali naročnine. Kljub temu se pri obisku demo
                različice, tako kot pri vsakem obisku strani, lahko obdelujejo običajni tehnični podatki zahteve,
                seje in varnosti (npr. IP naslov, podatki o brskalniku) — glejte 2. in 9. člen.
            </p>
        </section>

        <section id="placila">
            <h2>11. Plačila in obračun naročnine</h2>
            <p>
                Uporaba Beležke je plačljiva (glejte 9. člen Pogojev poslovanja). Plačila naročnine obdelujemo
                izključno prek ponudnika plačilnih storitev Stripe (Stripe, Inc. / Stripe Payments Europe, Ltd.) —
                prek Stripe Checkout za plačilo in Stripe Customer Portal za upravljanje naročnine.
            </p>
            <p>
                Stripe od nas prejme podatke, potrebne za obračun naročnine (naziv delovnega prostora, obračunski
                e-poštni naslov, znesek in valuto). Podatke o vaši plačilni kartici vnesete neposredno pri Stripe —
                Beležka jih nikoli ne vidi in ne shranjuje, prejmemo le rezultat transakcije in identifikatorje
                naročnine/stranke.
            </p>
            <p>
                Stripe v tem razmerju ni zgolj naš obdelovalec po naročilu: pri obdelavi plačil (npr. za namene
                preprečevanja goljufij, izpolnjevanja svojih regulatornih obveznosti ponudnika plačilnih storitev in
                lastnega vodenja plačilnih računov) Stripe glede določenih obdelav nastopa kot samostojen
                upravljavec po svojih lastnih pogojih in politiki zasebnosti, ne po naših navodilih. Priporočamo, da
                za podrobnosti o tem, kako Stripe obdeluje vaše podatke v vlogi samostojnega upravljavca, preberete
                Stripe-ovo lastno politiko zasebnosti.
            </p>
        </section>

        <section id="analitika">
            <h2>12. Analitika / sledenje</h2>
            <p>
                Na spletni strani trenutno ne uporabljamo orodij za analitiko ali sledenje obiskovalcem. Podrobnosti
                (vključno s piškotki, ki jih dejansko uporabljamo) najdete v
                <a class="text-[var(--color-accent-600)] hover:underline" :href="route('legal.cookies')">Politiki piškotkov</a>.
            </p>
        </section>

        <section id="prejemniki">
            <h2>13. Prejemniki in podobdelovalci</h2>
            <p>
                Vaših podatkov ne prodajamo. Za obdelavo vaših lastnih podatkov (npr. obračun naročnine prek Stripe,
                dostava push obvestil vam osebno) in za obdelavo podatkov vaših strank v vašem imenu (Article 28
                podobdelovalci) se lahko zanašamo na omejeno število ponudnikov — celoten, ažuren seznam obeh
                kategorij, jasno ločenih, je na voljo na strani
                <a class="text-[var(--color-accent-600)] hover:underline" :href="route('legal.subprocessors')">Podobdelovalci</a>.
            </p>
        </section>

        <section id="prenosi">
            <h2>14. Prenosi v tretje države</h2>
            <p>
                Nekateri ponudniki, na katere se zanašamo (npr. Meta Platforms, Inc. in Stripe), lahko podatke
                obdelujejo tudi izven Evropskega gospodarskega prostora. Za vsakega takega ponudnika na strani
                <a class="text-[var(--color-accent-600)] hover:underline" :href="route('legal.subprocessors')">Podobdelovalci</a>
                navajamo, kadar je znano, lokacijo obdelave in ustrezen prenosni mehanizem (npr. sklep o ustreznosti
                ali standardne pogodbene klavzule); dokler to ni potrjeno, ne navajamo domnevanega mehanizma.
            </p>
        </section>

        <section id="hramba">
            <h2>15. Hramba podatkov</h2>
            <p>
                Podatke vašega računa in delovnega prostora hranimo, dokler je vaš delovni prostor aktiven. Ob
                izbrisu delovnega prostora (ločen postopek od preklica naročnine, glejte Pogoje poslovanja) se
                podatki najprej označijo za izbris; imate {{ retention.workspace_grace_days }} dni od takrat, da
                izbris prekličete, sicer se podatki po izteku tega obdobja trajno izbrišejo iz aktivne aplikacije.
                Izvožena datoteka s podatki delovnega prostora je na voljo za prenos {{ retention.export_hours }} ur
                po ustvarjanju, nato se samodejno izbriše. Varnostno-revizijski zapisi se hranijo ločeno in dlje, saj
                služijo varnosti in odgovornosti;
                <template v-if="retention.audit_log_days">
                    hranimo jih {{ retention.audit_log_days }} dni.
                </template>
                <template v-else>
                    hranimo jih v skladu z našo interno varnostno politiko, ki upošteva tudi morebitno zakonsko
                    določeno najkrajšo hrambo takih zapisov.
                </template>
                Hramba varnostnih kopij je infrastrukturna odločitev, ki v tem dokumentu še ni določena.
            </p>
        </section>

        <section id="pravice">
            <h2>16. Pravice posameznika</h2>
            <p>
                Glede podatkov, za katere smo upravljavec (vaš uporabniški račun in obračun naročnine), imate
                pravico do dostopa, popravka, izbrisa, omejitve obdelave, prenosljivosti podatkov in ugovora
                obdelavi, kadar ta temelji na zakonitem interesu. Privolitev za push obvestila lahko kadarkoli
                prekličete v nastavitvah brskalnika.
            </p>
        </section>

        <section id="pritozba">
            <h2>17. Pravica do pritožbe pri Informacijskem pooblaščencu</h2>
            <p>
                Če menite, da obdelava vaših osebnih podatkov krši predpise, imate pravico vložiti pritožbo pri
                <a class="text-[var(--color-accent-600)] hover:underline" :href="legal.supervisory_authority_url" target="_blank" rel="noopener">
                    {{ legal.supervisory_authority_name }}
                </a>.
            </p>
        </section>

        <section id="varnost">
            <h2>18. Varnost</h2>
            <p>
                Za zaščito podatkov uporabljamo ukrepe kot so: šifriranje posebej občutljivih vsebin (npr. besedila
                sporočil in opomb strank) na strežniku, ločevanje podatkov med delovnimi prostori, omejen in
                nadzorovan dostop podpore ter beleženje varnostno pomembnih dogodkov. To niso absolutna jamstva —
                nobenega sistema ni mogoče označiti za "100 % varnega", zato tega tudi ne trdimo.
            </p>
        </section>

        <section id="spremembe">
            <h2>19. Spremembe politike</h2>
            <p>
                To politiko lahko občasno posodobimo. Datum zadnje spremembe je naveden na vrhu strani.
            </p>
        </section>

        <section id="kontakt">
            <h2>20. Kontakt</h2>
            <p v-if="legal.legal_email">
                Za vprašanja v zvezi z obdelavo osebnih podatkov nas kontaktirajte na
                <a class="text-[var(--color-accent-600)] hover:underline" :href="`mailto:${legal.legal_email}`">{{ legal.legal_email }}</a
                >.<span v-if="legal.dpo_contact"> Pooblaščena oseba za varstvo podatkov: {{ legal.dpo_contact }}.</span>
            </p>
        </section>
    </LegalLayout>
</template>
