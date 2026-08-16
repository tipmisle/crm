<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import type { Order } from '@/types/models';

const props = defineProps<{
    show: boolean;
    order: Order;
    action: string;
}>();

const emit = defineEmits<{ close: [] }>();

type Step = 'choose' | 'pickup' | 'shipped';

const step = ref<Step>('choose');
const customerName = computed(() => props.order.customer?.full_name ?? 'tam');

const pickupLocation = ref('');
const pickupDate = ref('');
const pickupTime = ref('');

const trackingNumber = ref(props.order.tracking_number ?? '');
const trackingUrl = ref(props.order.tracking_url ?? '');

const bodyEditedManually = ref(false);

const form = useForm({
    type: '' as 'pickup' | 'shipped' | '',
    body: '',
    tracking_number: '',
    tracking_url: '',
});

function formatPickupWhen(): string {
    if (!pickupDate.value) return '';

    const date = new Date(`${pickupDate.value}T00:00:00`);
    const formatted = date.toLocaleDateString('sl-SI', { day: 'numeric', month: 'long' });

    return pickupTime.value ? `${formatted} ob ${pickupTime.value}` : formatted;
}

function buildPickupMessage(): string {
    let message = `Živjo ${customerName.value} 😊 Tvoje naročilo je pripravljeno za prevzem.`;

    const when = formatPickupWhen();
    const location = pickupLocation.value.trim();

    if (location && when) {
        message += ` Prevzameš ga lahko na ${location}, ${when}.`;
    } else if (location) {
        message += ` Prevzameš ga lahko na ${location}.`;
    } else if (when) {
        message += ` Prevzameš ga lahko ${when}.`;
    }

    return message;
}

function buildShippedMessage(): string {
    let message = `Živjo ${customerName.value} 😊 Tvoje naročilo je bilo poslano in je na poti do tebe.`;

    const number = trackingNumber.value.trim();
    const url = trackingUrl.value.trim();

    if (number) {
        message += ` Številka za sledenje: ${number}.`;
    }
    if (url) {
        message += ` Sledenje: ${url}`;
    }

    return message;
}

function regenerateBody() {
    if (bodyEditedManually.value) return;
    form.body = step.value === 'pickup' ? buildPickupMessage() : step.value === 'shipped' ? buildShippedMessage() : '';
}

watch([pickupLocation, pickupDate, pickupTime], regenerateBody);
watch([trackingNumber, trackingUrl], regenerateBody);

function markEdited() {
    bodyEditedManually.value = true;
}

function choose(type: 'pickup' | 'shipped') {
    step.value = type;
    form.type = type;
    bodyEditedManually.value = false;
    regenerateBody();
}

function reset() {
    step.value = 'choose';
    pickupLocation.value = '';
    pickupDate.value = '';
    pickupTime.value = '';
    trackingNumber.value = props.order.tracking_number ?? '';
    trackingUrl.value = props.order.tracking_url ?? '';
    bodyEditedManually.value = false;
    form.reset();
    form.clearErrors();
}

watch(
    () => props.show,
    (show) => {
        if (show) reset();
    },
);

function submit() {
    form.tracking_number = step.value === 'shipped' ? trackingNumber.value.trim() : '';
    form.tracking_url = step.value === 'shipped' ? trackingUrl.value.trim() : '';

    form.post(props.action, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}

const title = computed(() => {
    if (step.value === 'pickup') return 'Pripravljeno za prevzem';
    if (step.value === 'shipped') return 'Pošiljka je bila poslana';
    return 'Obvesti stranko';
});
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('close')">
        <div class="p-6">
            <h2 class="text-base font-semibold text-neutral-900">{{ title }}</h2>

            <template v-if="step === 'choose'">
                <p class="mt-1 text-sm text-neutral-500">Kaj želiš poslati?</p>
                <div class="mt-4 flex flex-col gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-neutral-200 px-4 py-2.5 text-left text-sm font-medium text-neutral-800 hover:bg-neutral-50"
                        @click="choose('pickup')"
                    >
                        Pripravljeno za prevzem
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-neutral-200 px-4 py-2.5 text-left text-sm font-medium text-neutral-800 hover:bg-neutral-50"
                        @click="choose('shipped')"
                    >
                        Pošiljka je bila poslana
                    </button>
                </div>
                <div class="mt-5 flex justify-end">
                    <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-100" @click="emit('close')">
                        Prekliči
                    </button>
                </div>
            </template>

            <form v-else class="mt-4" @submit.prevent="submit">
                <template v-if="step === 'pickup'">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Lokacija prevzema</label>
                            <input
                                v-model="pickupLocation"
                                type="text"
                                placeholder="npr. Prešernova 5, Ljubljana"
                                class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Datum</label>
                                <input
                                    v-model="pickupDate"
                                    type="date"
                                    class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Ura</label>
                                <input
                                    v-model="pickupTime"
                                    type="time"
                                    class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                                />
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else-if="step === 'shipped'">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Številka za sledenje</label>
                            <input
                                v-model="trackingNumber"
                                type="text"
                                placeholder="npr. 123456789"
                                class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                            />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Povezava za sledenje</label>
                            <input
                                v-model="trackingUrl"
                                type="text"
                                placeholder="https://…"
                                class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                            />
                            <p v-if="form.errors.tracking_url" class="mt-1 text-xs text-red-500">{{ form.errors.tracking_url }}</p>
                        </div>
                    </div>
                </template>

                <div class="mt-4">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Sporočilo</label>
                    <textarea
                        v-model="form.body"
                        rows="4"
                        class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        @input="markEdited"
                    />
                    <p v-if="form.errors.body" class="mt-1 text-xs text-red-500">{{ form.errors.body }}</p>
                </div>

                <div class="mt-5 flex justify-between gap-2">
                    <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-100" @click="step = 'choose'">
                        Nazaj
                    </button>
                    <div class="flex gap-2">
                        <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-100" @click="emit('close')">
                            Prekliči
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md bg-[var(--color-ink-900)] px-3 py-1.5 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                        >
                            Pošlji
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </Modal>
</template>
