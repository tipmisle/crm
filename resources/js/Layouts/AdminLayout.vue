<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutDashboard, Building2, Users, Plug, ShieldCheck, ScrollText, ArrowLeft } from 'lucide-vue-next';
import type { PageProps } from '@/types';
import SupportModeBanner from '@/Components/SupportModeBanner.vue';

const page = usePage<PageProps>();

const nav = [
    { name: 'Nadzorna plošča', href: () => route('admin.dashboard'), icon: LayoutDashboard, current: () => route().current('admin.dashboard') },
    { name: 'Delovni prostori', href: () => route('admin.workspaces.index'), icon: Building2, current: () => route().current('admin.workspaces.*') },
    { name: 'Uporabniki', href: () => route('admin.users.index'), icon: Users, current: () => route().current('admin.users.*') },
    { name: 'Integracije', href: () => route('admin.integrations.index'), icon: Plug, current: () => route().current('admin.integrations.*') },
    { name: 'Dnevnik revizije', href: () => route('admin.audit-log.index'), icon: ScrollText, current: () => route().current('admin.audit-log.*') },
];

const supportSession = computed(() => page.props.activeSupportSession);
</script>

<template>
    <div class="flex h-screen flex-col overflow-hidden bg-neutral-50">
        <SupportModeBanner v-if="supportSession" :session="supportSession" />

        <div class="flex min-h-0 flex-1">
            <aside class="hidden w-56 shrink-0 flex-col border-r border-neutral-200 bg-white md:flex">
                <div class="flex items-center gap-2 border-b border-neutral-200 px-4 py-4">
                    <ShieldCheck :size="18" class="text-[var(--color-accent-500)]" />
                    <span class="text-sm font-semibold text-neutral-900">Beležka admin</span>
                </div>

                <nav class="flex-1 space-y-0.5 px-2 py-3">
                    <Link
                        v-for="item in nav"
                        :key="item.name"
                        :href="item.href()"
                        class="flex items-center gap-2.5 rounded-md px-3 py-1.5 text-sm font-medium transition"
                        :class="item.current() ? 'bg-neutral-900 text-white' : 'text-neutral-600 hover:bg-neutral-100'"
                    >
                        <component :is="item.icon" :size="15" />
                        {{ item.name }}
                    </Link>
                </nav>

                <div class="border-t border-neutral-200 p-3">
                    <Link :href="route('dashboard')" class="flex items-center gap-2 rounded-md px-3 py-1.5 text-xs font-medium text-neutral-500 hover:bg-neutral-100">
                        <ArrowLeft :size="13" />
                        Nazaj v aplikacijo
                    </Link>
                </div>
            </aside>

            <main class="flex-1 overflow-y-auto">
                <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
