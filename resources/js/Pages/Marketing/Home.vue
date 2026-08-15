<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import {
    Instagram,
    Facebook,
    MessageCircle,
    Music2,
    ArrowRight,
    Check,
    Plus,
    Minus,
    CalendarDays,
    Package,
    Bell,
} from 'lucide-vue-next';

// Placeholder — swap when pricing is finalized. Referenced only here.
const MONTHLY_PRICE = '19 €';

const faqs = [
    {
        q: 'Ali moram imeti Instagram Business račun?',
        a: 'Za povezavo Instagram sporočil potrebuješ Instagram Business ali Creator račun, povezan s Facebook stranjo. To je zahteva Meta platforme, ne Beležke.',
    },
    {
        q: 'Ali lahko odgovarjam na sporočila kar iz Beležke?',
        a: 'Da. Ko povežeš Instagram in Facebook Messenger, sporočila prihajajo v Beležkin Inbox in nanje odgovoriš neposredno tam — brez preklapljanja med aplikacijami.',
    },
    {
        q: 'Ali Beležka deluje za naročila in termine?',
        a: 'Da. Delovni prostor lahko uporablja naročila, termine ali oboje hkrati — odvisno od tega, kako posluje tvoj posel.',
    },
    {
        q: 'Ali lahko uporabljam samo termine?',
        a: 'Da. Če posluješ po terminih (npr. nohti, frizerstvo, tattoo), lahko naročila preprosto pustiš izklopljena.',
    },
    {
        q: 'Ali lahko uporabljam samo naročila?',
        a: 'Da. Če prodajaš izdelke ali izdelke po naročilu (npr. torte, cvetje, darila), lahko termine pustiš izklopljene.',
    },
    {
        q: 'Kaj pa TikTok in WhatsApp?',
        a: 'Integraciji sta v pripravi.',
    },
];

const openFaq = ref<number | null>(0);
function toggleFaq(index: number) {
    openFaq.value = openFaq.value === index ? null : index;
}
</script>

