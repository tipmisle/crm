<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import {
    LayoutDashboard,
    Inbox,
    Package,
    CalendarDays,
    Users,
    Settings,
    Search,
    LogOut,
    ChevronsUpDown,
    X,
    Sparkles,
    Menu,
    BarChart3,
    ShieldCheck,
    FileText,
} from 'lucide-vue-next';
import type { PageProps } from '@/types';
import Avatar from '@/Components/Avatar.vue';
import CommandPalette from '@/Components/CommandPalette.vue';
import DemoBanner from '@/Components/DemoBanner.vue';
import BillingBanner from '@/Components/BillingBanner.vue';

const page = usePage<PageProps>();

const toast = ref<{ type: 'success' | 'error'; message: string } | null>(null);
let toastTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => [page.props.flash?.success, page.props.flash?.error],
    ([success, error]) => {
        if (!success && !error) return;

        toast.value = success ? { type: 'success', message: success } : { type: 'error', message: error as string };

        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(() => (toast.value = null), 6000);
    },
    { immediate: true },
);

const nav = computed(() => [
    { name: 'Danes', href: () => route('dashboard'), icon: LayoutDashboard, current: () => route().current('dashboard') },
    { name: 'Prejeta pošta', href: () => route('inbox.index'), icon: Inbox, current: () => route().current('inbox.*') },
    ...(page.props.workspace?.orders_enabled ?? true
        ? [{ name: 'Naročila', href: () => route('orders.index'), icon: Package, current: () => route().current('orders.*') }]
        : []),
    ...(page.props.workspace?.appointments_enabled ?? false
        ? [{ name: 'Termini', href: () => route('appointments.index'), icon: CalendarDays, current: () => route().current('appointments.*') }]
        : []),
    ...(page.props.workspace?.orders_enabled || page.props.workspace?.appointments_enabled
        ? [{ name: 'Ponudba', href: () => route('catalog.index'), icon: Sparkles, current: () => route().current('catalog.*') }]
        : []),
    { name: 'Stranke', href: () => route('customers.index'), icon: Users, current: () => route().current('customers.*') },
    ...(page.props.workspace?.orders_enabled || page.props.workspace?.appointments_enabled
        ? [{ name: 'Dokumenti', href: () => route('documents.index'), icon: FileText, current: () => route().current('documents.index') }]
        : []),
    { name: 'Analitika', href: () => route('analytics.index'), icon: BarChart3, current: () => route().current('analytics.*') },
    { name: 'Nastavitve', href: () => route('settings.edit'), icon: Settings, current: () => route().current('settings.*') },
]);

const searchOpen = ref(false);
const userMenuOpen = ref(false);
const sidebarOpen = ref(false);

const unreadInboxCount = computed(() => page.props.unreadInboxCount ?? 0);

// Keeps the sidebar badge live across the whole app (not just the Inbox
// page itself) — same private, workspace-scoped channel used there.
const workspaceId = computed(() => page.props.workspace?.id);

useEcho(`workspace.${workspaceId.value}.inbox`, 'message.received', () => {
    router.reload({ only: ['unreadInboxCount'] });
});

