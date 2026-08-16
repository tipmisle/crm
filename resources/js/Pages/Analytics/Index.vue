<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import StatCard from '@/Components/Charts/StatCard.vue';
import LineChart from '@/Components/Charts/LineChart.vue';
import BarList from '@/Components/Charts/BarList.vue';
import DateRangePicker from '@/Components/Charts/DateRangePicker.vue';
import { formatMoney, formatDate } from '@/lib/format';
import type { ChannelType } from '@/types/models';
import type { PageProps } from '@/types';

interface ChannelBar {
    type: ChannelType;
    label: string;
    color: string;
    value: number;
    href: string | null;
}

interface TopItem {
    name: string;
    revenue: number;
    href: string | null;
}

interface CompareInfo {
    key: 'previous' | 'year' | 'none';
    label: string | null;
    range: { from: string; to: string } | null;
}

const props = defineProps<{
    range: { from: string; to: string };
    compare: CompareInfo;
    stats: {
        revenue: { value: number; delta: number | null };
        inquiries: { value: number; delta: number | null };
        bookings: { value: number; delta: number | null };
        conversionRate: { value: number | null; delta: number | null };
    };
    revenueSeries: { date: string; value: number }[];
    compareRevenueSeries: { date: string; value: number }[] | null;
    channelInquiries: ChannelBar[];
    channelRevenue: ChannelBar[];
    topProducts: TopItem[];
    topServices: TopItem[];
}>();

const page = usePage<PageProps>();
const ordersEnabled = () => page.props.workspace?.orders_enabled ?? true;
const appointmentsEnabled = () => page.props.workspace?.appointments_enabled ?? false;

function setRange(range: { from: string; to: string }) {
    router.get(route('analytics.index', { ...range, compare: props.compare.key }), {}, { preserveScroll: true, preserveState: true });
}

function setCompare(event: Event) {
    const compare = (event.target as HTMLSelectElement).value;
    router.get(route('analytics.index', { ...props.range, compare }), {}, { preserveScroll: true, preserveState: true });
}

const topProductBars = () => props.topProducts.map((item) => ({ label: item.name, value: item.revenue, href: item.href }));
const topServiceBars = () => props.topServices.map((item) => ({ label: item.name, value: item.revenue, href: item.href }));

const channelInquiryBars = () =>
    props.channelInquiries.map((c) => ({ label: c.label, value: c.value, color: c.color, channelType: c.type, href: c.href }));
const channelRevenueBars = () =>
    props.channelRevenue.map((c) => ({ label: c.label, value: c.value, color: c.color, channelType: c.type, href: c.href }));
</script>

<template>
    <Head title="Analitika" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Analitika</h1>
        </template>

        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-neutral-900">Analitika</h1>
                    <p class="mt-1 text-sm text-neutral-500">Od kod prihajajo povpraševanja in koliko prinašajo.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <select
                        :value="compare.key"
                        class="rounded-md border border-neutral-200 bg-white px-3 py-1.5 text-sm font-medium text-neutral-700 outline-none hover:bg-neutral-50"
                        @change="setCompare"
                    >
                        <option value="previous">Primerjaj s prejšnjim obdobjem</option>
                        <option value="year">Primerjaj z lanskim letom</option>
                        <option value="none">Brez primerjave</option>
                    </select>
                    <DateRangePicker :from="range.from" :to="range.to" @change="setRange" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Skupni prihodki"
                    :value="formatMoney(stats.revenue.value)"
                    :delta="stats.revenue.delta"
                    :compare-label="compare.label"
                />
                <StatCard
                    label="Nova povpraševanja"
                    :value="String(stats.inquiries.value)"
                    :delta="stats.inquiries.delta"
                    :compare-label="compare.label"
                />
                <StatCard
                    label="Število naročil"
                    :value="String(stats.bookings.value)"
                    :delta="stats.bookings.delta"
                    :compare-label="compare.label"
                />
                <StatCard
                    label="Stopnja konverzije"
                    :value="stats.conversionRate.value !== null ? `${stats.conversionRate.value}%` : '—'"
                    :delta="stats.conversionRate.delta"
                    :compare-label="compare.label"
                />
            </div>

            <SectionCard title="Prihodki skozi čas" :subtitle="`${formatDate(range.from)} – ${formatDate(range.to)}`">
                <LineChart :series="revenueSeries" :compare-series="compareRevenueSeries" :compare-label="compare.label" />
            </SectionCard>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <SectionCard title="Od kod prihajajo povpraševanja" subtitle="Novi pogovori po socialnem omrežju">
                    <BarList :items="channelInquiryBars()" format="number" empty-label="V tem obdobju še ni bilo povpraševanj." />
                </SectionCard>

                <SectionCard title="Prihodki po kanalu" subtitle="Naročila in termini po izvornem socialnem omrežju">
                    <BarList :items="channelRevenueBars()" format="money" empty-label="V tem obdobju še ni bilo prihodkov." />
                </SectionCard>
            </div>

            <div
                class="grid grid-cols-1 gap-6"
                :class="ordersEnabled() && appointmentsEnabled() ? 'lg:grid-cols-2' : ''"
            >
                <SectionCard v-if="ordersEnabled()" title="Najbolje prodajani izdelki" subtitle="Po prihodku v izbranem obdobju">
                    <BarList :items="topProductBars()" format="money" empty-label="V tem obdobju še ni bilo naročil." />
                </SectionCard>

                <SectionCard v-if="appointmentsEnabled()" title="Najbolje prodajane storitve" subtitle="Po prihodku v izbranem obdobju">
                    <BarList :items="topServiceBars()" format="money" empty-label="V tem obdobju še ni bilo terminov." />
                </SectionCard>
            </div>
        </div>
    </AppLayout>
</template>
