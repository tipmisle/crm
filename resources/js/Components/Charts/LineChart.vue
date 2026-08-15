<script setup lang="ts">
import { computed, ref } from 'vue';
import { formatMoney } from '@/lib/format';

const props = defineProps<{
    series: { date: string; value: number }[];
    compareSeries?: { date: string; value: number }[] | null;
    compareLabel?: string | null;
}>();

const WIDTH = 640;
const HEIGHT = 220;
const PAD = { top: 16, right: 12, bottom: 24, left: 48 };
const plotWidth = WIDTH - PAD.left - PAD.right;
const plotHeight = HEIGHT - PAD.top - PAD.bottom;

function niceMax(value: number): number {
    if (value <= 0) return 10;
    const magnitude = 10 ** Math.floor(Math.log10(value));
    const normalized = value / magnitude;
    const step = normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10;
    return step * magnitude;
}

const hasCompare = computed(() => !!props.compareSeries && props.compareSeries.length === props.series.length);

const maxValue = computed(() => {
    const values = props.series.map((p) => p.value);
    if (hasCompare.value) values.push(...props.compareSeries!.map((p) => p.value));
    return niceMax(Math.max(...values, 0));
});

const gridSteps = [0, 0.25, 0.5, 0.75, 1];

function toPoints(series: { date: string; value: number }[]) {
    return series.map((p, i) => {
        const x = PAD.left + (series.length > 1 ? (i / (series.length - 1)) * plotWidth : plotWidth / 2);
        const y = PAD.top + plotHeight - (maxValue.value > 0 ? (p.value / maxValue.value) * plotHeight : 0);
        return { x, y, ...p };
    });
}

const points = computed(() => toPoints(props.series));
const comparePoints = computed(() => (hasCompare.value ? toPoints(props.compareSeries!) : []));

// Catmull-Rom → cubic Bezier conversion, so peaks/valleys read as soft
// curves instead of sharp zigzags. Control points are clamped to the plot's
// vertical bounds to stop the curve overshooting past the zero baseline.
function toLinePath(pts: { x: number; y: number }[]): string {
    if (pts.length === 0) return '';
    if (pts.length === 1) return `M ${pts[0].x.toFixed(1)} ${pts[0].y.toFixed(1)}`;

    const yMin = PAD.top;
    const yMax = PAD.top + plotHeight;
    const clampY = (y: number) => Math.min(yMax, Math.max(yMin, y));

    let d = `M ${pts[0].x.toFixed(1)} ${pts[0].y.toFixed(1)}`;
    for (let i = 0; i < pts.length - 1; i++) {
        const p0 = pts[i - 1] ?? pts[i];
        const p1 = pts[i];
        const p2 = pts[i + 1];
        const p3 = pts[i + 2] ?? p2;
        const cp1x = p1.x + (p2.x - p0.x) / 6;
        const cp1y = clampY(p1.y + (p2.y - p0.y) / 6);
        const cp2x = p2.x - (p3.x - p1.x) / 6;
        const cp2y = clampY(p2.y - (p3.y - p1.y) / 6);
        d += ` C ${cp1x.toFixed(1)} ${cp1y.toFixed(1)}, ${cp2x.toFixed(1)} ${cp2y.toFixed(1)}, ${p2.x.toFixed(1)} ${p2.y.toFixed(1)}`;
    }
    return d;
}

const linePath = computed(() => toLinePath(points.value));
const comparePath = computed(() => toLinePath(comparePoints.value));

const areaPath = computed(() => {
    if (!points.value.length) return '';
    const last = points.value[points.value.length - 1];
    const first = points.value[0];
    const baseline = PAD.top + plotHeight;
    return `${linePath.value} L ${last.x.toFixed(1)} ${baseline} L ${first.x.toFixed(1)} ${baseline} Z`;
});

// Sparse x-axis labels — dense periods (30/90 days) would otherwise collide.
const xLabelIndexes = computed(() => {
    const count = props.series.length;
    if (count <= 1) return [0];
    const targetLabels = 6;
    const step = Math.max(1, Math.round((count - 1) / (targetLabels - 1)));
    const indexes: number[] = [];
    for (let i = 0; i < count; i += step) indexes.push(i);
    if (indexes[indexes.length - 1] !== count - 1) indexes.push(count - 1);
    return indexes;
});

function formatAxisDate(dateStr: string): string {
    return new Intl.DateTimeFormat('sl-SI', { day: 'numeric', month: 'short' }).format(new Date(dateStr));
}

function compactMoney(value: number): string {
    if (value >= 1000) return `€${(value / 1000).toFixed(1)}k`;
    return `€${Math.round(value)}`;
}

const svgEl = ref<SVGSVGElement | null>(null);
const hoverIndex = ref<number | null>(null);
const hoverPoint = computed(() => (hoverIndex.value !== null ? points.value[hoverIndex.value] : null));
const hoverComparePoint = computed(() => (hoverIndex.value !== null ? comparePoints.value[hoverIndex.value] : null));

