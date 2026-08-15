<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useIntersectionObserver } from '@vueuse/core';
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import Reveal from '@/Components/Marketing/Reveal.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import Badge from '@/Components/Badge.vue';
import CustomerAvatar from '@/Components/Marketing/CustomerAvatar.vue';
import CakeThumbnail from '@/Components/Marketing/CakeThumbnail.vue';
import { PAYMENT_STATUS_META } from '@/lib/statuses';
import {
    Instagram,
    Facebook,
    Music2,
    MessageCircle,
    ArrowRight,
    ArrowDown,
    Check,
    Plus,
    Minus,
    Bell,
    Sparkles,
    Mail,
    Phone,
    CalendarDays,
    StickyNote,
    Paperclip,
    SendHorizontal,
} from 'lucide-vue-next';

// Hero mockup animation: a small state machine that loops through the
// "DM -> reply -> reference photo -> order popup -> order created ->
// remembers customer" story. Each phase's duration is deliberately uneven
// so it reads as a live product rather than a metronome. Message bubbles
// live inside a fixed-height, bottom-anchored thread so the mockup's
// overall height never changes and nothing below it on the page jumps as
// it loops.
const HERO_PHASES = [
    { name: 'idle', duration: 1000 },
    { name: 'incoming', duration: 1600 },
    { name: 'typing', duration: 900 },
    { name: 'reply', duration: 1700 },
    { name: 'photo', duration: 1900 },
    { name: 'popup-open', duration: 700 },
    { name: 'popup-fill', duration: 1500 },
    { name: 'popup-confirm', duration: 700 },
    { name: 'created', duration: 1500 },
    { name: 'followup-typing', duration: 900 },
    { name: 'followup', duration: 2000 },
    { name: 'thanks', duration: 1400 },
    { name: 'react', duration: 1800 },
    { name: 'remember', duration: 1600 },
] as const;

const heroPhase = ref(0);
const prefersReducedMotion = ref(false);
let heroTimer: ReturnType<typeof setTimeout> | null = null;

function scheduleHeroPhase() {
    if (prefersReducedMotion.value) return;
    heroTimer = setTimeout(() => {
        heroPhase.value = (heroPhase.value + 1) % HERO_PHASES.length;
        scheduleHeroPhase();
    }, HERO_PHASES[heroPhase.value].duration);
}

onMounted(() => {
    prefersReducedMotion.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion.value) {
        heroPhase.value = 12; // polished static end-state: conversation, order, thanks and reaction all resolved
    } else {
        scheduleHeroPhase();
    }
});

onUnmounted(() => {
    if (heroTimer) clearTimeout(heroTimer);
});

const showUnreadFlash = computed(() => heroPhase.value === 1);
const showTyping = computed(() => heroPhase.value === 2 || heroPhase.value === 9);
const highlightCreateBtn = computed(() => heroPhase.value === 5);
const showPopup = computed(() => heroPhase.value >= 5 && heroPhase.value <= 7);
const popupFilled = computed(() => heroPhase.value >= 6);
const popupConfirming = computed(() => heroPhase.value === 7);
const showOrderCard = computed(() => heroPhase.value >= 8);
const showCreatedBadge = computed(() => heroPhase.value === 8);
const showThanksReaction = computed(() => heroPhase.value >= 12);
const heroDepositBadge = computed(() =>
    heroPhase.value >= 11
        ? PAYMENT_STATUS_META.paid
        : { label: 'Neplačana ara', color: PAYMENT_STATUS_META.deposit_due.color, bg: PAYMENT_STATUS_META.deposit_due.bg },
);

type HeroMessage = { id: string; from: 'them' | 'me'; type: 'text' | 'photo'; text: string };

const heroMessages = computed<HeroMessage[]>(() => {
    const list: HeroMessage[] = [];
    if (heroPhase.value >= 1) {
        list.push({ id: 'm1', from: 'them', type: 'text', text: 'Živjo! Bi bilo možno naročiti torto za 30. avgust? Nekaj za približno 20 ljudi 😊' });
    }
    if (heroPhase.value >= 3) {
        list.push({ id: 'm2', from: 'me', type: 'text', text: 'Seveda 😊 Kakšen stil ste imeli v mislih? Za koga pa bo torta?' });
    }
    if (heroPhase.value >= 4) {
        list.push({ id: 'm3', from: 'them', type: 'photo', text: 'Za hčerkin 10. rojstni dan. Tole bi mi bilo všeč:' });
    }
    if (heroPhase.value >= 10) {
        list.push({
            id: 'm4',
            from: 'me',
            type: 'text',
            text: 'Super 😊 Ara je 20 €. Torta bo pripravljena 30. avgusta ob 8:00 — vam ta ura ustreza?',
        });
    }
    if (heroPhase.value >= 11) {
        list.push({ id: 'm5', from: 'them', type: 'text', text: 'Odlično! Nakazano, se že veselim 🥰' });
    }
    return list;
});

// Payoff animation ("Se ti sliši znano?" section): four mini product cards
// around the static central Beležka card, each previewing a real piece of
// the app that one organized record already gives the owner. Plays through
// ONCE, only after scrolling into view, then stays fully visible — no loop.
const PAYOFF_STEP_DURATIONS = [400, 500, 500, 350, 350, 500, 350, 350] as const; // 0->1 .. 7->8
const PAYOFF_FINAL_PHASE = 8;

