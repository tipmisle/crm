<script setup lang="ts">
import { computed, onMounted, ref, useAttrs } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';

defineOptions({ inheritAttrs: false });

const model = defineModel<string>({ required: true });

const attrs = useAttrs();
const inputAttrs = computed(() => {
    const { class: _class, ...rest } = attrs;
    return rest;
});

const input = ref<HTMLInputElement | null>(null);
const showPassword = ref(false);

const isPassword = computed(() => attrs.type === 'password');
const resolvedType = computed(() => (isPassword.value && showPassword.value ? 'text' : ((attrs.type as string) ?? 'text')));

onMounted(() => {
    if (input.value?.hasAttribute('autofocus')) {
        input.value?.focus();
    }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <div class="relative">
        <input
            v-bind="inputAttrs"
            :type="resolvedType"
            v-model="model"
            ref="input"
            class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm text-neutral-900 outline-none focus:border-neutral-400"
            :class="isPassword ? 'pr-9' : ''"
        />
        <button
            v-if="isPassword"
            type="button"
            tabindex="-1"
            class="absolute inset-y-0 right-0 flex items-center px-2.5 text-neutral-400 hover:text-neutral-600"
            @click="showPassword = !showPassword"
        >
            <component :is="showPassword ? EyeOff : Eye" :size="16" />
        </button>
    </div>
</template>
