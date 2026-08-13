<script setup lang="ts">
import { computed } from 'vue';
import { channelMeta } from '@/lib/channels';
import type { ChannelType } from '@/types/models';

const props = withDefaults(
    defineProps<{
        type: ChannelType;
        size?: 'sm' | 'md';
    }>(),
    { size: 'sm' },
);

const meta = computed(() => channelMeta(props.type));

const dims = computed(() => (props.size === 'md' ? 'h-6 w-6' : 'h-5 w-5'));
const iconDims = computed(() => (props.size === 'md' ? 14 : 11));
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center rounded-full"
        :class="dims"
        :style="{ backgroundColor: meta.bg }"
        :title="meta.label"
    >
        <component :is="meta.icon" :size="iconDims" :style="{ color: meta.color }" />
    </span>
</template>
