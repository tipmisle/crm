<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import {
    addMonths,
    eachDayOfInterval,
    endOfMonth,
    endOfWeek,
    format,
    isSameDay,
    isSameMonth,
    isToday,
    startOfMonth,
    startOfWeek,
    subMonths,
} from 'date-fns';
import { sl } from 'date-fns/locale';

interface CalendarLink {
    label: string;
    href: string;
}

const props = defineProps<{
    markedDates?: string[];
    // A single destination opens directly; when both Orders and
    // Appointments calendars exist, pass both so the click can't silently
    // land on just one of them — see Today.vue's calendarLinks computed.
    calendarLinks?: CalendarLink[];
}>();

const cursor = ref(new Date());

const weeks = computed(() => {
    const start = startOfWeek(startOfMonth(cursor.value), { weekStartsOn: 1 });
    const end = endOfWeek(endOfMonth(cursor.value), { weekStartsOn: 1 });
    const days = eachDayOfInterval({ start, end });

    const rows: Date[][] = [];
    for (let i = 0; i < days.length; i += 7) {
        rows.push(days.slice(i, i + 7));
    }
    return rows;
});

const markedSet = computed(() => new Set(props.markedDates ?? []));

function hasMark(day: Date): boolean {
    return markedSet.value.has(format(day, 'yyyy-MM-dd'));
}

function goToday() {
    cursor.value = new Date();
}
</script>

<template>
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-4">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-neutral-900 capitalize">
                {{ format(cursor, 'LLLL yyyy', { locale: sl }) }}
            </h2>
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="rounded-md p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700"
                    title="Prejšnji mesec"
                    @click="cursor = subMonths(cursor, 1)"
                >
                    <ChevronLeft :size="14" />
                </button>
                <button
                    type="button"
                    class="rounded-md p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700"
                    title="Naslednji mesec"
                    @click="cursor = addMonths(cursor, 1)"
                >
                    <ChevronRight :size="14" />
                </button>
            </div>
        </div>

        <div class="grid grid-cols-7 gap-y-1 text-center">
            <span v-for="label in ['P', 'T', 'S', 'Č', 'P', 'S', 'N']" :key="label" class="text-[10px] font-medium text-neutral-400">
                {{ label }}
            </span>

            <template v-for="week in weeks" :key="week[0].toISOString()">
                <div v-for="day in week" :key="day.toISOString()" class="flex items-center justify-center py-0.5">
                    <span
                        class="relative flex h-7 w-7 items-center justify-center rounded-full text-xs"
                        :class="[
                            !isSameMonth(day, cursor) && 'text-neutral-300',
                            isSameMonth(day, cursor) && !isToday(day) && 'text-neutral-700',
                            isToday(day) && 'bg-[var(--color-ink-900)] font-semibold text-white',
                        ]"
                    >
                        {{ format(day, 'd') }}
                        <span
                            v-if="hasMark(day) && !isToday(day)"
                            class="absolute bottom-0.5 h-1 w-1 rounded-full bg-[var(--color-accent-500)]"
                        />
                    </span>
                </div>
            </template>
        </div>

        <div class="mt-3 flex items-center justify-between border-t border-neutral-100 pt-3">
            <button
                type="button"
                class="text-xs font-medium text-neutral-500 hover:text-neutral-800"
                @click="goToday"
            >
                Danes
            </button>
            <div v-if="calendarLinks?.length" class="flex items-center gap-3">
                <Link
                    v-for="link in calendarLinks"
                    :key="link.href"
                    :href="link.href"
                    class="text-xs font-medium text-[var(--color-accent-600)] hover:text-[var(--color-accent-700)]"
                >
                    {{ calendarLinks.length > 1 ? link.label : 'Odpri koledar' }}
                </Link>
            </div>
        </div>
    </div>
</template>
