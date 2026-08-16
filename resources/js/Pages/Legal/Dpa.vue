<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LegalLayout from '@/Layouts/LegalLayout.vue';

interface LegalConfig {
    dpa_version: string;
    legal_email: string | null;
    governing_law: string;
}

defineProps<{ legal: LegalConfig }>();

const toc = [
    { id: 'stranke', label: '1. Pogodbeni stranki' },
    { id: 'predmet', label: '2. Predmet obdelave' },
    { id: 'trajanje', label: '3. Trajanje obdelave' },
    { id: 'narava', label: '4. Narava in namen' },
    { id: 'vrste', label: '5. Vrste osebnih podatkov' },
    { id: 'kategorije', label: '6. Kategorije posameznikov' },
    { id: 'navodila', label: '7. Navodila upravljavca' },
    { id: 'zaupnost', label: '8. Zaupnost' },
    { id: 'tom', label: '9. Tehnični in organizacijski ukrepi' },
    { id: 'podobdelovalci', label: '10. Podobdelovalci' },
    { id: 'prenosi', label: '11. Prenosi izven EGP' },
    { id: 'pomoc-pravice', label: '12. Pomoč pri pravicah posameznikov' },
    { id: 'pomoc-incidenti', label: '13. Pomoč pri varnostnih incidentih' },
    { id: 'dpia', label: '14. DPIA / sodelovanje' },
    { id: 'izbris-vrnitev', label: '15. Izbris ali vrnitev podatkov' },
    { id: 'revizije', label: '16. Informacije in revizije' },
    { id: 'obvestilo-krsitev', label: '17. Obvestilo o kršitvi varnosti' },
    { id: 'odgovornosti', label: '18. Odgovornosti upravljavca' },
    { id: 'koncne', label: '19. Končne določbe' },
    { id: 'priloga-a', label: 'Priloga A — Tehnični in organizacijski ukrepi' },
];
</script>