const payoffEl = ref<HTMLElement | null>(null);
const payoffPhase = ref(0);
const payoffReducedMotion = ref(false);
let payoffTimer: ReturnType<typeof setTimeout> | null = null;
let payoffStarted = false;

function schedulePayoffPhase() {
    if (payoffReducedMotion.value || payoffPhase.value >= PAYOFF_FINAL_PHASE) return;
    payoffTimer = setTimeout(() => {
        payoffPhase.value += 1;
        schedulePayoffPhase();
    }, PAYOFF_STEP_DURATIONS[payoffPhase.value]);
}

const { stop: stopPayoffObserver } = useIntersectionObserver(
    payoffEl,
    ([entry]) => {
        if (!entry?.isIntersecting || payoffStarted) return;
        payoffStarted = true;
        payoffReducedMotion.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (payoffReducedMotion.value) {
            payoffPhase.value = PAYOFF_FINAL_PHASE; // polished static end-state: everything resolved and visible
        } else {
            schedulePayoffPhase();
        }
        stopPayoffObserver();
    },
    { threshold: 0.4 },
);

onUnmounted(() => {
    if (payoffTimer) clearTimeout(payoffTimer);
});

const showCalloutCustomer = computed(() => payoffPhase.value >= 1);
const showCalloutConversation = computed(() => payoffPhase.value >= 2);
const showCalloutStatus = computed(() => payoffPhase.value >= 3);
// 0 = Potrjeno, 1 = V pripravi, 2 = Pripravljeno
const calloutStatusStage = computed(() => (payoffPhase.value >= 5 ? 2 : payoffPhase.value >= 4 ? 1 : 0));
const showCalloutNext = computed(() => payoffPhase.value >= 6);
// 0 = Naročeno, 1 = Ara plačana, 2 = Plačano
const calloutPaymentStage = computed(() => (payoffPhase.value >= 8 ? 2 : payoffPhase.value >= 7 ? 1 : 0));

// Placeholder — swap when pricing is finalized. Referenced only here.
const MONTHLY_PRICE = '19 €';