// The sidebar is an off-canvas drawer below the lg breakpoint — close it
// whenever a navigation completes so it doesn't stay open over the new page.
// AppLayout remounts per page (it's not a persistent Inertia layout), so the
// listener must be torn down or it'd accumulate on every navigation.
const stopNavigateListener = router.on('navigate', () => {
    sidebarOpen.value = false;
});
onUnmounted(stopNavigateListener);
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-neutral-50">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div v-if="sidebarOpen" class="fixed inset-0 z-30 bg-[var(--color-ink-900)]/50 lg:hidden" @click="sidebarOpen = false" />
        </Transition>

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-60 shrink-0 -translate-x-full flex-col bg-[var(--color-ink-950)] text-neutral-300 transition-transform duration-200 ease-out lg:static lg:translate-x-0"
            :class="sidebarOpen && 'translate-x-0'"
        >
            <div class="flex items-center gap-2 px-5 py-5">
                <img src="/images/logo-white.png" alt="Beležka" class="h-6 w-auto" />
                <button
                    type="button"
                    class="rounded-md p-1 text-neutral-400 hover:bg-white/10 hover:text-white lg:hidden"
                    title="Zapri meni"
                    aria-label="Zapri meni"
                    @click="sidebarOpen = false"
                >
                    <X :size="16" />
                </button>
            </div>

            <nav class="flex-1 space-y-0.5 px-3">
                <Link
                    v-for="item in nav"
                    :key="item.name"
                    :href="item.href()"
                    class="group flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition"
                    :class="
                        item.current()
                            ? 'bg-white/10 text-white'
                            : 'text-neutral-400 hover:bg-white/5 hover:text-neutral-100'
                    "
                >
                    <component :is="item.icon" :size="17" />
                    <span class="flex-1">{{ item.name }}</span>
                    <span
                        v-if="item.name === 'Prejeta pošta' && unreadInboxCount > 0"
                        class="flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white"
                    >
                        {{ unreadInboxCount > 99 ? '99+' : unreadInboxCount }}
                    </span>
                </Link>
            </nav>

            <div class="border-t border-white/10 p-3">
                <button
                    type="button"
                    class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-left text-sm text-neutral-300 hover:bg-white/5"
                    @click="userMenuOpen = !userMenuOpen"
                >
                    <Avatar :name="page.props.auth.user.name" :src="page.props.auth.user.avatar_url" size="sm" />
                    <span class="flex-1 truncate">{{ page.props.auth.user.name }}</span>
                    <ChevronsUpDown :size="14" class="text-neutral-500" />
                </button>

                <div v-if="userMenuOpen" class="mt-1 space-y-0.5 rounded-md bg-white/5 p-1">
                    <Link
                        v-if="page.props.auth.user.is_platform_admin"
                        :href="route('admin.dashboard')"
                        class="flex items-center gap-2 rounded-md px-2.5 py-1.5 text-sm text-neutral-300 hover:bg-white/10"
                    >
                        <ShieldCheck :size="14" />
                        Admin
                    </Link>
                    <Link
                        :href="route('profile.edit')"
                        class="block rounded-md px-2.5 py-1.5 text-sm text-neutral-300 hover:bg-white/10"
                    >
                        Profil
                    </Link>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-sm text-neutral-300 hover:bg-white/10"
                    >
                        <LogOut :size="14" />
                        Odjava
                    </Link>
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <DemoBanner v-if="page.props.workspace?.is_demo" />
            <BillingBanner
                v-else-if="page.props.billing && ['past_due', 'canceling'].includes(page.props.billing.status)"
                :status="page.props.billing.status"
            />

            <header
                class="flex h-14 shrink-0 items-center justify-between gap-2 border-b border-neutral-200 bg-white px-4 sm:px-6"
            >
                <div class="flex min-w-0 items-center gap-2">
                    <button
                        type="button"
                        class="-ml-1 shrink-0 rounded-md p-1.5 text-neutral-500 hover:bg-neutral-100 lg:hidden"
                        title="Odpri meni"
                        aria-label="Odpri meni"
                        @click="sidebarOpen = true"
                    >
                        <Menu :size="18" />
                    </button>
                    <div class="min-w-0">
                        <slot name="header" />
                    </div>
                </div>

                <button
                    type="button"
                    class="flex shrink-0 items-center gap-2 rounded-md border border-neutral-200 bg-neutral-50 px-2.5 py-1.5 text-sm text-neutral-500 hover:bg-neutral-100 sm:px-3"
                    title="Iskanje"
                    aria-label="Iskanje"
                    @click="searchOpen = true"
                >
                    <Search :size="14" />
                    <span class="hidden sm:inline">Iskanje</span>
                    <kbd class="ml-2 hidden rounded border border-neutral-300 bg-white px-1.5 py-0.5 text-[10px] font-medium text-neutral-400 md:inline">
                        ⌘K
                    </kbd>
                </button>
            </header>

            <main class="flex-1 overflow-y-auto [scrollbar-gutter:stable]">
                <slot />
            </main>
        </div>

        <CommandPalette v-model:open="searchOpen" />

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 translate-y-1"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="toast"
                class="fixed right-4 top-4 z-50 flex max-w-sm items-start gap-2 rounded-lg border px-4 py-3 text-sm shadow-lg"
                :class="
                    toast.type === 'success'
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                        : 'border-rose-200 bg-rose-50 text-rose-800'
                "
            >
                <span class="flex-1">{{ toast.message }}</span>
                <button
                    type="button"
                    class="text-current/70 hover:text-current"
                    title="Zapri obvestilo"
                    aria-label="Zapri obvestilo"
                    @click="toast = null"
                >
                    <X :size="14" />
                </button>
            </div>
        </Transition>
    </div>
</template>
