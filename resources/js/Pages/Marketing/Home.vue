<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import Reveal from '@/Components/Marketing/Reveal.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import Badge from '@/Components/Badge.vue';
import { PAYMENT_STATUS_META } from '@/lib/statuses';
import {
    Instagram,
    Facebook,
    Music2,
    MessageCircle,
    ArrowRight,
    Check,
    Plus,
    Minus,
    Bell,
    Sparkles,
} from 'lucide-vue-next';

// Placeholder — swap when pricing is finalized. Referenced only here.
const MONTHLY_PRICE = '19 €';

const painFragments = [
    { text: 'Čakaj — koliko sem ji rekla za torto?', tag: 'Zapiski', rotate: '-rotate-2', accent: 'bg-neutral-100 text-neutral-500' },
    { text: 'A je tale že nakazala aro?', tag: 'Plačila', rotate: 'rotate-1', accent: 'bg-amber-50 text-amber-700' },
    { text: 'Nekdo mi je pisal za petek. Kdo že?', tag: 'Messenger', rotate: 'rotate-2', accent: 'bg-[#E3F1FF] text-[#0084FF]' },
    { text: 'Katera Nina je to?', tag: 'Instagram', rotate: '-rotate-1', accent: 'bg-[#FCE7F0] text-[#E1306C]' },
    { text: 'Uf, tej sem pozabila odgovoriti.', tag: 'Instagram', rotate: 'rotate-2', accent: 'bg-[#FCE7F0] text-[#E1306C]' },
    { text: 'Kje sva se dogovorili za 14:30?', tag: 'Koledar', rotate: '-rotate-2', accent: 'bg-neutral-100 text-neutral-500' },
    { text: 'Katera naročila imam sploh ta teden?', tag: 'Zapiski', rotate: 'rotate-1', accent: 'bg-neutral-100 text-neutral-500' },
    { text: 'Komu moram danes še pisati?', tag: 'V glavi', rotate: '-rotate-1', accent: 'bg-[var(--color-accent-50)] text-[var(--color-accent-700)]' },
];

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