<template>
    <Head title="Dogovor o obdelavi osebnih podatkov — Beležka">
        <meta name="description" content="Dogovor o obdelavi osebnih podatkov (Article 28 DPA) med Beležko in poslovnimi uporabniki." />
    </Head>

    <LegalLayout title="Dogovor o obdelavi osebnih podatkov" :updated-at="legal.dpa_version" :toc="toc">
        <section>
            <p>
                Ta dogovor ureja obdelavo osebnih podatkov, ki jih poslovni uporabnik ("Upravljavec") vnaša v
                Beležko o svojih strankah, in dopolnjuje Pogoje poslovanja. Z registracijo in potrditvijo ob
                registraciji ta dogovor postane del vašega pogodbenega razmerja z Beležko, brez potrebe po ločenem
                podpisu.
            </p>
        </section>

        <section id="stranke">
            <h2>1. Pogodbeni stranki</h2>
            <p>
                Pogodbeni stranki tega dogovora sta: poslovni uporabnik Beležke (v vlogi upravljavca podatkov svojih
                strank) in ponudnik Beležke, katerega podatki so na
                <a class="text-[var(--color-accent-600)] hover:underline" :href="route('legal.provider')">strani Podatki o ponudniku</a>
                (v vlogi obdelovalca).
            </p>
        </section>

        <section id="predmet">
            <h2>2. Predmet obdelave</h2>
            <p>
                Predmet obdelave so osebni podatki strank Upravljavca, ki jih Upravljavec vnese, prejme ali ustvari
                znotraj svojega delovnega prostora v Beležki.
            </p>
        </section>

        <section id="trajanje">
            <h2>3. Trajanje obdelave</h2>
            <p>
                Obdelava traja ves čas trajanja uporabniškega razmerja med Upravljavcem in Beležko, torej dokler
                Upravljavčev delovni prostor obstaja oziroma dokler ni izbrisan v skladu s 15. členom tega dogovora.
            </p>
        </section>

        <section id="narava">
            <h2>4. Narava in namen obdelave</h2>
            <p>
                Beležka za Upravljavca zagotavlja gostovanje, shranjevanje, prikaz in organizacijo podatkov strank
                (sporočila, naročila, termini, opombe) znotraj aplikacije Beležka, izključno za namen zagotavljanja
                storitve Upravljavcu.
            </p>
        </section>

        <section id="vrste">
            <h2>5. Vrste osebnih podatkov</h2>
            <p>
                Ime, kontaktni podatki (e-pošta, telefon), identifikatorji družbenih omrežij (Instagram/Facebook
                uporabniško ime), vsebina sporočil in prilog, podatki o naročilih in terminih ter opombe, ki jih o
                stranki vnese Upravljavec.
            </p>
        </section>

        <section id="kategorije">
            <h2>6. Kategorije posameznikov</h2>
            <p>Stranke in potencialne stranke Upravljavca, ki z njim komunicirajo prek povezanih kanalov.</p>
        </section>

        <section id="navodila">
            <h2>7. Navodila upravljavca</h2>
            <p>
                Beležka obdeluje osebne podatke izključno na podlagi dokumentiranih navodil Upravljavca — kar
                pomeni v okviru funkcionalnosti, ki jih Upravljavec sam upravlja znotraj aplikacije (npr. vnos,
                urejanje, izvoz, izbris). Beležka podatkov ne uporablja za lastne, od Upravljavca neodvisne namene.
            </p>
        </section>

        <section id="zaupnost">
            <h2>8. Zaupnost</h2>
            <p>
                Osebje Beležke, ki ima dostop do podatkov Upravljavca, je zavezano k zaupnosti. Standarden dostop do
                vsebine strank Upravljavca ni omogočen — dostop podpore je izrecen, časovno omejen, ga odobri
                lastnik delovnega prostora in se v celoti beleži (glejte Prilogo A).
            </p>
        </section>

        <section id="tom">
            <h2>9. Tehnični in organizacijski ukrepi</h2>
            <p>Konkreten seznam ukrepov je v Prilogi A tega dogovora.</p>
        </section>

        <section id="podobdelovalci">
            <h2>10. Uporaba podobdelovalcev</h2>
            <p>
                Upravljavec s tem dogovorom daje splošno pooblastilo Beležki za najem podobdelovalcev, potrebnih za
                izvajanje storitve. Ažuren seznam trenutnih podobdelovalcev je javno objavljen na strani
                <a class="text-[var(--color-accent-600)] hover:underline" :href="route('legal.subprocessors')">Podobdelovalci</a>,
                ki predstavlja tudi mehanizem obveščanja o spremembah tega seznama.
            </p>
        </section>

        <section id="prenosi">
            <h2>11. Prenosi izven EGP</h2>
            <p>
                Če katerikoli podobdelovalec podatke obdeluje izven Evropskega gospodarskega prostora, se to izvaja
                na podlagi ustreznega prenosnega mehanizma, ki ga določa ta podobdelovalec (npr. standardne
                pogodbene klavzule). Podrobnosti o posameznih ponudnikih so navedene na strani Podobdelovalci, ko so
                znane.
            </p>
        </section>

        <section id="pomoc-pravice">
            <h2>12. Pomoč pri uveljavljanju pravic posameznikov</h2>
            <p>
                Beležka Upravljavcu zagotavlja orodja za izvoz podatkov posamezne stranke ter za njihov izbris ali
                anonimizacijo, da lahko Upravljavec sam odgovori na zahteve svojih strank v zvezi z uveljavljanjem
                njihovih pravic.
            </p>
        </section>

        <section id="pomoc-incidenti">
            <h2>13. Pomoč pri varnostnih incidentih</h2>
            <p>
                Če Beležka ugotovi kršitev varstva osebnih podatkov, ki zadeva podatke Upravljavčevih strank, o tem
                Upravljavca obvesti brez nepotrebnega odlašanja in mu zagotovi razpoložljive informacije, potrebne
                za izpolnitev njegovih morebitnih obveznosti obveščanja.
            </p>
        </section>

        <section id="dpia">
            <h2>14. DPIA / sodelovanje</h2>
            <p>
                Če Upravljavec za svojo obdelavo potrebuje oceno učinka v zvezi z varstvom podatkov (DPIA), mu
                Beležka na razumno zahtevo zagotovi informacije o svoji obdelavi, ki jih Upravljavec za to potrebuje.
            </p>
        </section>

        <section id="izbris-vrnitev">
            <h2>15. Izbris ali vrnitev podatkov</h2>
            <p>
                Ob izbrisu delovnega prostora (opisano v Pogojih poslovanja) se podatki, po vnaprej določenem
                obdobju za obnovitev, trajno izbrišejo iz aktivne aplikacije. Pred izbrisom lahko Upravljavec svoje
                podatke izvozi. Izbris iz morebitnih varnostnih kopij sledi ločenemu, infrastrukturnemu časovnemu
                okviru, ki v tem dokumentu še ni določen.
            </p>
        </section>

        <section id="revizije">
            <h2>16. Informacije in revizije</h2>
            <p>
                Beležka Upravljavcu na razumno zahtevo zagotovi informacije, potrebne za dokazovanje skladnosti s
                tem dogovorom. Obseg in način morebitnega revizijskega pregleda se dogovorita posebej.
            </p>
        </section>

        <section id="obvestilo-krsitev">
            <h2>17. Obvestilo o kršitvi varnosti</h2>
            <p>
                Glejte 13. člen (Pomoč pri varnostnih incidentih) — obveznost obveščanja Upravljavca velja brez
                nepotrebnega odlašanja po tem, ko Beležka za kršitev izve.
            </p>
        </section>

        <section id="odgovornosti">
            <h2>18. Odgovornosti upravljavca</h2>
            <p>
                Upravljavec je odgovoren za zakonitost obdelave osebnih podatkov svojih strank, vključno z
                obstojem ustrezne pravne podlage za njihov vnos v Beležko, in za točnost teh podatkov. Omejitev
                odgovornosti med strankama ureja 20. člen Pogojev poslovanja.
            </p>
        </section>

        <section id="koncne">
            <h2>19. Končne določbe</h2>
            <p>
                Ta dogovor velja, dokler traja uporabniško razmerje po Pogojih poslovanja, in preneha skupaj z njim.
                Zanj velja pravo {{ legal.governing_law }}.
            </p>
        </section>

        <section id="priloga-a">
            <h2>Priloga A — Tehnični in organizacijski ukrepi</h2>

            <h3>Nadzor dostopa</h3>
            <p>
                Dostop do podatkov delovnega prostora je omejen na njegove člane. Podatki med delovnimi prostori so
                strogo ločeni (izolacija najemnikov) — vsaka poizvedba je omejena na trenutni delovni prostor
                uporabnika.
            </p>

            <h3>Izolacija delovnih prostorov</h3>
            <p>Vsak delovni prostor je logično ločen na ravni podatkovne baze; dostop do tujega delovnega prostora ni mogoč prek običajnih uporabniških poti.</p>

            <h3>Šifriranje</h3>
            <p>
                Posebej občutljiva vsebina (npr. besedilo sporočil, opombe strank, opisi naročil in terminov) je na
                strežniku dodatno aplikacijsko šifrirana. To ni šifriranje od konca do konca (E2EE) — Beležka lahko
                v okviru zagotavljanja storitve do te vsebine dostopa, ko je to potrebno.
            </p>

            <h3>Upravljanje integracijskih poverilnic</h3>
            <p>Dostopni žetoni za povezane storitve (npr. Meta) so šifrirani in niso izpostavljeni v API odzivih ali administratorskih pogledih.</p>

            <h3>Hramba datotek</h3>
            <p>Priložene datoteke (npr. slike v pogovorih) so shranjene zasebno in dostopne samo prek avtenticiranih, na delovni prostor vezanih poti — nikoli prek javno dostopnega URL-ja.</p>

            <h3>Varnostne kopije</h3>
            <p>
                Politika in konfiguracija varnostnih kopij (pogostost, šifriranje, obdobje hrambe) je infrastrukturna
                odločitev, ki v tem dokumentu še ni dokončno določena.
            </p>

            <h3>Dnevniki in revizijska sled</h3>
            <p>Varnostno pomembna dejanja (npr. odobritev dostopa podpore, izbris delovnega prostora) se beležijo v revizijsko sled, ki vsebuje identifikatorje dejanj, ne pa vsebine sporočil ali opomb.</p>

            <h3>Dostop podpore</h3>
            <p>Podpora nima stalnega dostopa do vsebine delovnega prostora. Dostop mora izrecno in časovno omejeno odobriti lastnik delovnega prostora, kadarkoli ga lahko prekliče, vsak dostop pa se zabeleži.</p>

            <h3>Razvoj in testiranje</h3>
            <p>Demo/testni podatki so ločeni od resničnih podatkov uporabnikov in se ne uporabljajo za razvoj brez izrecno vzpostavljenih testnih/demo okolij.</p>

            <h3>Obvladovanje incidentov</h3>
            <p>Ob ugotovljeni kršitvi varstva podatkov se uporabi postopek iz 13. in 17. člena tega dogovora.</p>

            <h3>Infrastrukturni ukrepi, ki v tem dokumentu še niso dokončno določeni</h3>
            <p>
                Naslednji ukrepi so odvisni od izbire ponudnika gostovanja in drugih infrastrukturnih odločitev, ki
                še niso dokončno sprejete: vsiljena uporaba HTTPS/varnih piškotkov v produkcijskem okolju, šifriranje
                podatkov na nivoju diska, upravljanje skrivnosti (secrets management), omejen dostop do podatkovne
                baze, spremljanje in opozarjanje (monitoring), ter dvofaktorska avtentikacija za platformske
                administratorje. Te postavke bomo dopolnili takoj, ko bodo dokončno določene.
            </p>
        </section>

        <section>
            <p v-if="legal.legal_email" class="text-sm text-neutral-500">
                Vprašanja v zvezi s tem dogovorom: <a class="text-[var(--color-accent-600)] hover:underline" :href="`mailto:${legal.legal_email}`">{{ legal.legal_email }}</a>.
            </p>
        </section>
    </LegalLayout>
</template>
