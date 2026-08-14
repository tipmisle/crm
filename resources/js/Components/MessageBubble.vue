<script setup lang="ts">
import { computed } from 'vue';
import { formatDateTime } from '@/lib/format';
import type { Message } from '@/types/models';

const props = defineProps<{ message: Message }>();

const isBusiness = computed(() => props.message.sender_type === 'business');
const attachments = computed(() => props.message.metadata?.attachments ?? []);
const isFailed = computed(() => props.message.status === 'failed');
const isPending = computed(() => props.message.status === 'pending');
</script>

<template>
    <div class="flex" :class="isBusiness ? 'justify-end' : 'justify-start'">
        <div class="max-w-md">
            <div
                class="space-y-2 rounded-2xl px-4 py-2.5 text-sm"
                :class="[
                    isBusiness ? 'bg-[var(--color-accent-500)] text-white' : 'bg-neutral-100 text-neutral-900',
                    isFailed ? 'opacity-60 ring-1 ring-rose-400' : '',
                ]"
            >
                <p v-if="message.body">{{ message.body }}</p>

                <div v-for="(attachment, i) in attachments" :key="i">
                    <img
                        v-if="attachment.type === 'image' && attachment.url"
                        :src="attachment.url"
                        class="max-h-64 rounded-lg"
                        alt="Priloga"
                    />
                    <a
                        v-else-if="attachment.url"
                        :href="attachment.url"
                        target="_blank"
                        rel="noopener"
                        class="underline"
                        :class="isBusiness ? 'text-white' : 'text-[var(--color-accent-600)]'"
                    >
                        📎 Priloga ({{ attachment.type }})
                    </a>
                </div>
            </div>
            <p class="mt-1 px-1 text-[11px] text-neutral-400" :class="isBusiness ? 'text-right' : 'text-left'">
                <span v-if="isPending">Pošiljanje …</span>
                <span v-else-if="isFailed" class="text-rose-500" :title="message.failure_reason ?? undefined">
                    Ni bilo poslano
                </span>
                <span v-else>{{ formatDateTime(message.sent_at) }}</span>
            </p>
        </div>
    </div>
</template>