const depositPaid = PAYMENT_STATUS_META.deposit_paid;
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
        <section class="bg-white pt-14 pb-0 sm:pt-20">
            <div class="mx-auto max-w-4xl px-4 text-center sm:px-6">
                <p class="text-sm font-medium text-[var(--color-accent-600)]">Instagram + Facebook Messenger za mala podjetja</p>

                <h1 class="mt-4 text-4xl leading-[1.08] font-semibold tracking-tight text-neutral-900 sm:text-5xl lg:text-6xl">
                    Stranka ti piše.<br />
                    Vse ostalo moraš trenutno<br class="hidden sm:block" />
                    držati v glavi.
                </h1>

                <p class="mx-auto mt-6 max-w-xl text-lg text-neutral-600">
                    Beležka poveže sporočila s strankami, naročili in termini — da se nič več ne izgubi med
                    DM-i, zapiski in koledarjem.
                </p>

                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <Link
                        :href="route('register')"
                        class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-accent-500)] px-6 py-3 text-sm font-semibold text-white shadow-sm shadow-[var(--color-accent-500)]/25 transition hover:bg-[var(--color-accent-600)]"
                    >
                        Začni z Beležko
                        <ArrowRight :size="16" />
                    </Link>
                    <a
                        href="#kako-deluje"
                        class="inline-flex items-center gap-2 rounded-lg px-6 py-3 text-sm font-semibold text-neutral-600 transition hover:text-neutral-900"
                    >
                        Poglej, kako deluje
                    </a>
                </div>

                <p class="mt-5 text-xs text-neutral-400">
                    Instagram + Facebook Messenger &nbsp;·&nbsp; TikTok + WhatsApp — kmalu
                </p>
            </div>

            <!-- Hero visual: full-bleed real product composition -->
            <Reveal :delay="150">
                <div class="relative mx-auto mt-12 max-w-6xl px-2 sm:mt-16 sm:px-6">
                    <div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-2xl shadow-neutral-900/[0.08] sm:rounded-3xl">
                        <div class="flex h-12 shrink-0 items-center border-b border-neutral-200 bg-white px-5">
                            <span class="text-sm font-semibold text-neutral-900">Prejeta pošta</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[240px_1fr] lg:grid-cols-[240px_1fr_300px]">
                            <div class="hidden border-r border-neutral-100 bg-white sm:block">
                                <div class="flex items-center gap-2.5 border-b border-[var(--color-accent-100)] bg-[var(--color-accent-50)] px-4 py-3.5">
                                    <div class="h-9 w-9 shrink-0 rounded-full bg-[var(--color-accent-200)]" />
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-neutral-800">Nina Kovač</p>
                                        <p class="truncate text-xs text-neutral-500">Bi bilo možno naročiti...</p>
                                    </div>
                                </div>
                                <div v-for="i in 5" :key="i" class="flex items-center gap-2.5 border-b border-neutral-50 px-4 py-3.5">
                                    <div class="h-9 w-9 shrink-0 rounded-full bg-neutral-100" />
                                    <div class="min-w-0 flex-1">
                                        <div class="h-2 w-16 rounded bg-neutral-200" />
                                        <div class="mt-1.5 h-2 w-24 rounded bg-neutral-100" />
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col border-r border-neutral-100 bg-white">
                                <div class="flex items-center gap-2.5 border-b border-neutral-100 px-5 py-4">
                                    <div class="h-9 w-9 rounded-full bg-[var(--color-accent-200)]" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-neutral-900">Nina Kovač</p>
                                        <div class="flex items-center gap-1 text-xs text-neutral-400">
                                            <ChannelIcon type="instagram" />
                                            <span>Instagram</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex-1 space-y-3 px-5 py-6">
                                    <div class="flex justify-start">
                                        <div class="max-w-[85%] rounded-2xl rounded-bl-sm bg-neutral-100 px-4 py-2.5 text-sm text-neutral-800">
                                            Živjo! Bi bilo možno naročiti torto za 30. avgust? Nekaj za približno
                                            20 ljudi 😊
                                        </div>
                                    </div>
                                    <div class="flex justify-end">
                                        <div class="max-w-[85%] rounded-2xl rounded-br-sm bg-[var(--color-accent-500)] px-4 py-2.5 text-sm text-white">
                                            Seveda — 85 €, ara 20 €. Za koga praznuješ? 🎂
                                        </div>
                                    </div>
                                </div>

                                <div class="border-t border-neutral-100 px-5 py-3.5">
                                    <div class="flex items-center gap-2 rounded-lg border border-neutral-200 bg-neutral-50 px-3.5 py-2.5">
                                        <span class="text-sm text-neutral-400">Napiši sporočilo …</span>
                                    </div>
                                </div>
                            </div>

                            <div class="hidden bg-neutral-50 px-5 py-5 lg:block">
                                <p class="text-sm font-semibold text-neutral-900">Nina Kovač</p>
                                <p class="mt-0.5 text-xs text-neutral-500">3 prejšnja naročila · 248 € skupaj</p>

                                <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-3.5">
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

                                <button
                                    type="button"
                                    class="mt-3 flex w-full items-center justify-center gap-1.5 rounded-lg bg-[var(--color-accent-500)] px-3 py-2 text-xs font-semibold text-white"
                                >
                                    <Plus :size="13" /> Ustvari naročilo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Reveal>
        </section>

        <!-- PAIN: "Se ti sliši znano?" -->
        <section class="border-t border-neutral-100 bg-neutral-50 py-20 sm:py-28">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <Reveal>
                    <h2 class="text-center text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                        Se ti tole sliši znano?
                    </h2>
                </Reveal>

                <div class="mx-auto mt-14 grid max-w-4xl grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                    <Reveal v-for="(fragment, i) in painFragments" :key="fragment.text" :delay="i * 60">
                        <div
                            class="rounded-xl border border-neutral-200 bg-white px-5 py-4 shadow-sm shadow-neutral-900/[0.04] transition hover:-translate-y-0.5 hover:shadow-md"
                            :class="fragment.rotate"
                        >
                            <span class="inline-block rounded-md px-1.5 py-0.5 text-[10px] font-semibold tracking-wide uppercase" :class="fragment.accent">
                                {{ fragment.tag }}
                            </span>
                            <p class="mt-2 text-[15px] leading-snug text-neutral-800">{{ fragment.text }}</p>
                        </div>
                    </Reveal>
                </div>

                <Reveal :delay="200">
                    <div class="mx-auto mt-16 max-w-2xl text-center">
                        <p class="text-2xl font-semibold text-neutral-900">
                            Beležka je narejena za točno ta del posla.
                        </p>

                        <div class="mt-8 inline-flex flex-wrap items-center justify-center gap-2 text-sm font-medium text-neutral-800">
                            <span class="rounded-md bg-white px-3 py-1.5 shadow-sm shadow-neutral-900/[0.04]">Sporočilo</span>
                            <ArrowRight :size="14" class="text-neutral-300" />
                            <span class="rounded-md bg-white px-3 py-1.5 shadow-sm shadow-neutral-900/[0.04]">Stranka</span>
                            <ArrowRight :size="14" class="text-neutral-300" />
                            <span class="rounded-md bg-white px-3 py-1.5 shadow-sm shadow-neutral-900/[0.04]">Naročilo / Termin</span>
                            <ArrowRight :size="14" class="text-neutral-300" />
                            <span class="rounded-md bg-[var(--color-accent-500)] px-3 py-1.5 text-white shadow-sm">vse ostane povezano</span>
                        </div>
                    </div>
                </Reveal>
            </div>
        </section>

        <!-- CUSTOMER STORY -->
        <section id="kako-deluje" class="border-t border-neutral-100 bg-white py-20 sm:py-28">
            <div class="mx-auto max-w-4xl px-4 sm:px-6">
                <Reveal>
                    <div class="text-center">
                        <h2 class="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                            En pogovor. En posel. Brez prepisovanja.
                        </h2>
                    </div>
                </Reveal>

                <div class="mt-16 space-y-10">
                    <!-- Step 1 -->
                    <Reveal>
                        <div class="grid grid-cols-[2rem_1fr] gap-5 sm:grid-cols-[2.5rem_1fr]">
                            <div class="flex flex-col items-center">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--color-accent-100)] text-sm font-semibold text-[var(--color-accent-700)] sm:h-10 sm:w-10">1</span>
                                <span class="mt-2 w-px flex-1 bg-neutral-200" />
                            </div>
                            <div class="pb-4">
                                <p class="text-sm font-medium text-neutral-400">Instagram DM</p>
                                <div class="mt-2 inline-block max-w-md rounded-2xl rounded-tl-sm bg-neutral-100 px-4 py-3 text-sm text-neutral-800">
                                    "Živjo, bi bilo možno naročiti torto za soboto?"
                                </div>
                            </div>
                        </div>
                    </Reveal>

                    <!-- Step 2 -->
                    <Reveal>
                        <div class="grid grid-cols-[2rem_1fr] gap-5 sm:grid-cols-[2.5rem_1fr]">
                            <div class="flex flex-col items-center">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--color-accent-100)] text-sm font-semibold text-[var(--color-accent-700)] sm:h-10 sm:w-10">2</span>
                                <span class="mt-2 w-px flex-1 bg-neutral-200" />
                            </div>
                            <div class="pb-4">
                                <p class="text-sm font-medium text-neutral-400">Dogovorita se za podrobnosti</p>
                                <div class="mt-2 flex flex-wrap gap-2 text-sm font-medium text-neutral-800">
                                    <span class="rounded-md bg-neutral-100 px-3 py-1.5">Rojstnodnevna torta</span>
                                    <span class="rounded-md bg-neutral-100 px-3 py-1.5">85 €</span>
                                    <span class="rounded-md bg-neutral-100 px-3 py-1.5">Ara 20 €</span>
                                    <span class="rounded-md bg-neutral-100 px-3 py-1.5">Sobota</span>
                                </div>
                            </div>
                        </div>
                    </Reveal>

                    <!-- Step 3 -->
                    <Reveal>
                        <div class="grid grid-cols-[2rem_1fr] gap-5 sm:grid-cols-[2.5rem_1fr]">
                            <div class="flex flex-col items-center">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--color-accent-100)] text-sm font-semibold text-[var(--color-accent-700)] sm:h-10 sm:w-10">3</span>
                                <span class="mt-2 w-px flex-1 bg-neutral-200" />
                            </div>
                            <div class="pb-4">
                                <p class="text-sm font-medium text-neutral-400">En klik in pogovor postane naročilo</p>
                                <button
                                    type="button"
                                    class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-[var(--color-accent-500)] px-3.5 py-2 text-xs font-semibold text-white"
                                >
                                    <Plus :size="13" /> Ustvari naročilo
                                </button>

                                <div class="mt-4 max-w-sm rounded-xl border border-neutral-200 bg-white p-4 shadow-sm shadow-neutral-900/[0.04]">
                                    <p class="text-xs font-medium text-neutral-400">Nina Kovač</p>
                                    <p class="mt-1 text-sm font-semibold text-neutral-900">Rojstnodnevna torta</p>
                                    <p class="mt-0.5 text-xs text-neutral-500">Rok: sobota</p>
                                    <div class="mt-3 flex flex-wrap items-center gap-1.5">
                                        <span class="text-sm font-semibold text-neutral-900">85 €</span>
                                        <Badge :color="depositPaid.color" :bg="depositPaid.bg">{{ depositPaid.label }}</Badge>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Reveal>

                    <!-- Step 4 -->
                    <Reveal>
                        <div class="grid grid-cols-[2rem_1fr] gap-5 sm:grid-cols-[2.5rem_1fr]">
                            <div class="flex flex-col items-center">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--color-accent-500)] text-sm font-semibold text-white sm:h-10 sm:w-10">4</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-neutral-400">Tri mesece kasneje …</p>
                                <div class="mt-2 inline-block max-w-md rounded-2xl rounded-tl-sm bg-neutral-100 px-4 py-3 text-sm text-neutral-800">
                                    "Živjo 😊"
                                </div>

                                <div class="mt-4 max-w-sm rounded-xl border border-[var(--color-accent-200)] bg-[var(--color-accent-50)] p-4">
                                    <p class="text-sm font-semibold text-neutral-900">Nina Kovač</p>
                                    <p class="mt-1 text-xs text-neutral-600">4 prejšnja naročila · 326 € skupaj</p>
                                    <p class="text-xs text-neutral-600">Zadnje naročilo: 12. 7.</p>
                                </div>

                                <p class="mt-4 max-w-md text-neutral-600">
                                    Ni ti treba iskati starih sporočil, da ugotoviš, kdo je stranka.
                                </p>
                            </div>
                        </div>
                    </Reveal>
                </div>
            </div>
        </section>

        <!-- ORDERS VS APPOINTMENTS -->
        <section class="border-t border-neutral-100 bg-white py-20 sm:py-28">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <Reveal>
                    <h2 class="text-center text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                        Za naročila. Za termine. Ali oboje.
                    </h2>
                </Reveal>

                <div class="mt-14 grid grid-cols-1 overflow-hidden rounded-2xl border border-neutral-200 lg:grid-cols-2">
                    <Reveal>
                        <div class="h-full bg-white p-8 sm:p-10">
                            <p class="text-xs font-semibold tracking-wide text-[var(--color-accent-600)] uppercase">Pečeš torte?</p>
                            <div class="mt-4 flex flex-wrap items-center gap-2 text-sm font-medium text-neutral-700">
                                <span class="rounded-md bg-neutral-100 px-2.5 py-1">DM</span>
                                <ArrowRight :size="12" class="text-neutral-300" />
                                <span class="rounded-md bg-neutral-100 px-2.5 py-1">Naročilo</span>
                                <ArrowRight :size="12" class="text-neutral-300" />
                                <span class="rounded-md bg-neutral-100 px-2.5 py-1">Rok</span>
                                <ArrowRight :size="12" class="text-neutral-300" />
                                <span class="rounded-md bg-neutral-100 px-2.5 py-1">Ara</span>
                                <ArrowRight :size="12" class="text-neutral-300" />
                                <span class="rounded-md bg-[var(--color-accent-100)] px-2.5 py-1 text-[var(--color-accent-700)]">Zaključeno</span>
                            </div>

                            <div class="mt-6 max-w-xs rounded-xl border border-neutral-200 p-4">
                                <p class="text-sm font-medium text-neutral-900">Rojstnodnevna torta</p>
                                <p class="mt-0.5 text-xs text-neutral-500">30. avgust</p>
                                <div class="mt-3 flex flex-wrap items-center gap-1.5">
                                    <span class="text-sm font-semibold text-neutral-900">85 €</span>
                                    <Badge :color="depositPaid.color" :bg="depositPaid.bg">Ara 20 €</Badge>
                                </div>
                            </div>

                            <p class="mt-6 text-sm text-neutral-500">
                                Torte, cvetje, custom darila, ročno izdelani in digitalni izdelki.
                            </p>
                        </div>
                    </Reveal>

                    <Reveal :delay="100">
                        <div class="h-full border-t border-neutral-200 bg-neutral-50 p-8 sm:border-t-0 sm:border-l sm:p-10">
                            <p class="text-xs font-semibold tracking-wide text-[var(--color-accent-600)] uppercase">Delaš po terminih?</p>
                            <div class="mt-4 flex flex-wrap items-center gap-2 text-sm font-medium text-neutral-700">
                                <span class="rounded-md bg-white px-2.5 py-1 shadow-sm">DM</span>
                                <ArrowRight :size="12" class="text-neutral-300" />
                                <span class="rounded-md bg-white px-2.5 py-1 shadow-sm">Termin</span>
                                <ArrowRight :size="12" class="text-neutral-300" />
                                <span class="rounded-md bg-white px-2.5 py-1 shadow-sm">Storitev</span>
                                <ArrowRight :size="12" class="text-neutral-300" />
                                <span class="rounded-md bg-white px-2.5 py-1 shadow-sm">Ura</span>
                                <ArrowRight :size="12" class="text-neutral-300" />
                                <span class="rounded-md bg-[var(--color-accent-100)] px-2.5 py-1 text-[var(--color-accent-700)]">Plačilo</span>
                            </div>

                            <div class="mt-6 max-w-xs rounded-xl border border-neutral-200 bg-white p-4">
                                <p class="text-sm font-medium text-neutral-900">BIAB refill</p>
                                <p class="mt-0.5 text-xs text-neutral-500">Petek, 14:00 · 75 min</p>
                                <div class="mt-3 flex flex-wrap items-center gap-1.5">
                                    <span class="text-sm font-semibold text-neutral-900">42 €</span>
                                    <Badge :color="depositPaid.color" :bg="depositPaid.bg">Ara 10 €</Badge>
                                </div>
                            </div>

                            <p class="mt-6 text-sm text-neutral-500">
                                Nohti, frizerstvo, lash &amp; brow, tattoo, fotografija, beauty storitve.
                            </p>
                        </div>
                    </Reveal>
                </div>
            </div>
        </section>

        <!-- INBOX CONTEXT -->
        <section class="border-t border-neutral-100 bg-neutral-50 py-20 sm:py-28">
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <Reveal>
                    <h2 class="mx-auto max-w-2xl text-center text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                        Ne odpiraj Instagrama samo zato, da preveriš, ali si komu pozabila odgovoriti.
                    </h2>
                </Reveal>

                <div class="mt-14 grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <Reveal>
                        <div class="rounded-xl border border-neutral-200 bg-white p-5 opacity-70">
                            <p class="text-xs font-semibold text-neutral-400 uppercase">Socialni inbox</p>
                            <div class="mt-3 rounded-lg bg-neutral-100 px-3.5 py-2.5 text-sm text-neutral-600">
                                "Bi bilo možno naročiti torto za 30. avgust?"
                            </div>
                            <p class="mt-3 text-xs text-neutral-400">Samo sporočilo. Nič drugega.</p>
                        </div>
                    </Reveal>
                    <Reveal :delay="100">
                        <div class="rounded-xl border border-[var(--color-accent-200)] bg-white p-5 shadow-sm shadow-[var(--color-accent-500)]/10">
                            <p class="text-xs font-semibold text-[var(--color-accent-600)] uppercase">Beležka</p>
                            <div class="mt-3 rounded-lg bg-[var(--color-accent-50)] px-3.5 py-2.5 text-sm text-neutral-800">
                                "Bi bilo možno naročiti torto za 30. avgust?"
                            </div>
                            <div class="mt-3 flex flex-wrap gap-1.5 text-xs">
                                <span class="rounded-md bg-neutral-100 px-2 py-1 text-neutral-600">3 prejšnja naročila</span>
                                <span class="rounded-md bg-neutral-100 px-2 py-1 text-neutral-600">Aktivno naročilo</span>
                                <span class="rounded-md bg-neutral-100 px-2 py-1 text-neutral-600">248 € skupaj</span>
                            </div>
                        </div>
                    </Reveal>
                </div>

                <Reveal :delay="150">
                    <div class="mx-auto mt-12 grid max-w-2xl grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="flex items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 py-2.5">
                            <Instagram :size="15" class="text-[#E1306C]" />
                            <div>
                                <p class="text-xs font-medium text-neutral-800">Instagram</p>
                                <p class="text-[10px] font-medium text-emerald-600">Povezano</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 py-2.5">
                            <Facebook :size="15" class="text-[#0084FF]" />
                            <div>
                                <p class="text-xs font-medium text-neutral-800">Messenger</p>
                                <p class="text-[10px] font-medium text-emerald-600">Povezano</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 rounded-lg border border-dashed border-neutral-200 bg-white/60 px-3 py-2.5 opacity-60">
                            <Music2 :size="15" class="text-neutral-400" />
                            <div>
                                <p class="text-xs font-medium text-neutral-500">TikTok</p>
                                <p class="text-[10px] font-medium text-neutral-400">Kmalu</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 rounded-lg border border-dashed border-neutral-200 bg-white/60 px-3 py-2.5 opacity-60">
                            <MessageCircle :size="15" class="text-neutral-400" />
                            <div>
                                <p class="text-xs font-medium text-neutral-500">WhatsApp</p>
                                <p class="text-[10px] font-medium text-neutral-400">Kmalu</p>
                            </div>
                        </div>
                    </div>
                </Reveal>
            </div>
        </section>

        <!-- TODAY: dark ink section -->
        <section class="border-t border-neutral-100 bg-[var(--color-ink-950)] py-20 sm:py-28">
            <div class="mx-auto grid max-w-6xl grid-cols-1 items-center gap-12 px-4 sm:px-6 lg:grid-cols-2">
                <Reveal>
                    <div>
                        <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                            Odpreš Beležko.<br />Takoj veš, kaj danes gori.
                        </h2>
                        <p class="mt-5 max-w-md text-neutral-300">
                            Ni dashboard zato, da bi gledala grafe.
                            <br />
                            Je seznam stvari, ki jih danes ne smeš pozabiti.
                        </p>
                    </div>
                </Reveal>

                <Reveal :delay="150">
                    <div class="rounded-xl border border-white/10 bg-white shadow-2xl shadow-black/30">
                        <div class="rounded-t-xl bg-gradient-to-br from-[var(--color-accent-500)] to-[var(--color-accent-700)] px-5 py-4 text-white">
                            <p class="text-sm font-semibold">Danes je 15. avgust.</p>
                        </div>
                        <div class="space-y-2 p-4">
                            <div class="flex items-center justify-between rounded-lg border border-neutral-100 px-3 py-2.5">
                                <span class="text-sm text-neutral-700">3 neodgovorjena sporočila</span>
                                <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-[var(--color-accent-500)] px-1.5 text-xs font-semibold text-white">3</span>
                            </div>
                            <div class="flex items-center justify-between rounded-lg border border-neutral-100 px-3 py-2.5">
                                <span class="text-sm text-neutral-700">09:00 — Gel nohti, Ana</span>
                            </div>
                            <div class="flex items-center justify-between rounded-lg border border-neutral-100 px-3 py-2.5">
                                <span class="text-sm text-neutral-700">14:30 — BIAB refill, Maja</span>
                            </div>
                            <div class="flex items-center justify-between rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5">
                                <span class="text-sm text-neutral-800">Rojstnodnevna torta — rok danes do 16:00</span>
                            </div>
                            <div class="flex items-center gap-2 rounded-lg border border-neutral-100 px-3 py-2.5">
                                <Bell :size="13" class="shrink-0 text-neutral-400" />
                                <span class="text-sm text-neutral-700">Piši Tini glede ponudbe</span>
                            </div>
                        </div>
                    </div>
                </Reveal>
            </div>
        </section>

        <!-- FEATURE TRIO -->
        <section id="funkcije" class="border-t border-neutral-100 bg-white py-20 sm:py-28">
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <Reveal>
                    <h2 class="mx-auto max-w-xl text-center text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                        Ko je vse povezano, ti ni treba vsakič začeti iz nič.
                    </h2>
                </Reveal>

                <div class="mt-16 space-y-16">
                    <Reveal>
                        <div class="grid grid-cols-1 items-center gap-8 lg:grid-cols-2">
                            <div>
                                <p class="text-xs font-semibold tracking-wide text-[var(--color-accent-600)] uppercase">Katalog</p>
                                <h3 class="mt-2 text-2xl font-semibold text-neutral-900">Cene vpišeš enkrat.</h3>
                                <p class="mt-3 max-w-md text-neutral-600">
                                    Ko pri novem naročilu ali terminu izbereš izdelek ali storitev, se trajanje,
                                    cena in ara izpolnijo sami.
                                </p>
                            </div>
                            <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-5 lg:ml-auto lg:max-w-xs">
                                <p class="text-sm font-medium text-neutral-900">BIAB refill</p>
                                <p class="mt-0.5 text-xs text-neutral-500">75 min</p>
                                <div class="mt-3 flex items-center gap-2 text-sm">
                                    <span class="font-semibold text-neutral-900">42 €</span>
                                    <Badge :color="depositPaid.color" :bg="depositPaid.bg">Ara 10 €</Badge>
                                </div>
                            </div>
                        </div>
                    </Reveal>

                    <Reveal>
                        <div class="grid grid-cols-1 items-center gap-8 lg:grid-cols-2">
                            <div class="order-2 rounded-xl border border-neutral-200 bg-neutral-50 p-5 lg:order-1 lg:max-w-xs">
                                <div class="flex items-start gap-2.5">
                                    <Bell :size="15" class="mt-0.5 shrink-0 text-[var(--color-accent-600)]" />
                                    <div>
                                        <p class="text-xs font-medium text-neutral-400">Danes, 16:00</p>
                                        <p class="text-sm text-neutral-800">Piši Maji glede ponudbe za torto</p>
                                    </div>
                                </div>
                            </div>
                            <div class="order-1 lg:order-2">
                                <p class="text-xs font-semibold tracking-wide text-[var(--color-accent-600)] uppercase">Opomniki</p>
                                <h3 class="mt-2 text-2xl font-semibold text-neutral-900">Beležka te spomni.</h3>
                                <p class="mt-3 max-w-md text-neutral-600">
                                    Nastaviš opomnik in nobeno povpraševanje ne pade v pozabo, tudi če ta teden ni
                                    časa.
                                </p>
                            </div>
                        </div>
                    </Reveal>

                    <Reveal>
                        <div class="grid grid-cols-1 items-center gap-8 lg:grid-cols-2">
                            <div>
                                <p class="text-xs font-semibold tracking-wide text-[var(--color-accent-600)] uppercase">Analitika</p>
                                <h3 class="mt-2 text-2xl font-semibold text-neutral-900">Končno veš, kaj se dogaja.</h3>
                                <p class="mt-3 max-w-md text-neutral-600">
                                    Prihodek, od kod prihajajo povpraševanja in kaj se najbolje prodaja — brez
                                    kompliciranega poročanja.
                                </p>
                            </div>
                            <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-5 lg:ml-auto lg:max-w-xs">
                                <p class="text-xs font-medium text-neutral-500">Prihodek</p>
                                <p class="mt-1 text-xl font-semibold text-neutral-900">4.820 €</p>
                                <div class="mt-3 space-y-1.5 border-t border-neutral-200 pt-3 text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="flex items-center gap-1 text-neutral-500"><Instagram :size="11" class="text-[#E1306C]" /> Instagram</span>
                                        <span class="font-medium text-neutral-800">3.150 €</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="flex items-center gap-1 text-neutral-500"><Facebook :size="11" class="text-[#0084FF]" /> Facebook</span>
                                        <span class="font-medium text-neutral-800">1.670 €</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Reveal>
                </div>
            </div>
        </section>

        <!-- WHO IT'S FOR -->
        <section id="za-koga" class="border-t border-neutral-100 bg-neutral-50 py-20 sm:py-28">
            <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
                <Reveal>
                    <h2 class="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                        Če se tvoj posel začne v DM-jih, si verjetno na pravem mestu.
                    </h2>
                </Reveal>

                <Reveal :delay="100">
                    <p class="mx-auto mt-8 max-w-2xl text-xl leading-relaxed text-neutral-500">
                        <span class="text-neutral-900">Torte &amp; sladice</span> ·
                        <span class="text-neutral-900">Cvetličarne</span> ·
                        <span class="text-neutral-900">Nohti &amp; beauty</span> ·
                        <span class="text-neutral-900">Frizerji</span> ·
                        <span class="text-neutral-900">Tattoo</span> ·
                        <span class="text-neutral-900">Fotografi</span> ·
                        <span class="text-neutral-900">Custom darila</span> ·
                        <span class="text-neutral-900">Handmade</span> ·
                        <span class="text-neutral-900">Poročni ponudniki</span>
                    </p>
                </Reveal>
            </div>
        </section>

        <!-- BEFORE / AFTER -->
        <section class="border-t border-neutral-100 bg-white py-20 sm:py-28">
            <div class="mx-auto max-w-4xl px-4 sm:px-6">
                <Reveal>
                    <h2 class="text-center text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                        Manj "čakaj, kje sem to zapisala?"<br />Več "vem, kaj je naslednje."
                    </h2>
                </Reveal>

                <div class="mt-16 grid grid-cols-1 gap-10 sm:grid-cols-2">
                    <Reveal>
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-neutral-400 uppercase">Pred Beležko</p>
                            <ul class="mt-4 space-y-3">
                                <li v-for="item in ['Instagram', 'Messenger', 'Zapiski', 'Koledar', 'Screenshoti', 'Spomin']" :key="item" class="text-2xl font-medium text-neutral-300 line-through decoration-2">
                                    {{ item }}
                                </li>
                            </ul>
                        </div>
                    </Reveal>
                    <Reveal :delay="100">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-[var(--color-accent-600)] uppercase">Z Beležko</p>
                            <ul class="mt-4 space-y-3">
                                <li v-for="item in ['Inbox', 'Stranka', 'Naročilo / Termin', 'Danes']" :key="item" class="text-2xl font-semibold text-neutral-900">
                                    {{ item }}
                                </li>
                            </ul>
                        </div>
                    </Reveal>
                </div>
            </div>
        </section>

        <!-- PRICING -->
        <section id="cena" class="border-t border-neutral-100 bg-neutral-50 py-20 sm:py-28">
            <div class="mx-auto max-w-2xl px-4 text-center sm:px-6">
                <Reveal>
                    <h2 class="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">Preprosta cena.</h2>
                    <p class="mt-4 text-neutral-600">En paket. Vse, kar potrebuješ za urejanje sporočil in poslovanja.</p>
                </Reveal>

                <Reveal :delay="100">
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
                            Začni z Beležko
                            <ArrowRight :size="16" />
                        </Link>
                    </div>
                </Reveal>
            </div>
        </section>

        <!-- FAQ -->
        <section class="border-t border-neutral-100 bg-white py-20 sm:py-28">
            <div class="mx-auto max-w-2xl px-4 sm:px-6">
                <Reveal>
                    <h2 class="text-center text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                        Pogosta vprašanja
                    </h2>
                </Reveal>

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
        <section class="border-t border-neutral-100 bg-gradient-to-br from-[var(--color-accent-500)] to-[var(--color-accent-700)] py-24 sm:py-32">
            <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
                <Sparkles :size="28" class="mx-auto text-white/70" />
                <h2 class="mt-6 text-4xl leading-[1.1] font-semibold tracking-tight text-white sm:text-5xl">
                    Tvoj posel se že začne v DM-jih.
                    <br />
                    Naj se kaos tam tudi konča.
                </h2>

                <div class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <Link
                        :href="route('register')"
                        class="inline-flex items-center gap-2 rounded-lg bg-white px-7 py-3.5 text-sm font-semibold text-[var(--color-accent-700)] shadow-sm transition hover:bg-neutral-50"
                    >
                        Začni z Beležko
                        <ArrowRight :size="16" />
                    </Link>
                    <Link
                        :href="route('login')"
                        class="inline-flex items-center gap-2 rounded-lg border border-white/30 px-7 py-3.5 text-sm font-semibold text-white transition hover:bg-white/10"
                    >
                        Prijava
                    </Link>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