function onMouseMove(event: MouseEvent) {
    if (!svgEl.value || !points.value.length) return;
    const rect = svgEl.value.getBoundingClientRect();
    const x = ((event.clientX - rect.left) / rect.width) * WIDTH;

    let nearest = 0;
    let nearestDist = Infinity;
    points.value.forEach((p, i) => {
        const dist = Math.abs(p.x - x);
        if (dist < nearestDist) {
            nearestDist = dist;
            nearest = i;
        }
    });
    hoverIndex.value = nearest;
}

function onMouseLeave() {
    hoverIndex.value = null;
}

const total = computed(() => props.series.reduce((sum, p) => sum + p.value, 0));
</script>

<template>
    <div class="relative">
        <svg
            ref="svgEl"
            :viewBox="`0 0 ${WIDTH} ${HEIGHT}`"
            class="w-full touch-none"
            :style="{ aspectRatio: `${WIDTH} / ${HEIGHT}` }"
            @mousemove="onMouseMove"
            @mouseleave="onMouseLeave"
        >
            <!-- Gridlines + y-axis labels -->
            <g v-for="step in gridSteps" :key="step">
                <line
                    :x1="PAD.left"
                    :x2="WIDTH - PAD.right"
                    :y1="PAD.top + plotHeight * (1 - step)"
                    :y2="PAD.top + plotHeight * (1 - step)"
                    stroke="#e5e5e5"
                    stroke-width="1"
                />
                <text :x="PAD.left - 8" :y="PAD.top + plotHeight * (1 - step) + 3" text-anchor="end" font-size="7" fill="#a3a3a3">
                    {{ compactMoney(maxValue * step) }}
                </text>
            </g>

            <!-- x-axis labels -->
            <text
                v-for="i in xLabelIndexes"
                :key="i"
                :x="points[i]?.x"
                :y="HEIGHT - 6"
                text-anchor="middle"
                font-size="7"
                fill="#a3a3a3"
            >
                {{ formatAxisDate(series[i].date) }}
            </text>

            <path v-if="comparePath" :d="comparePath" fill="none" stroke="var(--color-accent-300)" stroke-width="1" stroke-dasharray="4 3" stroke-linecap="round" stroke-linejoin="round" />

            <path :d="areaPath" fill="var(--color-accent-500)" opacity="0.1" />
            <path :d="linePath" fill="none" stroke="var(--color-accent-500)" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" />

            <!-- Hover crosshair -->
            <g v-if="hoverPoint">
                <line :x1="hoverPoint.x" :x2="hoverPoint.x" :y1="PAD.top" :y2="PAD.top + plotHeight" stroke="#c3c2b7" stroke-width="1" />
                <circle v-if="hoverComparePoint" :cx="hoverComparePoint.x" :cy="hoverComparePoint.y" r="3.5" fill="var(--color-accent-300)" stroke="#fff" stroke-width="2" />
                <circle :cx="hoverPoint.x" :cy="hoverPoint.y" r="4" fill="var(--color-accent-500)" stroke="#fff" stroke-width="2" />
            </g>
        </svg>

        <div
            v-if="hoverPoint"
            class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-md border border-neutral-200 bg-white px-2.5 py-1.5 text-xs shadow-md"
            :style="{ left: `${(hoverPoint.x / WIDTH) * 100}%`, top: `${(hoverPoint.y / HEIGHT) * 100 - 3}%` }"
        >
            <p class="flex items-center gap-1.5 font-semibold text-neutral-900">
                <span class="inline-block h-1.5 w-1.5 rounded-full" style="background-color: var(--color-accent-500)" />
                {{ formatMoney(hoverPoint.value) }}
            </p>
            <p class="text-neutral-500">{{ formatAxisDate(hoverPoint.date) }}</p>
            <p v-if="hoverComparePoint" class="mt-1 flex items-center gap-1.5 border-t border-neutral-100 pt-1 text-neutral-600">
                <span class="inline-block h-1.5 w-1.5 rounded-full" style="background-color: var(--color-accent-300)" />
                {{ formatMoney(hoverComparePoint.value) }}
            </p>
        </div>

        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-neutral-400">
            <div v-if="hasCompare" class="flex flex-wrap items-center gap-3">
                <span class="flex items-center gap-1.5">
                    <span class="inline-block h-0.5 w-3 rounded-full" style="background-color: var(--color-accent-500)" />
                    Izbrano obdobje
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block w-3 border-t-2 border-dashed" style="border-color: var(--color-accent-300)" />
                    {{ compareLabel ?? 'Primerjava' }}
                </span>
            </div>
            <span v-else />
            <p>Skupaj v obdobju: {{ formatMoney(total) }}</p>
        </div>
    </div>
</template>
