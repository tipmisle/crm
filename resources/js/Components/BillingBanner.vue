<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CreditCard } from 'lucide-vue-next';
import type { SubscriptionAccessState } from '@/types';

const props = defineProps<{
    status: SubscriptionAccessState;
}>();

const copy = {
    past_due: 'Plačila ni bilo mogoče izvesti. Posodobi način plačila.',
    canceling: 'Naročnina bo kmalu potekla.',
} as const;

const message = props.status === 'past_due' ? copy.past_due : copy.canceling;
const bg = props.status === 'past_due' ? 'bg-red-600' : 'bg-amber-500';
</script>

<template>
    <div
        class="flex shrink-0 flex-wrap items-center justify-center gap-x-3 gap-y-1 px-4 py-2 text-center text-xs font-medium text-white sm:text-sm"
        :class="bg"
    >
        <span class="inline-flex items-center gap-1.5">
            <CreditCard :size="14" class="shrink-0" />
            {{ message }}
        </span>
        <Link
            :href="route('settings.billing.edit')"
            class="shrink-0 rounded-md bg-white/15 px-2.5 py-1 font-semibold whitespace-nowrap text-white transition hover:bg-white/25"
        >
            Uredi naročnino
        </Link>
    </div>
</template>
