<script setup lang="ts">
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ShieldAlert } from 'lucide-vue-next';
import type { ActiveSupportSession } from '@/types';

const props = defineProps<{ session: ActiveSupportSession }>();

const expiresLabel = computed(() =>
    new Date(props.session.expires_at).toLocaleTimeString('sl-SI', { hour: '2-digit', minute: '2-digit' }),
);

function leave() {
    router.post(route('admin.support.end'), {}, { onSuccess: () => router.visit(route('admin.workspaces.index')) });
}
</script>

<template>
    <div class="flex shrink-0 flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-amber-600 px-4 py-2 text-center text-xs font-semibold text-white sm:text-sm">
        <span class="inline-flex items-center gap-1.5">
            <ShieldAlert :size="14" class="shrink-0" />
            Način podpore · {{ session.workspace.name }} · dostop poteče ob {{ expiresLabel }}
        </span>
        <Link
            :href="route('admin.workspaces.support.browse', session.workspace.id)"
            class="shrink-0 rounded-md bg-white/15 px-2.5 py-1 font-semibold whitespace-nowrap text-white transition hover:bg-white/25"
        >
            Prebrskaj vsebino
        </Link>
        <button
            type="button"
            class="shrink-0 rounded-md bg-white/15 px-2.5 py-1 font-semibold whitespace-nowrap text-white transition hover:bg-white/25"
            @click="leave"
        >
            Zapusti dostop
        </button>
    </div>
</template>