<template>
    <Head title="Beležka — sporočila, stranke, naročila in termini na enem mestu">
        <meta
            name="description"
            content="Beležka združi Instagram in Facebook sporočila, stranke, naročila in termine v eno preprosto aplikacijo za mala podjetja."
        />
    </Head>

    <MarketingLayout>
        <!-- HERO -->
        <section class="relative overflow-hidden bg-white pt-16 pb-20 sm:pt-20 sm:pb-28">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="mx-auto max-w-3xl text-center">
                    <div
                        class="mx-auto inline-flex items-center gap-2 rounded-full border border-[var(--color-accent-200)] bg-[var(--color-accent-50)] px-3 py-1 text-xs font-medium text-[var(--color-accent-700)]"
                    >
                        <Instagram :size="13" /> + <Facebook :size="13" />
                        <span>Instagram in Facebook Messenger v eni aplikaciji</span>
                    </div>

                    <h1 class="mt-6 text-4xl font-semibold tracking-tight text-neutral-900 sm:text-5xl sm:leading-[1.1]">
                        Instagram sporočilo spremeni v naročilo ali termin.
                    </h1>

                    <p class="mx-auto mt-5 max-w-2xl text-lg text-neutral-600">
                        Instagram in Facebook sporočila pridejo v Beležko, kjer nanje odgovoriš, ustvariš stranko in
                        pogovor spremeniš v naročilo ali termin — brez prepisovanja med petimi aplikacijami.
                    </p>

                    <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <Link
                            :href="route('register')"
                            class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-accent-500)] px-6 py-3 text-sm font-semibold text-white shadow-sm shadow-[var(--color-accent-500)]/25 transition hover:bg-[var(--color-accent-600)]"
                        >
                            Začni brezplačno
                            <ArrowRight :size="16" />
                        </Link>
                        <a
                            href="#kako-deluje"
                            class="inline-flex items-center gap-2 rounded-lg border border-neutral-200 px-6 py-3 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50"
                        >
                            Poglej, kako deluje
                        </a>
                    </div>

                    <p class="mt-5 text-xs text-neutral-400">
                        Instagram + Facebook Messenger &nbsp;·&nbsp; TikTok + WhatsApp — kmalu
                    </p>
                </div>

                <!-- Product visual: mirrors the real Inbox UI -->
                <div class="relative mx-auto mt-14 max-w-5xl">
                    <div
                        class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-2xl shadow-neutral-900/10"
                    >
                        <div class="flex items-center gap-1.5 border-b border-neutral-200 bg-neutral-50 px-4 py-2.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-neutral-300" />
                            <span class="h-2.5 w-2.5 rounded-full bg-neutral-300" />
                            <span class="h-2.5 w-2.5 rounded-full bg-neutral-300" />
                            <span class="ml-3 rounded-md bg-white px-3 py-1 text-[11px] text-neutral-400 ring-1 ring-neutral-200">
                                belezka.app/inbox
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] lg:grid-cols-[220px_1fr_260px]">
                            <!-- conversation list -->
                            <div class="hidden border-r border-neutral-100 bg-white sm:block">
                                <div class="flex items-center gap-2.5 border-b border-[var(--color-accent-100)] bg-[var(--color-accent-50)] px-3 py-3">
                                    <div class="h-8 w-8 shrink-0 rounded-full bg-[var(--color-accent-200)]" />
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-semibold text-neutral-800">Nina Kovač</p>
                                        <p class="truncate text-[11px] text-neutral-500">Bi bilo možno naročiti...</p>
                                    </div>
                                </div>
                                <div v-for="i in 4" :key="i" class="flex items-center gap-2.5 border-b border-neutral-50 px-3 py-3">
                                    <div class="h-8 w-8 shrink-0 rounded-full bg-neutral-100" />
                                    <div class="min-w-0 flex-1">
                                        <div class="h-2 w-16 rounded bg-neutral-200" />
                                        <div class="mt-1.5 h-2 w-24 rounded bg-neutral-100" />
                                    </div>
                                </div>
                            </div>

                            <!-- conversation thread -->
                            <div class="flex flex-col border-r border-neutral-100 bg-white">
                                <div class="flex items-center gap-2.5 border-b border-neutral-100 px-4 py-3">
                                    <div class="h-8 w-8 rounded-full bg-[var(--color-accent-200)]" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-neutral-900">Nina Kovač</p>
                                        <div class="flex items-center gap-1 text-[11px] text-neutral-400">
                                            <ChannelIcon type="instagram" />
                                            <span>Instagram</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex-1 space-y-3 px-4 py-5">
                                    <div class="flex justify-start">
                                        <div class="max-w-[85%] rounded-2xl rounded-bl-sm bg-neutral-100 px-3.5 py-2.5 text-sm text-neutral-800">
                                            Živjo! Bi bilo možno naročiti torto za 30. avgust? Nekaj za približno
                                            20 ljudi 😊
                                        </div>
                                    </div>
                                    <div class="flex justify-end">
                                        <div class="max-w-[85%] rounded-2xl rounded-br-sm bg-[var(--color-accent-500)] px-3.5 py-2.5 text-sm text-white">
                                            Seveda! Za 20 oseb predlagam dvonadstropno torto, 85 € z aro 20 €. 🎂
                                        </div>
                                    </div>
                                </div>

                                <div class="border-t border-neutral-100 px-4 py-3">
                                    <div class="flex items-center gap-2 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2">
                                        <span class="text-xs text-neutral-400">Napiši sporočilo …</span>
                                    </div>
                                </div>
                            </div>

                            <!-- customer context -->
                            <div class="hidden bg-neutral-50 px-4 py-4 lg:block">
                                <p class="text-xs font-semibold text-neutral-900">Nina Kovač</p>
                                <p class="mt-0.5 text-[11px] text-neutral-500">3 prejšnja naročila · 248 € skupaj</p>

                                <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-3">
                                    <p class="text-[10px] font-medium tracking-wide text-neutral-400 uppercase">
                                        Aktivno naročilo
                                    </p>
                                    <p class="mt-1.5 text-sm font-medium text-neutral-900">Rojstnodnevna torta</p>
                                    <p class="mt-0.5 text-xs text-neutral-500">30. avgust</p>
                                    <div class="mt-2.5 flex items-center justify-between text-xs">
                                        <span class="font-semibold text-neutral-900">85 €</span>
                                        <span class="rounded-md bg-[var(--color-accent-50)] px-1.5 py-0.5 font-medium text-[var(--color-accent-700)]">
                                            Ara 20 €
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION 2: THE PAIN -->
        <section class="border-t border-neutral-100 bg-neutral-50 py-20 sm:py-28">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                        DM je samo začetek.
                    </h2>
                    <p class="mt-4 text-neutral-600">
                        Pogovor stekne na Instagramu, potem pa se posel razprši po beležkah, koledarju in spominu.
                    </p>
                </div>

                <div class="mx-auto mt-14 grid max-w-4xl grid-cols-1 items-center gap-4 lg:grid-cols-[1fr_auto_1fr]">
                    <div class="space-y-3 rounded-xl border border-neutral-200 bg-white p-5">
                        <p class="text-xs font-medium text-neutral-400">Danes</p>
                        <div class="flex items-start gap-2">
                            <Instagram :size="15" class="mt-0.5 shrink-0 text-[#E1306C]" />
                            <p class="text-sm text-neutral-700">"Koliko bi stala taka torta?"</p>
                        </div>
                        <div class="flex items-start gap-2 opacity-70">
                            <span class="mt-0.5 h-[15px] w-[15px] shrink-0 text-center text-xs leading-[15px]">📝</span>
                            <p class="text-sm text-neutral-700">Zapiskano: "85 €, ara 20 €"</p>
                        </div>
                        <div class="flex items-start gap-2 opacity-50">
                            <CalendarDays :size="15" class="mt-0.5 shrink-0 text-neutral-500" />
                            <p class="text-sm text-neutral-700">V koledarju: "30. avgust"</p>
                        </div>
                        <div class="flex items-start gap-2 opacity-30">
                            <span class="mt-0.5 h-[15px] w-[15px] shrink-0 text-center text-xs leading-[15px]">🤔</span>
                            <p class="text-sm text-neutral-700">V spominu: "Je že plačala?"</p>
                        </div>
                    </div>

                    <div class="flex justify-center py-2 lg:py-0">
                        <ArrowRight class="hidden text-neutral-300 lg:block" :size="24" />
                        <div class="h-8 w-px bg-neutral-200 lg:hidden" />
                    </div>

                    <div class="rounded-xl border border-[var(--color-accent-200)] bg-white p-5 shadow-sm shadow-[var(--color-accent-500)]/10">
                        <p class="text-xs font-medium text-[var(--color-accent-600)]">Z Beležko</p>
                        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm font-medium text-neutral-800">
                            <span class="rounded-md bg-neutral-100 px-2.5 py-1">Sporočilo</span>
                            <ArrowRight :size="14" class="text-neutral-300" />
                            <span class="rounded-md bg-neutral-100 px-2.5 py-1">Stranka</span>
                            <ArrowRight :size="14" class="text-neutral-300" />
                            <span class="rounded-md bg-neutral-100 px-2.5 py-1">Naročilo</span>
                            <ArrowRight :size="14" class="text-neutral-300" />
                            <span class="rounded-md bg-neutral-100 px-2.5 py-1">Rok</span>
                            <ArrowRight :size="14" class="text-neutral-300" />
                            <span class="rounded-md bg-[var(--color-accent-100)] px-2.5 py-1 text-[var(--color-accent-700)]">
                                Plačilo
                            </span>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center gap-2 text-sm font-medium text-neutral-800">
                            <span class="rounded-md bg-neutral-100 px-2.5 py-1">Sporočilo</span>
                            <ArrowRight :size="14" class="text-neutral-300" />
                            <span class="rounded-md bg-neutral-100 px-2.5 py-1">Stranka</span>
                            <ArrowRight :size="14" class="text-neutral-300" />
                            <span class="rounded-md bg-neutral-100 px-2.5 py-1">Termin</span>
                            <ArrowRight :size="14" class="text-neutral-300" />
                            <span class="rounded-md bg-neutral-100 px-2.5 py-1">Storitev</span>
                            <ArrowRight :size="14" class="text-neutral-300" />
                            <span class="rounded-md bg-[var(--color-accent-100)] px-2.5 py-1 text-[var(--color-accent-700)]">
                                Plačilo
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION 3: ONE INBOX -->
        <section id="kako-deluje" class="border-t border-neutral-100 bg-white py-20 sm:py-28">
            <div class="mx-auto grid max-w-6xl grid-cols-1 items-center gap-12 px-4 sm:px-6 lg:grid-cols-2">
                <div>
                    <h2 class="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                        Ne preverjaj Instagrama in Facebooka vsakih deset minut.
                    </h2>
                    <p class="mt-4 text-neutral-600">
                        Instagram in Facebook Messenger sporočila pridejo v en Inbox. Prebereš, odgovoriš, vidiš,
                        kdo je stranka in kaj je naročila nazadnje — ter takoj ustvariš naslednji korak.
                    </p>

                    <div class="mt-6 flex flex-wrap gap-2.5">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-[#FCE7F0] px-3 py-1.5 text-xs font-medium text-[#E1306C]">
                            <Instagram :size="13" /> Instagram
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-[#E3F1FF] px-3 py-1.5 text-xs font-medium text-[#0084FF]">
                            <Facebook :size="13" /> Messenger
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-neutral-100 px-3 py-1.5 text-xs font-medium text-neutral-400">
                            <Music2 :size="13" /> TikTok — kmalu
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-neutral-100 px-3 py-1.5 text-xs font-medium text-neutral-400">
                            <MessageCircle :size="13" /> WhatsApp — kmalu
                        </span>
                    </div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04]">
                    <div
                        v-for="(row, i) in [
                            { name: 'Nina Kovač', preview: 'Bi bilo možno naročiti torto za 30. avgust?', channel: 'instagram', unread: 2 },
                            { name: 'Maja Horvat', preview: 'Ali imate v petek termin za nohte?', channel: 'facebook_messenger', unread: 1 },
                            { name: 'Petra Zupan', preview: 'Hvala za torto, bila je odlična!', channel: 'instagram', unread: 0 },
                        ]"
                        :key="row.name"
                        class="flex items-center gap-3 px-4 py-3.5"
                        :class="i !== 2 && 'border-b border-neutral-100'"
                    >
                        <div class="h-9 w-9 shrink-0 rounded-full bg-neutral-100" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <ChannelIcon :type="row.channel as any" />
                                <p class="truncate text-sm font-medium text-neutral-800">{{ row.name }}</p>
                            </div>
                            <p class="mt-0.5 truncate text-xs text-neutral-500">{{ row.preview }}</p>
                        </div>
                        <span
                            v-if="row.unread"
                            class="flex h-4.5 min-w-4.5 shrink-0 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white"
                        >
                            {{ row.unread }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION 4: FROM MESSAGE TO BUSINESS RECORD -->
        <section id="funkcije" class="border-t border-neutral-100 bg-neutral-50 py-20 sm:py-28">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                        Sporočilo postane poslovni zapis.
                    </h2>
                    <p class="mt-4 text-neutral-600">Vse ostane povezano — od prvega sporočila do plačila.</p>
                </div>

                <div class="mx-auto mt-14 grid max-w-5xl grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border border-neutral-200 bg-white p-5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--color-accent-50)] text-[var(--color-accent-600)]">
                            <span class="text-sm font-semibold">1</span>
                        </div>
                        <p class="mt-3 text-sm font-semibold text-neutral-900">Prispe sporočilo</p>
                        <p class="mt-1 text-xs text-neutral-500">Instagram ali Messenger, naravnost v Beležko.</p>
                    </div>
                    <div class="rounded-xl border border-neutral-200 bg-white p-5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--color-accent-50)] text-[var(--color-accent-600)]">
                            <span class="text-sm font-semibold">2</span>
                        </div>
                        <p class="mt-3 text-sm font-semibold text-neutral-900">Ustvariš stranko</p>
                        <p class="mt-1 text-xs text-neutral-500">En klik — pogovor postane stranka.</p>
                    </div>
                    <div class="rounded-xl border border-neutral-200 bg-white p-5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--color-accent-50)] text-[var(--color-accent-600)]">
                            <span class="text-sm font-semibold">3</span>
                        </div>
                        <p class="mt-3 text-sm font-semibold text-neutral-900">Naročilo ali termin</p>
                        <p class="mt-1 text-xs text-neutral-500">Karkoli od obeh ustreza tvojemu poslu.</p>
                    </div>
                    <div class="rounded-xl border border-neutral-200 bg-white p-5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--color-accent-50)] text-[var(--color-accent-600)]">
                            <span class="text-sm font-semibold">4</span>
                        </div>
                        <p class="mt-3 text-sm font-semibold text-neutral-900">Beležka si zapomni</p>
                        <p class="mt-1 text-xs text-neutral-500">Rok, aro, status — vse na enem mestu.</p>
                    </div>
                </div>

                <div class="mx-auto mt-6 grid max-w-5xl grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-neutral-200 bg-white p-5">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-neutral-900">Rojstnodnevna torta</p>
                            <Package :size="16" class="text-neutral-400" />
                        </div>
                        <p class="mt-1 text-xs text-neutral-500">30. avgust</p>
                        <div class="mt-3 flex items-center gap-2 text-sm">
                            <span class="font-semibold text-neutral-900">85 €</span>
                            <span class="rounded-md bg-[var(--color-accent-50)] px-1.5 py-0.5 text-xs font-medium text-[var(--color-accent-700)]">
                                Ara 20 €
                            </span>
                        </div>
                    </div>
                    <div class="rounded-xl border border-neutral-200 bg-white p-5">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-neutral-900">BIAB refill</p>
                            <CalendarDays :size="16" class="text-neutral-400" />
                        </div>
                        <p class="mt-1 text-xs text-neutral-500">Petek, 14:00 · 75 min</p>
                        <div class="mt-3 flex items-center gap-2 text-sm">
                            <span class="font-semibold text-neutral-900">42 €</span>
                            <span class="rounded-md bg-[var(--color-accent-50)] px-1.5 py-0.5 text-xs font-medium text-[var(--color-accent-700)]">
                                Ara 10 €
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION 5: TWO BUSINESS TYPES -->
        <section class="border-t border-neutral-100 bg-white py-20 sm:py-28">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                        Za naročila. Za termine. Ali oboje.
                    </h2>
                </div>

                <div class="mx-auto mt-14 grid max-w-5xl grid-cols-1 gap-6 lg:grid-cols-2">
                    <div class="rounded-2xl border border-neutral-200 bg-neutral-50 p-6 sm:p-8">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white text-[var(--color-accent-600)] shadow-sm">
                            <Package :size="18" />
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-neutral-900">Naročila</h3>
                        <p class="mt-1 text-sm text-neutral-500">
                            Torte, cvetje, izdelki po naročilu, darila, ročno izdelani izdelki.
                        </p>
                        <div class="mt-5 flex flex-wrap items-center gap-2 text-xs font-medium text-neutral-700">
                            <span class="rounded-md bg-white px-2.5 py-1 shadow-sm">DM</span>
                            <ArrowRight :size="12" class="text-neutral-300" />
                            <span class="rounded-md bg-white px-2.5 py-1 shadow-sm">Naročilo</span>
                            <ArrowRight :size="12" class="text-neutral-300" />
                            <span class="rounded-md bg-white px-2.5 py-1 shadow-sm">Rok</span>
                            <ArrowRight :size="12" class="text-neutral-300" />
                            <span class="rounded-md bg-white px-2.5 py-1 shadow-sm">Ara</span>
                            <ArrowRight :size="12" class="text-neutral-300" />
                            <span class="rounded-md bg-[var(--color-accent-100)] px-2.5 py-1 text-[var(--color-accent-700)]">
                                Dokončano
                            </span>
                        </div>
                        <p class="mt-4 text-xs text-neutral-500">
                            Cene in are lahko vnaprej vpišeš v katalog izdelkov.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-neutral-200 bg-neutral-50 p-6 sm:p-8">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white text-[var(--color-accent-600)] shadow-sm">
                            <CalendarDays :size="18" />
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-neutral-900">Termini</h3>
                        <p class="mt-1 text-sm text-neutral-500">
                            Nohti, frizerstvo, lash/brow, tattoo, fotografija, beauty storitve.
                        </p>
                        <div class="mt-5 flex flex-wrap items-center gap-2 text-xs font-medium text-neutral-700">
                            <span class="rounded-md bg-white px-2.5 py-1 shadow-sm">DM</span>
                            <ArrowRight :size="12" class="text-neutral-300" />
                            <span class="rounded-md bg-white px-2.5 py-1 shadow-sm">Termin</span>
                            <ArrowRight :size="12" class="text-neutral-300" />
                            <span class="rounded-md bg-white px-2.5 py-1 shadow-sm">Storitev</span>
                            <ArrowRight :size="12" class="text-neutral-300" />
                            <span class="rounded-md bg-white px-2.5 py-1 shadow-sm">Datum/čas</span>
                            <ArrowRight :size="12" class="text-neutral-300" />
                            <span class="rounded-md bg-[var(--color-accent-100)] px-2.5 py-1 text-[var(--color-accent-700)]">
                                Ara
                            </span>
                        </div>
                        <p class="mt-4 text-xs text-neutral-500">
                            Trajanje storitve in koledar poskrbita, da se termini ne prekrivajo.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION 6: CUSTOMER MEMORY -->
        <section class="border-t border-neutral-100 bg-neutral-50 py-20 sm:py-28">
            <div class="mx-auto grid max-w-6xl grid-cols-1 items-center gap-12 px-4 sm:px-6 lg:grid-cols-2">
                <div class="order-2 lg:order-1">
                    <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm shadow-neutral-900/[0.04]">
                        <div class="flex items-center gap-3">
                            <div class="h-11 w-11 rounded-full bg-[var(--color-accent-200)]" />
                            <div>
                                <p class="text-sm font-semibold text-neutral-900">Ana Novak</p>
                                <p class="text-xs text-neutral-500">Instagram @ana.novak</p>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-lg bg-neutral-50 py-2.5">
                                <p class="text-sm font-semibold text-neutral-900">4</p>
                                <p class="text-[10px] text-neutral-500">naročila</p>
                            </div>
                            <div class="rounded-lg bg-neutral-50 py-2.5">
                                <p class="text-sm font-semibold text-neutral-900">2</p>
                                <p class="text-[10px] text-neutral-500">termina</p>
                            </div>
                            <div class="rounded-lg bg-neutral-50 py-2.5">
                                <p class="text-sm font-semibold text-neutral-900">526 €</p>
                                <p class="text-[10px] text-neutral-500">skupaj</p>
                            </div>
                        </div>
                        <div class="mt-4 space-y-2 border-t border-neutral-100 pt-4">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-neutral-600">Rojstnodnevna torta</span>
                                <span class="text-neutral-400">pred 3 meseci</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-neutral-600">Termin — manikura</span>
                                <span class="text-neutral-400">pred 5 meseci</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="order-1 lg:order-2">
                    <h2 class="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                        Ko se ista stranka oglasi čez tri mesece, začneš tam, kjer sta ostala.
                    </h2>
                    <p class="mt-4 text-neutral-600">
                        Beležka poveže pogovor s stranko in njeno zgodovino. Ni ti treba brskati po starih
                        sporočilih, da bi se spomnila, kaj je nazadnje naročila.
                    </p>
                </div>
            </div>
        </section>

        <!-- SECTION 7: TODAY -->
        <section class="border-t border-neutral-100 bg-white py-20 sm:py-28">
            <div class="mx-auto grid max-w-6xl grid-cols-1 items-center gap-12 px-4 sm:px-6 lg:grid-cols-2">
                <div>
                    <h2 class="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                        Odpreš Beležko in takoj veš, kaj moraš urediti.
                    </h2>
                    <p class="mt-4 text-neutral-600">
                        Brez zapletenih nadzornih plošč. Samo današnje delo — na enem mestu.
                    </p>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04]">
                    <div class="rounded-t-xl bg-gradient-to-br from-[var(--color-accent-500)] to-[var(--color-accent-700)] px-5 py-4 text-white">
                        <p class="text-sm font-semibold">Danes je 15. avgust 2026.</p>
                    </div>
                    <div class="space-y-2 p-4">
                        <div class="flex items-center justify-between rounded-lg border border-neutral-100 px-3 py-2.5">
                            <span class="text-sm text-neutral-700">Neodgovorjena sporočila</span>
                            <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-[var(--color-accent-500)] px-1.5 text-xs font-semibold text-white">
                                3
                            </span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg border border-neutral-100 px-3 py-2.5">
                            <span class="text-sm text-neutral-700">Termini danes</span>
                            <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-[var(--color-accent-500)] px-1.5 text-xs font-semibold text-white">
                                2
                            </span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg border border-neutral-100 px-3 py-2.5">
                            <span class="text-sm text-neutral-700">Naročilo z rokom danes</span>
                            <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-[var(--color-accent-500)] px-1.5 text-xs font-semibold text-white">
                                1
                            </span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg border border-neutral-100 px-3 py-2.5">
                            <span class="text-sm text-neutral-700">Opomniki</span>
                            <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-[var(--color-accent-500)] px-1.5 text-xs font-semibold text-white">
                                2
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION 8: CATALOG -->
        <section class="border-t border-neutral-100 bg-neutral-50 py-20 sm:py-28">
            <div class="mx-auto grid max-w-6xl grid-cols-1 items-center gap-12 px-4 sm:px-6 lg:grid-cols-2">
                <div class="order-2 lg:order-1">
                    <div class="space-y-2.5">
                        <div class="flex items-center justify-between rounded-xl border border-neutral-200 bg-white p-4">
                            <div>
                                <p class="text-sm font-medium text-neutral-900">Rojstnodnevna torta</p>
                                <p class="text-xs text-neutral-500">Naročilo</p>
                            </div>
                            <div class="text-right text-sm">
                                <p class="font-semibold text-neutral-900">85 €</p>
                                <p class="text-xs text-neutral-500">Ara 20 €</p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between rounded-xl border border-neutral-200 bg-white p-4">
                            <div>
                                <p class="text-sm font-medium text-neutral-900">BIAB refill</p>
                                <p class="text-xs text-neutral-500">Storitev · 75 min</p>
                            </div>
                            <div class="text-right text-sm">
                                <p class="font-semibold text-neutral-900">42 €</p>
                                <p class="text-xs text-neutral-500">Ara 10 €</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="order-1 lg:order-2">
                    <h2 class="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                        Cene vpišeš enkrat.
                    </h2>
                    <p class="mt-4 text-neutral-600">
                        Izdelke in storitve z običajno ceno, aro in trajanjem shraniš v katalog. Ko pri novem
                        naročilu ali terminu izbereš izdelek ali storitev, se vrednosti izpolnijo same — za
                        posamezno stranko jih lahko še vedno spremeniš.
                    </p>
                </div>
            </div>
        </section>

        <!-- SECTION 9: ANALYTICS -->
        <section class="border-t border-neutral-100 bg-white py-20 sm:py-28">
            <div class="mx-auto grid max-w-6xl grid-cols-1 items-center gap-12 px-4 sm:px-6 lg:grid-cols-2">
                <div>
                    <h2 class="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                        In končno vidiš, kaj ti dejansko prinaša posel.
                    </h2>
                    <p class="mt-4 text-neutral-600">
                        Prihodki, od kod prihajajo povpraševanja, kateri kanal največ prinese in kateri izdelki
                        ali storitve se najbolje prodajajo — brez kompliciranega poročanja.
                    </p>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm shadow-neutral-900/[0.04]">
                    <div class="flex items-baseline justify-between">
                        <div>
                            <p class="text-xs font-medium text-neutral-500">Prihodek</p>
                            <p class="mt-1 text-2xl font-semibold text-neutral-900">4.820 €</p>
                        </div>
                        <span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">+12 %</span>
                    </div>
                    <p class="mt-1 text-[11px] text-neutral-400">glede na prejšnje obdobje</p>

                    <div class="mt-5 space-y-2.5 border-t border-neutral-100 pt-4">
                        <div class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-1.5 text-neutral-600">
                                <Instagram :size="13" class="text-[#E1306C]" /> Instagram
                            </span>
                            <span class="font-medium text-neutral-900">3.150 €</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-1.5 text-neutral-600">
                                <Facebook :size="13" class="text-[#0084FF]" /> Facebook
                            </span>
                            <span class="font-medium text-neutral-900">1.670 €</span>
                        </div>
                    </div>

                    <div class="mt-4 border-t border-neutral-100 pt-4">
                        <p class="text-xs font-medium text-neutral-500">Najbolje prodajano</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span class="rounded-md bg-neutral-100 px-2.5 py-1 text-xs text-neutral-700">Rojstnodnevna torta</span>
                            <span class="rounded-md bg-neutral-100 px-2.5 py-1 text-xs text-neutral-700">BIAB refill</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION 10: FOLLOW-UPS -->
        <section class="border-t border-neutral-100 bg-neutral-50 py-20 sm:py-28">
            <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
                <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-[var(--color-accent-100)] text-[var(--color-accent-600)]">
                    <Bell :size="18" />
                </div>
                <h2 class="mt-5 text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                    Ne izgubi naročila samo zato, ker si pozabila odgovoriti.
                </h2>
                <p class="mt-4 text-neutral-600">
                    Nastaviš opomnik in Beležka poskrbi, da povpraševanje ne pade v pozabo.
                </p>

                <div class="mx-auto mt-8 max-w-sm space-y-2.5 text-left">
                    <div class="flex items-center gap-3 rounded-lg border border-neutral-200 bg-white px-4 py-3">
                        <span class="rounded-md bg-neutral-100 px-2 py-1 text-xs font-medium text-neutral-500">
                            Danes, 16:00
                        </span>
                        <span class="text-sm text-neutral-800">Piši Maji glede ponudbe za torto</span>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg border border-neutral-200 bg-white px-4 py-3">
                        <span class="rounded-md bg-neutral-100 px-2 py-1 text-xs font-medium text-neutral-500">
                            Jutri
                        </span>
                        <span class="text-sm text-neutral-800">Preveri, ali Nina potrdi termin</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION 11: WHO IT IS FOR -->
        <section id="za-koga" class="border-t border-neutral-100 bg-white py-20 sm:py-28">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                        Če se tvoj posel začne v DM-jih, je Beležka verjetno zate.
                    </h2>
                </div>

                <div class="mx-auto mt-12 flex max-w-3xl flex-wrap justify-center gap-2.5">
                    <span
                        v-for="category in [
                            'Torte & sladice',
                            'Cvetličarne',
                            'Nohti & beauty',
                            'Frizerji',
                            'Tattoo studii',
                            'Fotografi',
                            'Ročno izdelani izdelki',
                            'Custom darila',
                            'Poročni ponudniki',
                        ]"
                        :key="category"
                        class="rounded-full border border-neutral-200 px-4 py-2 text-sm text-neutral-700"
                    >
                        {{ category }}
                    </span>
                </div>
            </div>
        </section>

        <!-- SECTION 12: INTEGRATIONS -->
        <section class="border-t border-neutral-100 bg-neutral-50 py-20 sm:py-28">
            <div class="mx-auto max-w-4xl px-4 sm:px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                        Tvoja sporočila, na enem mestu.
                    </h2>
                </div>

                <div class="mx-auto mt-12 grid max-w-2xl grid-cols-2 gap-4">
                    <div class="flex items-center gap-3 rounded-xl border border-neutral-200 bg-white p-4">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#FCE7F0]">
                            <Instagram :size="18" class="text-[#E1306C]" />
                        </span>
                        <div>
                            <p class="text-sm font-medium text-neutral-900">Instagram</p>
                            <p class="flex items-center gap-1 text-xs font-medium text-emerald-600">
                                <Check :size="12" /> Povezano
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-xl border border-neutral-200 bg-white p-4">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#E3F1FF]">
                            <Facebook :size="18" class="text-[#0084FF]" />
                        </span>
                        <div>
                            <p class="text-sm font-medium text-neutral-900">Facebook Messenger</p>
                            <p class="flex items-center gap-1 text-xs font-medium text-emerald-600">
                                <Check :size="12" /> Povezano
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-xl border border-dashed border-neutral-200 bg-white/60 p-4 opacity-60">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-neutral-100">
                            <Music2 :size="18" class="text-neutral-400" />
                        </span>
                        <div>
                            <p class="text-sm font-medium text-neutral-500">TikTok</p>
                            <p class="text-xs font-medium text-neutral-400">Kmalu</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-xl border border-dashed border-neutral-200 bg-white/60 p-4 opacity-60">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-neutral-100">
                            <MessageCircle :size="18" class="text-neutral-400" />
                        </span>
                        <div>
                            <p class="text-sm font-medium text-neutral-500">WhatsApp</p>
                            <p class="text-xs font-medium text-neutral-400">Kmalu</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION 13: PRICING -->
        <section id="cena" class="border-t border-neutral-100 bg-white py-20 sm:py-28">
            <div class="mx-auto max-w-2xl px-4 text-center sm:px-6">
                <h2 class="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">Preprosta cena.</h2>
                <p class="mt-4 text-neutral-600">En paket. Vse, kar potrebuješ za urejanje sporočil in poslovanja.</p>

                <div class="mx-auto mt-10 max-w-sm rounded-2xl border border-neutral-200 bg-white p-8 shadow-sm shadow-neutral-900/[0.04]">
                    <p class="text-sm font-semibold text-[var(--color-accent-600)]">Beležka</p>
                    <div class="mt-2 flex items-baseline justify-center gap-1">
                        <span class="text-4xl font-semibold text-neutral-900">{{ MONTHLY_PRICE }}</span>
                        <span class="text-sm text-neutral-500">/ mesec</span>
                    </div>

                    <ul class="mt-6 space-y-2.5 text-left text-sm text-neutral-600">
                        <li class="flex items-start gap-2">
                            <Check :size="15" class="mt-0.5 shrink-0 text-[var(--color-accent-500)]" />
                            Instagram in Facebook Messenger v enem Inboxu
                        </li>
                        <li class="flex items-start gap-2">
                            <Check :size="15" class="mt-0.5 shrink-0 text-[var(--color-accent-500)]" />
                            Stranke, naročila in termini
                        </li>
                        <li class="flex items-start gap-2">
                            <Check :size="15" class="mt-0.5 shrink-0 text-[var(--color-accent-500)]" />
                            Katalog izdelkov in storitev
                        </li>
                        <li class="flex items-start gap-2">
                            <Check :size="15" class="mt-0.5 shrink-0 text-[var(--color-accent-500)]" />
                            Opomniki in obveščanje
                        </li>
                        <li class="flex items-start gap-2">
                            <Check :size="15" class="mt-0.5 shrink-0 text-[var(--color-accent-500)]" />
                            Analitika poslovanja
                        </li>
                    </ul>

                    <Link
                        :href="route('register')"
                        class="mt-8 flex w-full items-center justify-center gap-2 rounded-lg bg-[var(--color-accent-500)] px-6 py-3 text-sm font-semibold text-white shadow-sm shadow-[var(--color-accent-500)]/25 transition hover:bg-[var(--color-accent-600)]"
                    >
                        Začni brezplačno
                        <ArrowRight :size="16" />
                    </Link>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section class="border-t border-neutral-100 bg-neutral-50 py-20 sm:py-28">
            <div class="mx-auto max-w-2xl px-4 sm:px-6">
                <h2 class="text-center text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                    Pogosta vprašanja
                </h2>

                <div class="mt-10 divide-y divide-neutral-200 rounded-xl border border-neutral-200 bg-white">
                    <div v-for="(item, index) in faqs" :key="item.q">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left"
                            @click="toggleFaq(index)"
                        >
                            <span class="text-sm font-medium text-neutral-900">{{ item.q }}</span>
                            <Plus v-if="openFaq !== index" :size="16" class="shrink-0 text-neutral-400" />
                            <Minus v-else :size="16" class="shrink-0 text-neutral-400" />
                        </button>
                        <div v-if="openFaq === index" class="px-5 pb-4 text-sm text-neutral-600">
                            {{ item.a }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FINAL CTA -->
        <section class="border-t border-neutral-100 bg-gradient-to-br from-[var(--color-accent-500)] to-[var(--color-accent-700)] py-20 sm:py-24">
            <div class="mx-auto max-w-2xl px-4 text-center sm:px-6">
                <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                    Tvoj posel že živi v DM-jih.<br class="hidden sm:block" />
                    Naj bo vsaj organiziran.
                </h2>

                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <Link
                        :href="route('register')"
                        class="inline-flex items-center gap-2 rounded-lg bg-white px-6 py-3 text-sm font-semibold text-[var(--color-accent-700)] shadow-sm transition hover:bg-neutral-50"
                    >
                        Začni z Beležko
                        <ArrowRight :size="16" />
                    </Link>
                    <Link
                        :href="route('login')"
                        class="inline-flex items-center gap-2 rounded-lg border border-white/30 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                    >
                        Prijava
                    </Link>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