const painCategories = [
    {
        label: 'Dogovori',
        offset: 'lg:mt-0',
        items: [
            { text: 'Kakšno ceno sem že postavila?', tag: 'Zapiski', rotate: 'sm:-rotate-2', accent: 'bg-neutral-100 text-neutral-500' },
            { text: 'Kje sva rekli, da bo prevzem?', tag: 'Instagram', rotate: 'sm:rotate-1', accent: 'bg-[#FCE7F0] text-[#E1306C]' },
            { text: 'Kakšno torto je že želela?', tag: 'Messenger', rotate: 'sm:-rotate-1', accent: 'bg-[#E3F1FF] text-[#0084FF]' },
        ],
    },
    {
        label: 'Roki in termini',
        offset: 'lg:mt-10',
        items: [
            { text: 'Kdo pride v petek?', tag: 'Koledar', rotate: 'sm:rotate-2', accent: 'bg-neutral-100 text-neutral-500' },
            { text: 'A sem termin prestavila?', tag: 'Koledar', rotate: 'sm:-rotate-2', accent: 'bg-neutral-100 text-neutral-500' },
            { text: 'Katera naročila imam ta teden?', tag: 'Zapiski', rotate: 'sm:rotate-1', accent: 'bg-neutral-100 text-neutral-500' },
        ],
    },
    {
        label: 'Follow-upi in plačila',
        offset: 'lg:mt-4',
        items: [
            { text: 'Ali je bila ara že plačana?', tag: 'Plačila', rotate: 'sm:-rotate-1', accent: 'bg-amber-50 text-amber-700' },
            { text: 'Komu moram še odgovoriti?', tag: 'Instagram', rotate: 'sm:rotate-2', accent: 'bg-[#FCE7F0] text-[#E1306C]' },
            { text: 'Koga moram danes spomniti?', tag: 'V glavi', rotate: 'sm:-rotate-2', accent: 'bg-[var(--color-accent-50)] text-[var(--color-accent-700)]' },
        ],
    },
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
        <section class="bg-[var(--color-ink-950)] pt-14 pb-20 sm:pt-20 sm:pb-28">
            <div class="mx-auto max-w-4xl px-4 text-center sm:px-6">
                <h1 class="text-4xl leading-[1.15] font-semibold tracking-tight text-white sm:text-5xl lg:text-[3.1rem]">
                    Sprejemaš naročila ali rezervacije prek zasebnih sporočil?
                </h1>

                <p class="mx-auto mt-5 max-w-xl text-xl font-medium text-neutral-200 sm:text-2xl">
                    Pozabi na preklapljanje med aplikacijami, spregledana sporočila in zamujene roke.
                </p>

                <p class="mx-auto mt-4 max-w-lg text-base text-neutral-400 sm:text-lg">
                    Sinhroniziraj Instagram in Facebook sporočila ter vodi stranke, naročila in termine — brez
                    prepisovanja v zapiske, koledarje in druge aplikacije.
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
                        class="inline-flex items-center gap-2 rounded-lg px-6 py-3 text-sm font-semibold text-neutral-300 transition hover:text-white"
                    >
                        Poglej, kako deluje
                    </a>
                </div>
            </div>

            <!-- Hero visual: full-bleed real product composition -->
            <Reveal :delay="150">
                <div class="relative mx-auto mt-12 max-w-6xl px-2 sm:mt-16 sm:px-6">
                    <div class="relative overflow-hidden rounded-2xl border border-black/5 bg-white shadow-2xl shadow-black/40 ring-1 ring-white/10 sm:rounded-3xl">
                        <div class="flex h-12 shrink-0 items-center border-b border-neutral-200 bg-white px-5">
                            <span class="text-sm font-semibold text-neutral-900">Prejeta pošta</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[240px_1fr] lg:grid-cols-[240px_1fr_300px]">
                            <div class="hidden border-r border-neutral-100 bg-white sm:block">
                                <div class="flex items-center gap-2.5 border-b border-[var(--color-accent-100)] bg-[var(--color-accent-50)] px-4 py-3.5">
                                    <div class="relative shrink-0">
                                        <CustomerAvatar :size="36" />
                                        <Transition
                                            enter-active-class="transition duration-200 ease-out"
                                            enter-from-class="scale-0 opacity-0"
                                            leave-active-class="transition duration-200 ease-in"
                                            leave-to-class="scale-0 opacity-0"
                                        >
                                            <span
                                                v-if="showUnreadFlash"
                                                class="absolute -top-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-red-500 ring-2 ring-[var(--color-accent-50)]"
                                            />
                                        </Transition>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-neutral-800">Nina Kovač</p>
                                        <p class="truncate text-xs text-neutral-500">Poslala bi vam še sliko...</p>
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
                                    <CustomerAvatar :size="36" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-neutral-900">Nina Kovač</p>
                                        <div class="flex items-center gap-1 text-xs text-neutral-400">
                                            <ChannelIcon type="instagram" />
                                            <span>@nina.kovac</span>
                                        </div>
                                    </div>
                                </div>

                                <TransitionGroup
                                    tag="div"
                                    class="relative flex h-72 flex-col justify-end gap-3 overflow-hidden px-5 py-6"
                                    enter-active-class="transition duration-400 ease-out"
                                    enter-from-class="translate-y-3 opacity-0"
                                    leave-active-class="absolute inset-x-5 transition duration-300 ease-in"
                                    leave-to-class="opacity-0"
                                    move-class="transition-transform duration-400 ease-out"
                                >
                                    <div v-for="msg in heroMessages" :key="msg.id" class="flex" :class="msg.from === 'them' ? 'justify-start' : 'justify-end'">
                                        <div
                                            v-if="msg.type === 'text'"
                                            class="relative max-w-[85%] rounded-2xl px-4 py-2.5 text-sm"
                                            :class="
                                                msg.from === 'them'
                                                    ? 'rounded-bl-sm bg-neutral-100 text-neutral-800'
                                                    : 'rounded-br-sm bg-[var(--color-accent-500)] text-white'
                                            "
                                        >
                                            {{ msg.text }}
                                            <Transition
                                                enter-active-class="transition duration-200 ease-out"
                                                enter-from-class="scale-0 opacity-0"
                                            >
                                                <span
                                                    v-if="msg.id === 'm5' && showThanksReaction"
                                                    class="absolute -right-1.5 -bottom-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-white text-[11px] shadow ring-1 ring-black/5"
                                                >
                                                    ❤️
                                                </span>
                                            </Transition>
                                        </div>
                                        <div v-else class="max-w-[85%] space-y-2 rounded-2xl rounded-bl-sm bg-neutral-100 px-4 py-2.5 text-sm text-neutral-800">
                                            <p>{{ msg.text }}</p>
                                            <CakeThumbnail :size="76" />
                                        </div>
                                    </div>

                                    <div v-if="showTyping" key="typing" class="flex justify-end">
                                        <div class="flex items-center gap-1 rounded-2xl rounded-br-sm bg-[var(--color-accent-500)]/15 px-4 py-3">
                                            <span class="typing-dot h-1.5 w-1.5 rounded-full bg-[var(--color-accent-500)]" style="animation-delay: 0ms" />
                                            <span class="typing-dot h-1.5 w-1.5 rounded-full bg-[var(--color-accent-500)]" style="animation-delay: 150ms" />
                                            <span class="typing-dot h-1.5 w-1.5 rounded-full bg-[var(--color-accent-500)]" style="animation-delay: 300ms" />
                                        </div>
                                    </div>
                                </TransitionGroup>

                                <div class="border-t border-neutral-100 px-5 py-3.5">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-neutral-200 text-neutral-400 transition hover:text-neutral-600"
                                        >
                                            <Paperclip :size="15" />
                                        </button>
                                        <div class="flex flex-1 items-center rounded-lg border border-neutral-200 bg-neutral-50 px-3.5 py-2.5">
                                            <span class="text-sm text-neutral-400">Napiši sporočilo …</span>
                                        </div>
                                        <button
                                            type="button"
                                            class="flex shrink-0 items-center gap-1.5 rounded-md bg-[var(--color-accent-500)] px-3.5 py-2.5 text-xs font-semibold text-white"
                                        >
                                            <SendHorizontal :size="14" /> Pošlji
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="hidden bg-neutral-50 px-5 py-5 lg:block">
                                <div class="flex items-center gap-2">
                                    <CustomerAvatar :size="28" />
                                    <div>
                                        <p class="text-sm font-semibold text-neutral-900">Nina Kovač</p>
                                        <p class="text-xs text-neutral-500">@nina.kovac</p>
                                    </div>
                                </div>

                                <div class="mt-3 space-y-1 text-xs text-neutral-500">
                                    <p class="flex items-center gap-1.5"><Mail :size="11" /> nina@example.com</p>
                                    <p class="flex items-center gap-1.5"><Phone :size="11" /> +386 XX XXX XXX</p>
                                </div>

                                <p class="mt-3 rounded-md bg-white px-2.5 py-2 text-xs text-neutral-500 ring-1 ring-neutral-200">
                                    "Priporočila jo je prejšnja stranka."
                                </p>

                                <div class="mt-4 border-t border-neutral-200 pt-3">
                                    <p class="text-[10px] font-medium tracking-wide text-neutral-400 uppercase">Poslovni podatki</p>
                                    <dl class="mt-2 space-y-1.5 text-xs">
                                        <div class="flex items-center justify-between rounded">
                                            <dt class="text-neutral-500">Prejšnja naročila</dt>
                                            <dd class="font-medium text-neutral-800">3</dd>
                                        </div>
                                        <div class="flex items-center justify-between rounded">
                                            <dt class="text-neutral-500">Skupaj porabljeno</dt>
                                            <dd class="font-medium text-neutral-800">248 €</dd>
                                        </div>
                                        <div class="flex items-center justify-between rounded">
                                            <dt class="text-neutral-500">Zadnje naročilo</dt>
                                            <dd class="font-medium text-neutral-800">{{ showOrderCard ? '30. avgust' : '9. jul.' }}</dd>
                                        </div>
                                    </dl>
                                </div>

                                <div class="mt-4">
                                    <Transition
                                        enter-active-class="transition duration-300 ease-out"
                                        enter-from-class="translate-y-1 opacity-0"
                                        leave-active-class="transition duration-200 ease-in"
                                        leave-to-class="opacity-0"
                                        mode="out-in"
                                    >
                                        <div
                                            v-if="!showOrderCard"
                                            key="empty"
                                            class="flex min-h-[104px] items-center justify-center rounded-lg border border-dashed border-neutral-300 px-3 text-center text-[11px] text-neutral-400"
                                        >
                                            Naročilo še ni ustvarjeno
                                        </div>
                                        <div v-else key="order" class="min-h-[104px] rounded-lg border border-neutral-200 bg-white p-3.5">
                                            <div class="flex items-center justify-between">
                                                <p class="text-[10px] font-medium tracking-wide text-neutral-400 uppercase">
                                                    Aktivno naročilo
                                                </p>
                                                <Transition
                                                    enter-active-class="transition duration-200 ease-out"
                                                    enter-from-class="opacity-0"
                                                    leave-active-class="transition duration-200 ease-in"
                                                    leave-to-class="opacity-0"
                                                >
                                                    <span v-if="showCreatedBadge" class="flex items-center gap-1 text-[10px] font-medium text-emerald-600">
                                                        <Check :size="10" /> Ustvarjeno
                                                    </span>
                                                </Transition>
                                            </div>
                                            <p class="mt-1.5 text-sm font-medium text-neutral-900">Rojstnodnevna torta</p>
                                            <p class="mt-0.5 text-xs text-neutral-500">30. avgust</p>
                                            <div class="mt-2.5 flex items-center justify-between text-xs">
                                                <span class="font-semibold text-neutral-900">85 €</span>
                                                <Transition
                                                    enter-active-class="transition duration-200 ease-out"
                                                    enter-from-class="scale-90 opacity-0"
                                                    mode="out-in"
                                                >
                                                    <Badge :key="heroDepositBadge.label" :color="heroDepositBadge.color" :bg="heroDepositBadge.bg">
                                                        {{ heroDepositBadge.label }}
                                                    </Badge>
                                                </Transition>
                                            </div>
                                        </div>
                                    </Transition>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 border-t border-neutral-100 bg-white px-5 py-3.5">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-md bg-[var(--color-accent-500)] px-3.5 py-2 text-xs font-semibold text-white transition-all duration-300"
                                :class="highlightCreateBtn && 'scale-105 ring-2 ring-[var(--color-accent-300)]'"
                            >
                                <Plus :size="13" /> Ustvari naročilo
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-3.5 py-2 text-xs font-semibold text-neutral-700"
                            >
                                <CalendarDays :size="13" /> Rezerviraj termin
                            </button>
                            <span class="mx-1 hidden h-4 w-px bg-neutral-200 sm:inline-block" />
                            <button type="button" class="inline-flex items-center gap-1.5 px-1.5 py-2 text-xs font-medium text-neutral-400 hover:text-neutral-600">
                                <StickyNote :size="13" /> Dodaj opombo
                            </button>
                            <button type="button" class="inline-flex items-center gap-1.5 px-1.5 py-2 text-xs font-medium text-neutral-400 hover:text-neutral-600">
                                <Bell :size="13" /> Nastavi opomnik
                            </button>
                        </div>

                        <Transition
                            enter-active-class="transition duration-200 ease-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition duration-200 ease-in"
                            leave-to-class="opacity-0"
                        >
                            <div v-if="showPopup" class="absolute inset-0 z-10 flex items-center justify-center bg-neutral-900/30 backdrop-blur-[1px]">
                                <Transition
                                    appear
                                    enter-active-class="transition duration-250 ease-out"
                                    enter-from-class="translate-y-2 scale-95 opacity-0"
                                >
                                    <div class="w-72 rounded-xl bg-white p-5 shadow-2xl ring-1 ring-black/5">
                                        <p class="text-sm font-semibold text-neutral-900">Novo naročilo</p>

                                        <div class="mt-3 space-y-2">
                                            <div class="flex items-center justify-between rounded-lg border border-neutral-200 px-3 py-2 text-xs">
                                                <span class="text-neutral-400">Izdelek</span>
                                                <span class="font-medium text-neutral-900">Rojstnodnevna torta</span>
                                            </div>
                                            <div class="flex items-center justify-between rounded-lg border border-neutral-200 px-3 py-2 text-xs">
                                                <span class="text-neutral-400">Rok</span>
                                                <span class="font-medium text-neutral-900">30. avgust</span>
                                            </div>
                                            <div class="flex items-center justify-between rounded-lg border border-neutral-200 px-3 py-2 text-xs">
                                                <span class="text-neutral-400">Cena</span>
                                                <Transition
                                                    enter-active-class="transition duration-200 ease-out"
                                                    enter-from-class="translate-y-1 opacity-0"
                                                    mode="out-in"
                                                >
                                                    <span v-if="popupFilled" key="filled" class="font-medium text-neutral-900">85 €</span>
                                                    <span v-else key="empty" class="text-neutral-300">—</span>
                                                </Transition>
                                            </div>
                                            <div class="flex items-center justify-between rounded-lg border border-neutral-200 px-3 py-2 text-xs">
                                                <span class="text-neutral-400">Ara</span>
                                                <Transition
                                                    enter-active-class="transition duration-200 ease-out"
                                                    enter-from-class="translate-y-1 opacity-0"
                                                    mode="out-in"
                                                >
                                                    <span v-if="popupFilled" key="filled" class="font-medium text-neutral-900">20 €</span>
                                                    <span v-else key="empty" class="text-neutral-300">—</span>
                                                </Transition>
                                            </div>
                                        </div>

                                        <button
                                            type="button"
                                            class="mt-4 w-full rounded-md bg-[var(--color-accent-500)] py-2 text-xs font-semibold text-white transition-transform duration-150"
                                            :class="popupConfirming && 'scale-95'"
                                        >
                                            Ustvari naročilo
                                        </button>
                                    </div>
                                </Transition>
                            </div>
                        </Transition>
                    </div>
                </div>
            </Reveal>
        </section>

        <!-- PAIN: "Se ti sliši znano?" -->
        <section class="border-t border-neutral-100 bg-neutral-50 py-20 sm:py-28">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <Reveal>
                    <h2 class="text-center text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                        Manj kaosa, več pregleda.
                    </h2>
                </Reveal>

                <Reveal :delay="60">
                    <p class="mx-auto mt-4 max-w-md text-center text-[15px] text-neutral-500">
                        Dogovori, roki, plačila in opombe se hitro razpršijo med zasebna sporočila, zapiske,
                        koledarje in razne aplikacije.
                    </p>
                </Reveal>

                <div class="mx-auto mt-14 grid max-w-5xl grid-cols-1 gap-x-8 gap-y-10 lg:grid-cols-3">
                    <Reveal v-for="(category, ci) in painCategories" :key="category.label" :delay="ci * 80" :class="category.offset">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-neutral-400 uppercase">{{ category.label }}</p>
                            <div class="mt-4 space-y-3">
                                <div
                                    v-for="(fragment, fi) in category.items"
                                    :key="fragment.text"
                                    class="rounded-xl border border-neutral-200 bg-white px-5 py-4 shadow-sm shadow-neutral-900/[0.04] transition hover:-translate-y-0.5 hover:shadow-md"
                                    :class="[fragment.rotate, fi === 1 && 'sm:ml-4']"
                                >
                                    <span class="inline-block rounded-md px-1.5 py-0.5 text-[10px] font-semibold tracking-wide uppercase" :class="fragment.accent">
                                        {{ fragment.tag }}
                                    </span>
                                    <p class="mt-2 text-[15px] leading-snug text-neutral-800">{{ fragment.text }}</p>
                                </div>
                            </div>
                        </div>
                    </Reveal>
                </div>

                <!-- Sources: where the scattered information is actually coming from -->
                <Reveal :delay="120">
                    <div class="mx-auto mt-10 flex max-w-lg flex-wrap items-center justify-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-neutral-200 bg-white px-2.5 py-1 text-[11px] font-medium text-neutral-500 shadow-sm shadow-neutral-900/[0.03]">
                            <ChannelIcon type="instagram" size="sm" /> Instagram
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-neutral-200 bg-white px-2.5 py-1 text-[11px] font-medium text-neutral-500 shadow-sm shadow-neutral-900/[0.03]">
                            <ChannelIcon type="facebook_messenger" size="sm" /> Facebook
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-neutral-200 bg-white px-2.5 py-1 text-[11px] font-medium text-neutral-500 shadow-sm shadow-neutral-900/[0.03]">
                            <StickyNote :size="11" class="text-neutral-400" /> Zapiski
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-neutral-200 bg-white px-2.5 py-1 text-[11px] font-medium text-neutral-500 shadow-sm shadow-neutral-900/[0.03]">
                            <CalendarDays :size="11" class="text-neutral-400" /> Koledar
                        </span>
                    </div>
                </Reveal>

                <Reveal :delay="200">
                    <div class="mx-auto mt-10 max-w-2xl text-center">
                        <ArrowDown :size="28" class="mx-auto mb-4 text-[var(--color-accent-500)]" />
                        <p class="text-2xl font-semibold text-neutral-900">Vse v eni aplikaciji.</p>
                        <p class="mx-auto mt-2 max-w-md text-sm text-neutral-500">
                            Sprejemaj in odgovarjaj na sporočila, ustvari naročila, termine in spremljaj analitiko.
                        </p>
                    </div>
                </Reveal>

                <!-- Central hub + callouts: one record instantly explains itself -->
                <div ref="payoffEl" class="mx-auto mt-10">
                    <!-- Desktop: clean 3-column grid — callouts left/right, card centered -->
                    <div class="mx-auto hidden max-w-6xl grid-cols-[300px_1fr_300px] items-stretch gap-6 lg:grid">
                        <!-- left column -->
                        <div class="flex h-full flex-col gap-4">
                            <!-- Stranka — mini customer summary card -->
                            <Reveal :delay="0">
                                <div
                                    class="rounded-xl border border-neutral-200 bg-white p-5 text-left shadow-sm shadow-neutral-900/[0.05] transition-all duration-300 ease-out"
                                    :class="showCalloutCustomer ? 'translate-y-0 scale-100 opacity-100' : 'translate-y-2 scale-95 opacity-0'"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <CustomerAvatar :size="32" />
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-neutral-900">Nina Kovač</p>
                                            <p class="text-[10px] font-medium tracking-wide text-neutral-400 uppercase">Stranka</p>
                                        </div>
                                    </div>
                                    <div class="mt-3 space-y-1 border-t border-neutral-100 pt-2.5 text-xs text-neutral-500">
                                        <p class="flex items-center gap-1.5"><Mail :size="11" /> nina@example.com</p>
                                        <p class="flex items-center gap-1.5"><Phone :size="11" /> +386 XX XXX XXX</p>
                                    </div>
                                    <div class="mt-3 space-y-1 border-t border-neutral-100 pt-2.5 text-xs">
                                        <div class="flex items-center justify-between">
                                            <span class="text-neutral-400">Naročila</span>
                                            <span class="font-medium text-neutral-800">3</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-neutral-400">Skupaj</span>
                                            <span class="font-medium text-neutral-800">248 €</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-neutral-400">Zadnje</span>
                                            <span class="font-medium text-neutral-800">9. jul.</span>
                                        </div>
                                    </div>
                                </div>
                            </Reveal>

                            <!-- Pogovor — miniature real DM preview -->
                            <Reveal :delay="0" class="flex-1">
                                <div
                                    class="flex h-full flex-col justify-center rounded-xl border border-neutral-200 bg-white p-4 text-left shadow-sm shadow-neutral-900/[0.05] transition-all duration-300 ease-out"
                                    :class="showCalloutConversation ? 'translate-y-0 scale-100 opacity-100' : 'translate-y-2 scale-95 opacity-0'"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-1.5 text-[10px] font-semibold tracking-wide text-neutral-400 uppercase">
                                            <ChannelIcon type="instagram" size="sm" /> Instagram
                                        </div>
                                        <span class="flex items-center gap-1 text-[10px] font-medium text-emerald-600">
                                            <Check :size="10" /> Sinhronizirano
                                        </span>
                                    </div>
                                    <div class="mt-2.5">
                                        <div class="flex items-start gap-1.5">
                                            <CustomerAvatar :size="20" />
                                            <div class="max-w-[180px] rounded-xl rounded-tl-sm bg-neutral-100 px-2.5 py-1.5 text-xs text-neutral-700">
                                                Za hčerkin rojstni dan 😊
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </Reveal>
                        </div>

                        <!-- center column: card -->
                        <div class="mx-auto flex h-full w-full max-w-md">
                            <div class="flex w-full flex-col overflow-hidden rounded-2xl border border-[var(--color-accent-200)] bg-white shadow-lg shadow-[var(--color-accent-500)]/10 ring-1 ring-[var(--color-accent-500)]/5">
                                <div class="flex items-center gap-2 border-b border-[var(--color-accent-100)] bg-[var(--color-accent-50)] px-5 py-3">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-[var(--color-accent-500)] text-[11px] font-bold text-white">B</span>
                                    <p class="text-xs font-semibold tracking-wide text-[var(--color-accent-700)] uppercase">Beležka</p>
                                </div>
                                <div class="flex flex-1 flex-col justify-center space-y-2.5 px-5 py-4 text-sm">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-neutral-400">Stranka</span>
                                        <span class="font-medium text-neutral-900">Nina Kovač</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-neutral-400">Kanal</span>
                                        <span class="inline-flex items-center gap-1.5 font-medium text-neutral-900">
                                            <ChannelIcon type="instagram" size="sm" /> Instagram
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-neutral-400">Naročilo</span>
                                        <span class="font-medium text-neutral-900">Rojstnodnevna torta</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-neutral-400">Status</span>
                                        <Badge color="#6A3CCB" bg="#EBE5FD">V pripravi</Badge>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-neutral-400">Rok</span>
                                        <span class="font-medium text-neutral-900">30. avgust</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-neutral-400">Cena</span>
                                        <span class="font-medium text-neutral-900">85 €</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-neutral-400">Plačilo</span>
                                        <Badge :color="depositPaid.color" :bg="depositPaid.bg">Ara plačana (20 €)</Badge>
                                    </div>
                                    <div class="flex items-start justify-between gap-3 border-t border-neutral-100 pt-2.5">
                                        <span class="shrink-0 text-neutral-400">Opomba</span>
                                        <span class="text-right font-medium text-neutral-700">Prevzem ob 8:00</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- right column -->
                        <div class="flex h-full flex-col gap-4">
                            <!-- Status — operational status card with a one-time transition -->
                            <Reveal :delay="0">
                                <div
                                    class="rounded-xl border border-neutral-200 bg-white p-5 text-left shadow-sm shadow-neutral-900/[0.05] transition-all duration-300 ease-out"
                                    :class="showCalloutStatus ? 'translate-y-0 scale-100 opacity-100' : 'translate-y-2 scale-95 opacity-0'"
                                >
                                    <p class="text-[10px] font-semibold tracking-wide text-neutral-400 uppercase">Naročilo</p>
                                    <p class="mt-1 text-sm font-semibold text-neutral-900">Rojstnodnevna torta</p>
                                    <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                                        <Badge
                                            class="transition-opacity duration-300"
                                            :class="calloutStatusStage === 0 ? 'opacity-100' : 'opacity-50'"
                                            color="#0E7490"
                                            bg="#E0F7FA"
                                        >
                                            Potrjeno
                                        </Badge>
                                        <Badge
                                            class="transition-opacity duration-300"
                                            :class="calloutStatusStage === 1 ? 'opacity-100' : 'opacity-50'"
                                            color="#6A3CCB"
                                            bg="#EBE5FD"
                                        >
                                            V pripravi
                                        </Badge>
                                        <Badge
                                            class="transition-opacity duration-300"
                                            :class="calloutStatusStage === 2 ? 'opacity-100' : 'opacity-50'"
                                            color="#0F766E"
                                            bg="#DBF6EF"
                                        >
                                            Pripravljeno
                                        </Badge>
                                    </div>
                                    <p class="mt-2 border-t border-neutral-100 pt-2 text-xs text-neutral-500">Prevzem: 30. avgust</p>
                                    <Transition
                                        enter-active-class="transition-all duration-300 ease-out"
                                        enter-from-class="mt-0 max-h-0 opacity-0"
                                        leave-active-class="transition-all duration-150 ease-in"
                                        leave-to-class="mt-0 max-h-0 opacity-0"
                                    >
                                        <button
                                            v-if="calloutStatusStage === 2"
                                            type="button"
                                            class="mt-2.5 inline-flex w-full items-center justify-center gap-1.5 overflow-hidden rounded-md border border-neutral-200 px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-neutral-50"
                                        >
                                            <Bell :size="12" /> Obvesti stranko
                                        </button>
                                    </Transition>
                                </div>
                            </Reveal>

                            <!-- Plačilo — payment status card -->
                            <Reveal :delay="0" class="flex-1">
                                <div
                                    class="flex h-full flex-col justify-center rounded-xl border border-neutral-200 bg-white p-5 text-left shadow-sm shadow-neutral-900/[0.05] transition-all duration-300 ease-out"
                                    :class="showCalloutNext ? 'translate-y-0 scale-100 opacity-100' : 'translate-y-2 scale-95 opacity-0'"
                                >
                                    <p class="text-[10px] font-semibold tracking-wide text-neutral-400 uppercase">Plačilo</p>
                                    <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                                        <Badge
                                            class="transition-opacity duration-300"
                                            :class="calloutPaymentStage === 0 ? 'opacity-100' : 'opacity-50'"
                                            color="#4B5563"
                                            bg="#F1F2F4"
                                        >
                                            Naročeno
                                        </Badge>
                                        <Badge
                                            class="transition-opacity duration-300"
                                            :class="calloutPaymentStage === 1 ? 'opacity-100' : 'opacity-50'"
                                            color="#0E7490"
                                            bg="#E0F7FA"
                                        >
                                            Ara plačana
                                        </Badge>
                                        <Badge
                                            class="transition-opacity duration-300"
                                            :class="calloutPaymentStage === 2 ? 'opacity-100' : 'opacity-50'"
                                            color="#15803D"
                                            bg="#DCFCE7"
                                        >
                                            Plačano
                                        </Badge>
                                    </div>
                                    <div class="mt-2 space-y-1 border-t border-neutral-100 pt-2 text-xs">
                                        <div class="flex items-center justify-between">
                                            <span class="text-neutral-500">Cena</span>
                                            <span class="font-semibold text-neutral-900">85 €</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-neutral-500">Ara</span>
                                            <span class="font-semibold text-neutral-900">20 €</span>
                                        </div>
                                    </div>
                                </div>
                            </Reveal>
                        </div>
                    </div>

                    <!-- Mobile / tablet: card first, then callouts stacked in a clean 2x2 grid -->
                    <div class="mx-auto max-w-sm lg:hidden">
                        <div class="overflow-hidden rounded-2xl border border-[var(--color-accent-200)] bg-white shadow-lg shadow-[var(--color-accent-500)]/10 ring-1 ring-[var(--color-accent-500)]/5">
                            <div class="flex items-center gap-2 border-b border-[var(--color-accent-100)] bg-[var(--color-accent-50)] px-5 py-3">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-[var(--color-accent-500)] text-[11px] font-bold text-white">B</span>
                                <p class="text-xs font-semibold tracking-wide text-[var(--color-accent-700)] uppercase">Beležka</p>
                            </div>
                            <div class="space-y-2.5 px-5 py-4 text-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-neutral-400">Stranka</span>
                                    <span class="font-medium text-neutral-900">Nina Kovač</span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-neutral-400">Kanal</span>
                                    <span class="inline-flex items-center gap-1.5 font-medium text-neutral-900">
                                        <ChannelIcon type="instagram" size="sm" /> Instagram
                                    </span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-neutral-400">Naročilo</span>
                                    <span class="font-medium text-neutral-900">Rojstnodnevna torta</span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-neutral-400">Status</span>
                                    <Badge color="#6A3CCB" bg="#EBE5FD">V pripravi</Badge>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-neutral-400">Rok</span>
                                    <span class="font-medium text-neutral-900">30. avgust</span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-neutral-400">Plačilo</span>
                                    <Badge :color="depositPaid.color" :bg="depositPaid.bg">Ara plačana (20 €)</Badge>
                                </div>
                                <div class="flex items-start justify-between gap-3 border-t border-neutral-100 pt-2.5">
                                    <span class="shrink-0 text-neutral-400">Opomba</span>
                                    <span class="text-right font-medium text-neutral-700">Prevzem ob 8:00</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <div class="rounded-xl border border-neutral-200 bg-white p-3.5 text-left shadow-sm shadow-neutral-900/[0.05]">
                                <div class="flex items-center gap-2">
                                    <CustomerAvatar :size="26" />
                                    <p class="truncate text-xs font-semibold text-neutral-900">Nina Kovač</p>
                                </div>
                                <div class="mt-2 space-y-0.5 border-t border-neutral-100 pt-2 text-[11px]">
                                    <div class="flex items-center justify-between">
                                        <span class="text-neutral-400">Naročila</span>
                                        <span class="font-medium text-neutral-800">3</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-neutral-400">Skupaj</span>
                                        <span class="font-medium text-neutral-800">248 €</span>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-xl border border-neutral-200 bg-white p-3.5 text-left shadow-sm shadow-neutral-900/[0.05]">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5 text-[10px] font-semibold tracking-wide text-neutral-400 uppercase">
                                        <ChannelIcon type="instagram" size="sm" /> Instagram
                                    </div>
                                    <span class="flex items-center gap-1 text-[10px] font-medium text-emerald-600">
                                        <Check :size="10" /> Sinhronizirano
                                    </span>
                                </div>
                                <div class="mt-2 flex items-start gap-1.5">
                                    <CustomerAvatar :size="18" />
                                    <div class="max-w-[140px] rounded-xl rounded-tl-sm bg-neutral-100 px-2 py-1 text-[11px] text-neutral-700">
                                        Za hčerkin rojstni dan 😊
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-xl border border-neutral-200 bg-white p-3.5 text-left shadow-sm shadow-neutral-900/[0.05]">
                                <p class="text-xs font-semibold text-neutral-900">Rojstnodnevna torta</p>
                                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                    <Badge class="opacity-50" color="#0E7490" bg="#E0F7FA">Potrjeno</Badge>
                                    <Badge class="opacity-50" color="#6A3CCB" bg="#EBE5FD">V pripravi</Badge>
                                    <Badge color="#0F766E" bg="#DBF6EF">Pripravljeno</Badge>
                                </div>
                                <p class="mt-1.5 text-[11px] text-neutral-500">Prevzem: 30. avgust</p>
                                <button
                                    type="button"
                                    class="mt-2 inline-flex w-full items-center justify-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-[11px] font-semibold text-neutral-700"
                                >
                                    <Bell :size="11" /> Obvesti stranko
                                </button>
                            </div>
                            <div class="rounded-xl border border-neutral-200 bg-white p-3.5 text-left shadow-sm shadow-neutral-900/[0.05]">
                                <p class="text-[10px] font-semibold tracking-wide text-neutral-400 uppercase">Plačilo</p>
                                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                    <Badge class="opacity-50" color="#4B5563" bg="#F1F2F4">Naročeno</Badge>
                                    <Badge class="opacity-50" color="#0E7490" bg="#E0F7FA">Ara plačana</Badge>
                                    <Badge color="#15803D" bg="#DCFCE7">Plačano</Badge>
                                </div>
                                <div class="mt-1.5 space-y-0.5 text-[11px]">
                                    <div class="flex items-center justify-between">
                                        <span class="text-neutral-500">Cena</span>
                                        <span class="font-semibold text-neutral-900">85 €</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-neutral-500">Ara</span>
                                        <span class="font-semibold text-neutral-900">20 €</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
                                    class="mt-2 inline-flex items-center gap-1.5 rounded-md bg-[var(--color-accent-500)] px-3.5 py-2 text-xs font-semibold text-white"
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

<style scoped>
.typing-dot {
    animation: typing-bounce 1.1s ease-in-out infinite;
}

@keyframes typing-bounce {
    0%,
    60%,
    100% {
        transform: translateY(0);
        opacity: 0.5;
    }
    30% {
        transform: translateY(-2px);
        opacity: 1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .typing-dot {
        animation: none;
    }
}
</style>

