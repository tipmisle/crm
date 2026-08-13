<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import { channelMeta } from '@/lib/channels';
import type { Channel, Workspace } from '@/types/models';

const props = defineProps<{
    workspace: Workspace;
    channels: Channel[];
}>();

const form = useForm({
    name: props.workspace.name,
    email: props.workspace.email ?? '',
    timezone: props.workspace.timezone,
    currency: props.workspace.currency,
});

function submit() {
    form.patch(route('settings.update'));
}

const timezones = ['Europe/Ljubljana', 'Europe/London', 'Europe/Berlin', 'America/New_York', 'America/Los_Angeles', 'UTC'];
const currencies = ['EUR', 'USD', 'GBP'];
</script>

<template>
    <Head title="Settings" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Settings</h1>
        </template>

        <div class="mx-auto max-w-3xl space-y-6 px-6 py-8">
            <h1 class="text-2xl font-semibold text-neutral-900">Settings</h1>

            <SectionCard title="Business" subtitle="Basic information about your business">
                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Business name</label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full max-w-sm rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Contact email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            class="w-full max-w-sm rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                    </div>

                    <div class="grid max-w-sm grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Timezone</label>
                            <select v-model="form.timezone" class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none">
                                <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Currency</label>
                            <select v-model="form.currency" class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none">
                                <option v-for="c in currencies" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800 disabled:opacity-50"
                    >
                        Save changes
                    </button>
                </form>
            </SectionCard>

            <SectionCard title="Channels" subtitle="Connect your social accounts to sync conversations automatically">
                <p class="mb-4 text-sm text-neutral-500">
                    Once connected, new messages from these channels will flow into your Inbox automatically. For now, channel
                    connections are simulated with demo data.
                </p>

                <div class="space-y-2">
                    <div
                        v-for="channel in channels"
                        :key="channel.id"
                        class="flex items-center justify-between rounded-lg border border-neutral-200 px-4 py-3"
                    >
                        <div class="flex items-center gap-3">
                            <ChannelIcon :type="channel.type" size="md" />
                            <div>
                                <p class="text-sm font-medium text-neutral-900">{{ channelMeta(channel.type).label }}</p>
                                <p class="text-xs text-neutral-500">{{ channel.display_name }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="text-xs font-medium text-neutral-400">Not connected</span>
                            <button
                                type="button"
                                disabled
                                title="Coming soon"
                                class="cursor-not-allowed rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-400"
                            >
                                Connect
                            </button>
                        </div>
                    </div>
                </div>
            </SectionCard>
        </div>
    </AppLayout>
</template>
