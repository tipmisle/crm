<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { formatMoney } from '@/lib/format';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import type { ChannelType } from '@/types/models';

interface BarItem {
    label: string;
    value: number;
    color?: string;
    meta?: string;
    channelType?: ChannelType;
    href?: string | null;
}

const props = withDefaults(
    defineProps<{
        items: BarItem[];
        format?: 'money' | 'number';
        emptyLabel?: string;
    }>(),
    { format: 'money', emptyLabel: 'Ni podatkov za to obdobje.' },
);

const maxValue = computed(() => Math.max(...props.items.map((i) => i.value), 1));
const total = computed(() => props.items.reduce((sum, i) => sum + i.value, 0));

function formatValue(value: number): string {
    return props.format === 'money' ? formatMoney(value) : value.toLocaleString('sl-SI');
}

function share(value: number): string {
    if (total.value <= 0) return '0 %';
    return `${Math.round((value / total.value) * 100)} %`;
}
</script>

<template>
    <div v-if="items.length" class="space-y-3">
        <component
            :is="item.href ? Link : 'div'"
            v-for="item in items"
            :key="item.label"
            :href="item.href ?? undefined"
            class="relative block"
            :class="item.href && 'group -mx-1.5 rounded-md px-1.5 py-0.5 transition hover:bg-neutral-50'"
        >
            <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                <span class="flex min-w-0 items-center gap-2 truncate font-medium text-neutral-800">
                    <ChannelIcon v-if="item.channelType" :type="item.channelType" size="sm" />
                    <span v-else class="h-2 w-2 shrink-0 rounded-full" :style="{ backgroundColor: item.color ?? 'var(--color-accent-500)' }" />
                    <span class="truncate" :class="item.href && 'group-hover:underline'">{{ item.label }}</span>
                    <span v-if="item.meta" class="shrink-0 text-xs font-normal text-neutral-400">· {{ item.meta }}</span>
                </span>
                <span class="shrink-0 text-neutral-500">
                    <span class="font-medium text-neutral-900">{{ formatValue(item.value) }}</span>
                    <span class="ml-1 text-xs text-neutral-400">({{ share(item.value) }})</span>
                </span>
            </div>
            <div class="h-1 overflow-hidden rounded-full bg-neutral-100/70">
                <div
                    class="h-full rounded-full"
                    :style="{
                        width: `${(item.value / maxValue) * 100}%`,
                        backgroundColor: item.color ?? 'var(--color-accent-500)',
                        opacity: 0.45,
                    }"
                />
            </div>
        </component>
    </div>
    <p v-else class="rounded-lg border border-dashed border-neutral-200 px-4 py-6 text-center text-sm text-neutral-400">
        {{ emptyLabel }}
    </p>
</template>
