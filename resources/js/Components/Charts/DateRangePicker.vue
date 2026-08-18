<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Check, ChevronDown } from 'lucide-vue-next';
import DateInput from '@/Components/DateInput.vue';

const props = withDefaults(
    defineProps<{
        from: string;
        to: string;
        size?: 'sm' | 'md';
        allowAll?: boolean;
    }>(),
    { size: 'md', allowAll: false },
);

const emit = defineEmits<{
    (e: 'change', range: { from: string; to: string }): void;
}>();

// Deliberately NOT date.toISOString().slice(0, 10) — that converts to UTC
// first, so anyone east of UTC (e.g. Ljubljana, UTC+1/+2) gets "yesterday"
// for local midnight and every preset silently excludes today's data.
function toDateString(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function today(): Date {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    return d;
}

function daysAgo(n: number): Date {
    const d = today();
    d.setDate(d.getDate() - n);
    return d;
}

function monthStart(): Date {
    const d = today();
    d.setDate(1);
    return d;
}

const presets = computed(() => [
    ...(props.allowAll ? [{ key: 'all', label: 'Celotno obdobje', from: '', to: '' }] : []),
    { key: 'today', label: 'Danes', from: toDateString(today()), to: toDateString(today()) },
    { key: '7', label: 'Zadnjih 7 dni', from: toDateString(daysAgo(6)), to: toDateString(today()) },
    { key: '30', label: 'Zadnjih 30 dni', from: toDateString(daysAgo(29)), to: toDateString(today()) },
    { key: '90', label: 'Zadnjih 90 dni', from: toDateString(daysAgo(89)), to: toDateString(today()) },
    { key: 'mtd', label: 'Ta mesec', from: toDateString(monthStart()), to: toDateString(today()) },
]);

const activePreset = computed(() => presets.value.find((p) => p.from === props.from && p.to === props.to) ?? null);

const currentLabel = computed(() => {
    if (activePreset.value) return activePreset.value.label;
    if (!props.from && !props.to) return 'Celotno obdobje';
    if (props.from === props.to) return formatDisplay(props.from);
    return `${formatDisplay(props.from)} – ${formatDisplay(props.to)}`;
});

function formatDisplay(dateStr: string): string {
    if (!dateStr) return '';
    return new Intl.DateTimeFormat('sl-SI', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(dateStr));
}

const open = ref(false);
const customFrom = ref(props.from);
const customTo = ref(props.to);

function togglePanel() {
    if (!open.value) {
        customFrom.value = props.from;
        customTo.value = props.to;
    }
    open.value = !open.value;
}

function selectPreset(preset: { from: string; to: string }) {
    emit('change', { from: preset.from, to: preset.to });
    open.value = false;
}

function applyCustomRange() {
    if (!props.allowAll && (!customFrom.value || !customTo.value)) return;
    if (customFrom.value && customTo.value) {
        const from = customFrom.value <= customTo.value ? customFrom.value : customTo.value;
        const to = customFrom.value <= customTo.value ? customTo.value : customFrom.value;
        emit('change', { from, to });
    } else {
        emit('change', { from: customFrom.value, to: customTo.value });
    }
    open.value = false;
}

function closeOnEscape(e: KeyboardEvent) {
    if (open.value && e.key === 'Escape') open.value = false;
}

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape));
</script>

<template>
    <div class="relative">
        <button
            type="button"
            :class="[
                'flex items-center gap-2 rounded-md border border-neutral-200 bg-white font-medium text-neutral-700 hover:bg-neutral-50',
                size === 'sm' ? 'px-2.5 py-1.5 text-xs' : 'px-3 py-1.5 text-sm',
            ]"
            @click="togglePanel"
        >
            {{ currentLabel }}
            <ChevronDown :size="size === 'sm' ? 13 : 14" class="text-neutral-400" />
        </button>

        <div v-if="open" class="fixed inset-0 z-40" @click="open = false" />

        <div
            v-if="open"
            class="absolute right-0 z-50 mt-2 w-64 overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-lg"
        >
            <div class="py-1">
                <button
                    v-for="preset in presets"
                    :key="preset.key"
                    type="button"
                    :class="[
                        'flex w-full items-center justify-between gap-2 px-3 py-2 text-left hover:bg-neutral-50',
                        size === 'sm' ? 'text-xs' : 'text-sm',
                    ]"
                    @click="selectPreset(preset)"
                >
                    <span :class="activePreset?.key === preset.key ? 'font-semibold text-neutral-900' : 'text-neutral-700'">
                        {{ preset.label }}
                    </span>
                    <Check v-if="activePreset?.key === preset.key" :size="size === 'sm' ? 14 : 16" class="text-[var(--color-accent-500)]" />
                </button>
            </div>

            <div class="border-t border-neutral-100 p-3">
                <p class="mb-2 text-xs font-medium text-neutral-500">Poljubno obdobje</p>
                <div class="flex items-center gap-2">
                    <DateInput v-model="customFrom" size="sm" :max="customTo || undefined" class="min-w-0 flex-1" />
                    <span class="shrink-0 text-neutral-400">–</span>
                    <DateInput v-model="customTo" size="sm" :min="customFrom || undefined" :max="toDateString(today())" class="min-w-0 flex-1" />
                </div>
                <button
                    type="button"
                    :disabled="!allowAll && (!customFrom || !customTo)"
                    class="mt-2 w-full rounded-md bg-[var(--color-ink-900)] px-3 py-1.5 text-xs font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                    @click="applyCustomRange"
                >
                    Uporabi
                </button>
            </div>
        </div>
    </div>
</template>
