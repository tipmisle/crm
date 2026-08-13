<script setup lang="ts">
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    Inbox,
    Package,
    Users,
    Settings,
    Search,
    LogOut,
    ChevronsUpDown,
} from 'lucide-vue-next';
import type { PageProps } from '@/types';
import Avatar from '@/Components/Avatar.vue';
import CommandPalette from '@/Components/CommandPalette.vue';

const page = usePage<PageProps>();

const nav = [
    { name: 'Today', href: () => route('dashboard'), icon: LayoutDashboard, current: () => route().current('dashboard') },
    { name: 'Inbox', href: () => route('inbox.index'), icon: Inbox, current: () => route().current('inbox.*') },
    { name: 'Orders', href: () => route('orders.index'), icon: Package, current: () => route().current('orders.*') },
    { name: 'Customers', href: () => route('customers.index'), icon: Users, current: () => route().current('customers.*') },
    { name: 'Settings', href: () => route('settings.edit'), icon: Settings, current: () => route().current('settings.*') },
];

const searchOpen = ref(false);
const userMenuOpen = ref(false);
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-neutral-50">
        <aside class="flex w-60 shrink-0 flex-col bg-[var(--color-ink-950)] text-neutral-300">
            <div class="flex items-center gap-2 px-5 py-5">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-[var(--color-accent-500)] text-sm font-bold text-white"
                >
                    B
                </div>
                <div class="truncate text-sm font-semibold text-white">
                    {{ page.props.workspace?.name ?? 'Workspace' }}
                </div>
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
                    {{ item.name }}
                </Link>
            </nav>

            <div class="border-t border-white/10 p-3">
                <button
                    type="button"
                    class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-left text-sm text-neutral-300 hover:bg-white/5"
                    @click="userMenuOpen = !userMenuOpen"
                >
                    <Avatar :name="page.props.auth.user.name" size="sm" />
                    <span class="flex-1 truncate">{{ page.props.auth.user.name }}</span>
                    <ChevronsUpDown :size="14" class="text-neutral-500" />
                </button>

                <div v-if="userMenuOpen" class="mt-1 space-y-0.5 rounded-md bg-white/5 p-1">
                    <Link
                        :href="route('profile.edit')"
                        class="block rounded-md px-2.5 py-1.5 text-sm text-neutral-300 hover:bg-white/10"
                    >
                        Profile
                    </Link>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-sm text-neutral-300 hover:bg-white/10"
                    >
                        <LogOut :size="14" />
                        Log out
                    </Link>
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header
                class="flex h-14 shrink-0 items-center justify-between border-b border-neutral-200 bg-white px-6"
            >
                <div class="min-w-0">
                    <slot name="header" />
                </div>

                <button
                    type="button"
                    class="flex items-center gap-2 rounded-md border border-neutral-200 bg-neutral-50 px-3 py-1.5 text-sm text-neutral-500 hover:bg-neutral-100"
                    @click="searchOpen = true"
                >
                    <Search :size="14" />
                    <span>Search</span>
                    <kbd class="ml-2 rounded border border-neutral-300 bg-white px-1.5 py-0.5 text-[10px] font-medium text-neutral-400">⌘K</kbd>
                </button>
            </header>

            <main class="flex-1 overflow-y-auto">
                <slot />
            </main>
        </div>

        <CommandPalette v-model:open="searchOpen" />
    </div>
</template>
