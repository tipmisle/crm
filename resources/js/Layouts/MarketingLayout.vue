<script setup lang="ts">
import { ref, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Menu, X, Play } from 'lucide-vue-next';
import { trackDemoClick } from '@/lib/analytics';

// Absolute (/#…) rather than bare (#…) fragments — these links must also
// work correctly from pages other than the homepage (legal pages, /demo),
// where a bare fragment would just append the hash to the current URL
// with nothing on the page to scroll to.
const navigation = [
    { label: 'Kako deluje', href: '/#kako-deluje' },
    { label: 'Funkcije', href: '/#funkcije' },
    { label: 'Za koga', href: '/#za-koga' },
    { label: 'Cena', href: '/#cena' },
];

const mobileMenuOpen = ref(false);

const stopNavigateListener = router.on('navigate', () => {
    mobileMenuOpen.value = false;
});
onUnmounted(stopNavigateListener);
</script>

<template>
    <div class="min-h-screen bg-white text-neutral-900">
        <header class="legal-page-chrome sticky top-0 z-40 border-b border-white/10 bg-[var(--color-ink-950)]/95 backdrop-blur-sm">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
                <Link href="/" class="flex items-center gap-2">
                    <img src="/images/logo-white.png" alt="Beležka" class="h-5 w-auto" />
                </Link>

                <nav class="hidden items-center gap-8 lg:flex">
                    <a
                        v-for="item in navigation"
                        :key="item.href"
                        :href="item.href"
                        class="text-sm font-medium text-neutral-300 transition hover:text-white"
                    >
                        {{ item.label }}
                    </a>
                </nav>

                <div class="hidden items-center gap-3 lg:flex">
                    <Link
                        :href="route('demo')"
                        class="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-300 transition hover:text-white"
                        @click="trackDemoClick('header')"
                    >
                        <Play :size="13" />
                        Preizkusi demo
                    </Link>
                    <Link
                        :href="route('login')"
                        class="text-sm font-medium text-neutral-300 transition hover:text-white"
                    >
                        Prijava
                    </Link>
                    <Link
                        :href="route('register')"
                        class="rounded-lg bg-[var(--color-accent-500)] px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-[var(--color-accent-500)]/25 transition hover:bg-[var(--color-accent-600)]"
                    >
                        Začni z Beležko
                    </Link>
                </div>

                <button
                    type="button"
                    class="-mr-1.5 rounded-md p-2 text-neutral-300 hover:bg-white/10 hover:text-white lg:hidden"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <Menu v-if="!mobileMenuOpen" :size="20" />
                    <X v-else :size="20" />
                </button>
            </div>

            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-1"
                leave-active-class="transition duration-100 ease-in"
                leave-to-class="opacity-0"
            >
                <div v-if="mobileMenuOpen" class="border-t border-white/10 bg-[var(--color-ink-950)] px-4 py-4 lg:hidden">
                    <nav class="flex flex-col gap-1">
                        <a
                            v-for="item in navigation"
                            :key="item.href"
                            :href="item.href"
                            class="rounded-md px-2 py-2.5 text-sm font-medium text-neutral-300 hover:bg-white/5 hover:text-white"
                            @click="mobileMenuOpen = false"
                        >
                            {{ item.label }}
                        </a>
                    </nav>
                    <div class="mt-3 flex flex-col gap-2 border-t border-white/10 pt-3">
                        <Link
                            :href="route('demo')"
                            class="inline-flex items-center justify-center gap-1.5 rounded-md px-2 py-2.5 text-center text-sm font-medium text-neutral-300 hover:bg-white/5 hover:text-white"
                            @click="trackDemoClick('header')"
                        >
                            <Play :size="13" />
                            Preizkusi demo
                        </Link>
                        <Link
                            :href="route('login')"
                            class="rounded-md px-2 py-2.5 text-center text-sm font-medium text-neutral-300 hover:bg-white/5 hover:text-white"
                        >
                            Prijava
                        </Link>
                        <Link
                            :href="route('register')"
                            class="rounded-lg bg-[var(--color-accent-500)] px-4 py-2.5 text-center text-sm font-semibold text-white"
                        >
                            Začni z Beležko
                        </Link>
                    </div>
                </div>
            </Transition>
        </header>

        <main>
            <slot />
        </main>

        <footer class="legal-page-chrome border-t border-neutral-200 bg-neutral-50">
            <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
                <div class="grid grid-cols-2 gap-8 sm:grid-cols-4">
                    <div class="col-span-2 sm:col-span-1">
                        <img src="/images/logo-dark.png" alt="Beležka" class="h-5 w-auto" />
                        <p class="mt-3 max-w-xs text-sm text-neutral-500">
                            Sporočila, stranke, naročila in termini — povezani na enem mestu.
                        </p>
                        <p class="mt-1.5 max-w-xs text-xs text-neutral-400">
                            Za podjetja, ki sprejemajo naročila ali rezervacije prek zasebnih sporočil.
                        </p>
                    </div>

                    <div>
                        <h3 class="text-xs font-semibold tracking-wide text-neutral-400 uppercase">Izdelek</h3>
                        <ul class="mt-3 space-y-2.5 text-sm text-neutral-600">
                            <li><a href="/#kako-deluje" class="hover:text-neutral-900">Kako deluje</a></li>
                            <li><a href="/#funkcije" class="hover:text-neutral-900">Funkcije</a></li>
                            <li><a href="/#za-koga" class="hover:text-neutral-900">Za koga</a></li>
                            <li><a href="/#cena" class="hover:text-neutral-900">Cena</a></li>
                            <li>
                                <Link :href="route('demo')" class="hover:text-neutral-900" @click="trackDemoClick('footer')">
                                    Preizkusi demo
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-xs font-semibold tracking-wide text-neutral-400 uppercase">Račun</h3>
                        <ul class="mt-3 space-y-2.5 text-sm text-neutral-600">
                            <li><Link :href="route('login')" class="hover:text-neutral-900">Prijava</Link></li>
                            <li><Link :href="route('register')" class="hover:text-neutral-900">Ustvari račun</Link></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-xs font-semibold tracking-wide text-neutral-400 uppercase">Pravno</h3>
                        <ul class="mt-3 space-y-2.5 text-sm text-neutral-600">
                            <li><Link :href="route('legal.terms')" class="hover:text-neutral-900">Pogoji poslovanja</Link></li>
                            <li><Link :href="route('legal.privacy')" class="hover:text-neutral-900">Politika zasebnosti</Link></li>
                            <li><Link :href="route('legal.cookies')" class="hover:text-neutral-900">Piškotki</Link></li>
                            <li><Link :href="route('legal.dpa')" class="hover:text-neutral-900">Obdelava osebnih podatkov</Link></li>
                            <li><Link :href="route('legal.subprocessors')" class="hover:text-neutral-900">Podobdelovalci</Link></li>
                            <li><Link :href="route('legal.provider')" class="hover:text-neutral-900">Podatki o ponudniku</Link></li>
                        </ul>
                    </div>
                </div>

                <div class="mt-10 border-t border-neutral-200 pt-6 text-xs text-neutral-400">
                    © {{ new Date().getFullYear() }} Beležka. Vse pravice pridržane.
                </div>
            </div>
        </footer>
    </div>
</template>
