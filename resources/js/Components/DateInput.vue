<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { onClickOutside } from '@vueuse/core';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-vue-next';

// Native <input type="date"> renders dd/mm/yyyy or mm/dd/yyyy depending on
// the browser's OS-level locale, not the page's `lang` — Chromium ignores
// `lang` on the element for this, and its picker UI can't be themed. So we
// mask a text input to the Slovenian dd.mm.llll format ourselves and back it
// with a custom calendar popover styled to match the rest of the app.

const props = withDefaults(
    defineProps<{
        modelValue: string;
        min?: string;
        max?: string;
        size?: 'sm' | 'md';
    }>(),
    { min: undefined, max: undefined, size: 'md' },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const MONTHS = ['Januar', 'Februar', 'Marec', 'April', 'Maj', 'Junij', 'Julij', 'Avgust', 'September', 'Oktober', 'November', 'December'];
const DAY_LABELS = ['P', 'T', 'S', 'Č', 'P', 'S', 'N'];

function isoToDisplay(iso: string): string {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
    if (!m) return '';
    return `${m[3]}.${m[2]}.${m[1]}`;
}

function displayToIso(display: string): string | null {
    const m = /^(\d{2})\.(\d{2})\.(\d{4})$/.exec(display);
    if (!m) return null;
    const [, day, month, year] = m;
    const iso = `${year}-${month}-${day}`;
    const date = new Date(iso);
    if (Number.isNaN(date.getTime()) || date.getUTCDate() !== Number(day) || date.getUTCMonth() + 1 !== Number(month)) return null;
    return iso;
}

function todayIso(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

const text = ref(isoToDisplay(props.modelValue));
const root = ref<HTMLElement | null>(null);
const open = ref(false);

// A concrete example date reads far more clearly as a placeholder than the
// literal pattern "dd.mm.llll" — show tomorrow's date in the same format.
const placeholderDate = (() => {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    return `${day}.${month}.${d.getFullYear()}`;
})();

const anchor = () => {
    const iso = props.modelValue && displayToIso(isoToDisplay(props.modelValue)) ? props.modelValue : todayIso();
    const [y, m] = iso.split('-').map(Number);
    return { year: y, month: m - 1 };
};

const viewYear = ref(anchor().year);
const viewMonth = ref(anchor().month);

watch(
    () => props.modelValue,
    (value) => {
        const display = isoToDisplay(value);
        if (display !== text.value) text.value = display;
    },
);

function onTextInput(event: Event) {
    const raw = (event.target as HTMLInputElement).value;
    const digits = raw.replace(/\D/g, '').slice(0, 8);

    let formatted = digits;
    if (digits.length > 4) formatted = `${digits.slice(0, 2)}.${digits.slice(2, 4)}.${digits.slice(4)}`;
    else if (digits.length > 2) formatted = `${digits.slice(0, 2)}.${digits.slice(2)}`;

    text.value = formatted;

    const iso = displayToIso(formatted);
    if (iso) emit('update:modelValue', iso);
    else if (formatted === '') emit('update:modelValue', '');
}

function onBlur() {
    let iso = displayToIso(text.value);
    if (iso && props.min && iso < props.min) iso = null;
    if (iso && props.max && iso > props.max) iso = null;

    if (iso && iso !== props.modelValue) emit('update:modelValue', iso);
    text.value = iso ? isoToDisplay(iso) : isoToDisplay(props.modelValue);
}

function togglePicker() {
    if (open.value) {
        open.value = false;
        return;
    }
    const a = anchor();
    viewYear.value = a.year;
    viewMonth.value = a.month;
    open.value = true;
}

function shiftMonth(delta: number) {
    let m = viewMonth.value + delta;
    let y = viewYear.value;
    if (m < 0) {
        m = 11;
        y -= 1;
    } else if (m > 11) {
        m = 0;
        y += 1;
    }
    viewMonth.value = m;
    viewYear.value = y;
}

interface DayCell {
    iso: string;
    day: number;
    inMonth: boolean;
    disabled: boolean;
    isToday: boolean;
    isSelected: boolean;
}

const weeks = computed<DayCell[][]>(() => {
    const year = viewYear.value;
    const month = viewMonth.value;
    const first = new Date(year, month, 1);
    // getDay(): 0=Sun..6=Sat — shift so Monday is column 0.
    const startOffset = (first.getDay() + 6) % 7;
    const gridStart = new Date(year, month, 1 - startOffset);
    const today = todayIso();

    const cells: DayCell[] = [];
    for (let i = 0; i < 42; i++) {
        const d = new Date(gridStart);
        d.setDate(gridStart.getDate() + i);
        const iso = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        cells.push({
            iso,
            day: d.getDate(),
            inMonth: d.getMonth() === month,
            disabled: Boolean((props.min && iso < props.min) || (props.max && iso > props.max)),
            isToday: iso === today,
            isSelected: iso === props.modelValue,
        });
    }

    const rows: DayCell[][] = [];
    for (let i = 0; i < 42; i += 7) rows.push(cells.slice(i, i + 7));
    return rows;
});

function selectDay(cell: DayCell) {
    if (cell.disabled) return;
    emit('update:modelValue', cell.iso);
    text.value = isoToDisplay(cell.iso);
    open.value = false;
}

function goToToday() {
    const iso = todayIso();
    if (props.min && iso < props.min) return;
    if (props.max && iso > props.max) return;
    const [y, m] = iso.split('-').map(Number);
    viewYear.value = y;
    viewMonth.value = m - 1;
    emit('update:modelValue', iso);
    text.value = isoToDisplay(iso);
    open.value = false;
}

onClickOutside(root, () => {
    open.value = false;
});
</script>

<template>
    <div ref="root" class="relative">
        <input
            :value="text"
            type="text"
            inputmode="numeric"
            :placeholder="placeholderDate"
            maxlength="10"
            :class="[
                'w-full rounded-md border border-neutral-200 outline-none focus:border-neutral-400',
                size === 'sm' ? 'px-2 py-1.5 pr-8 text-xs' : 'px-3 py-2 pr-9 text-sm',
            ]"
            @input="onTextInput"
            @blur="onBlur"
            @focus="open = true"
        />
        <button
            type="button"
            tabindex="-1"
            class="absolute top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600"
            :class="size === 'sm' ? 'right-2' : 'right-2.5'"
            @click="togglePicker"
        >
            <CalendarDays :size="size === 'sm' ? 13 : 15" />
        </button>

        <div
            v-if="open"
            class="absolute z-20 mt-1.5 w-64 rounded-lg border border-neutral-200 bg-white p-3 shadow-lg shadow-neutral-900/[0.06]"
        >
            <div class="flex items-center justify-between">
                <button
                    type="button"
                    tabindex="-1"
                    class="rounded-md p-1 text-neutral-400 hover:bg-neutral-50 hover:text-neutral-700"
                    @click="shiftMonth(-1)"
                >
                    <ChevronLeft :size="15" />
                </button>
                <span class="text-xs font-semibold text-neutral-800">{{ MONTHS[viewMonth] }} {{ viewYear }}</span>
                <button
                    type="button"
                    tabindex="-1"
                    class="rounded-md p-1 text-neutral-400 hover:bg-neutral-50 hover:text-neutral-700"
                    @click="shiftMonth(1)"
                >
                    <ChevronRight :size="15" />
                </button>
            </div>

            <div class="mt-2 grid grid-cols-7 gap-y-0.5 text-center">
                <span v-for="(label, i) in DAY_LABELS" :key="i" class="text-[10px] font-medium text-neutral-400">{{ label }}</span>
            </div>

            <div class="mt-0.5 grid grid-cols-7 gap-y-0.5 text-center">
                <template v-for="(week, wi) in weeks" :key="wi">
                    <button
                        v-for="cell in week"
                        :key="cell.iso"
                        type="button"
                        tabindex="-1"
                        :disabled="cell.disabled"
                        class="mx-auto flex h-7 w-7 items-center justify-center rounded-md text-xs transition disabled:cursor-not-allowed disabled:text-neutral-300"
                        :class="[
                            cell.isSelected
                                ? 'bg-[var(--color-ink-900)] font-semibold text-white'
                                : cell.inMonth
                                  ? 'text-neutral-700 hover:bg-[var(--color-accent-50)]'
                                  : 'text-neutral-300 hover:bg-neutral-50',
                            !cell.isSelected && cell.isToday ? 'ring-1 ring-inset ring-[var(--color-accent-400)]' : '',
                        ]"
                        @click="selectDay(cell)"
                    >
                        {{ cell.day }}
                    </button>
                </template>
            </div>

            <button
                type="button"
                tabindex="-1"
                class="mt-2.5 w-full rounded-md border border-neutral-200 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                @click="goToToday"
            >
                Danes
            </button>
        </div>
    </div>
</template>
