<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import { useConfirmState } from '@/composables/useConfirm';

const { state, settle } = useConfirmState();
</script>

<template>
    <Modal :show="state.show" max-width="sm" @close="settle(false)">
        <div class="p-6">
            <h2 v-if="state.title" class="text-base font-semibold text-neutral-900">{{ state.title }}</h2>
            <p class="text-sm" :class="state.title ? 'mt-1.5 text-neutral-500' : 'text-neutral-700'">{{ state.message }}</p>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-100" @click="settle(false)">
                    {{ state.cancelLabel }}
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-sm font-medium text-white"
                    :class="state.danger ? 'bg-red-600 hover:bg-red-700' : 'bg-[var(--color-ink-900)] hover:bg-[var(--color-ink-800)]'"
                    @click="settle(true)"
                >
                    {{ state.confirmLabel }}
                </button>
            </div>
        </div>
    </Modal>
</template>
