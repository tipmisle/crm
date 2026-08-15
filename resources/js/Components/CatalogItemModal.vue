<script setup lang="ts">
import { computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import type { Product, Service } from '@/types/models';

const props = defineProps<{
    open: boolean;
    kind: 'product' | 'service';
    item?: Product | Service | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved', item: Product | Service): void;
}>();

const isEditing = computed(() => !!props.item);
const isService = computed(() => props.kind === 'service');

const form = useForm({
    name: '',
    description: '',
    default_duration_minutes: 60 as number | string,
    default_price: '' as string | number,
    default_deposit_amount: '' as string | number,
});

function resetFromItem() {
    form.reset();
    form.clearErrors();
    if (props.item) {
        form.name = props.item.name;
        form.description = props.item.description ?? '';
        form.default_duration_minutes = isService.value ? (props.item as Service).default_duration_minutes : 60;
        form.default_price = props.item.default_price ?? '';
        form.default_deposit_amount = props.item.default_deposit_amount ?? '';
    }
}

watch(
    () => props.open,
    (open) => {
        if (open) resetFromItem();
    },
);

function close() {
    emit('update:open', false);
}

function submit() {
    const routeName = isEditing.value
        ? isService.value
            ? 'services.update'
            : 'products.update'
        : isService.value
          ? 'services.store'
          : 'products.store';

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            // The backend doesn't return the created/updated record directly
            // (it's a redirect back()), so re-fetch just the list props and
            // hand the newest matching item back to the caller so it can be
            // auto-selected (quick add flow).
            router.reload({
                only: [isService.value ? 'services' : 'products'],
                onSuccess: (page: any) => {
                    const list = (isService.value ? page.props.services : page.props.products) as (Product | Service)[];
                    const match = isEditing.value ? list.find((i) => i.id === props.item!.id) : [...list].sort((a, b) => b.id - a.id)[0];
                    if (match) emit('saved', match);
                    close();
                },
            });
        },
    };

    form.transform((data) =>
        isService.value
            ? data
            : { name: data.name, description: data.description, default_price: data.default_price, default_deposit_amount: data.default_deposit_amount },
    );

    if (isEditing.value) {
        form.patch(route(routeName, props.item!.id), options);
    } else {
        form.post(route(routeName), options);
    }
}
</script>

<template>
    <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0"
        leave-active-class="transition duration-100 ease-in"
        leave-to-class="opacity-0"
    >
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-[var(--color-ink-900)]/40 px-4" @click.self="close">
            <div class="w-full max-w-md rounded-xl bg-white shadow-xl">
                <header class="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-neutral-900">
                        {{ isEditing ? 'Uredi' : 'Dodaj' }} {{ isService ? 'storitev' : 'produkt' }}
                    </h2>
                    <button type="button" class="text-neutral-400 hover:text-neutral-700" @click="close">
                        <X :size="16" />
                    </button>
                </header>

                <form class="space-y-4 px-5 py-4" @submit.prevent="submit">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Ime</label>
                        <input
                            v-model="form.name"
                            type="text"
                            :placeholder="isService ? 'npr. Gel manikura' : 'npr. Rojstnodnevna torta'"
                            class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Opis (neobvezno)</label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                    </div>

                    <div v-if="isService">
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Trajanje (min)</label>
                        <input
                            v-model.number="form.default_duration_minutes"
                            type="number"
                            min="5"
                            class="w-full max-w-[10rem] rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                        <p v-if="form.errors.default_duration_minutes" class="mt-1 text-xs text-red-500">{{ form.errors.default_duration_minutes }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Privzeta cena</label>
                            <input
                                v-model="form.default_price"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                            />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Privzeta ara</label>
                            <input
                                v-model="form.default_deposit_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                            />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" class="rounded-md px-3 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-50" @click="close">
                            Prekliči
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing || !form.name"
                            class="rounded-md bg-[var(--color-ink-900)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                        >
                            Shrani
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Transition>
</template>
