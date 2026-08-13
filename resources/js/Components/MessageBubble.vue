<script setup lang="ts">
import { computed } from 'vue';
import { formatDateTime } from '@/lib/format';
import type { Message } from '@/types/models';

const props = defineProps<{ message: Message }>();

const isBusiness = computed(() => props.message.sender_type === 'business');
</script>

<template>
    <div class="flex" :class="isBusiness ? 'justify-end' : 'justify-start'">
        <div class="max-w-md">
            <div
                class="rounded-2xl px-4 py-2.5 text-sm"
                :class="
                    isBusiness
                        ? 'bg-[var(--color-accent-500)] text-white'
                        : 'bg-neutral-100 text-neutral-900'
                "
            >
                {{ message.body }}
            </div>
            <p class="mt-1 px-1 text-[11px] text-neutral-400" :class="isBusiness ? 'text-right' : 'text-left'">
                {{ formatDateTime(message.sent_at) }}
            </p>
        </div>
    </div>
</template>
