<script setup lang="ts">
import { nextTick, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import { useConfirm } from '@/composables/useConfirm';
import { formatDate, formatDateTime } from '@/lib/format';

interface Workspace {
    id: number;
    name: string;
    deletion_requested_at: string | null;
    scheduled_deletion_at: string | null;
}

interface ExportEntry {
    id: number;
    status: string;
    expires_at: string;
    downloaded_at: string | null;
    created_at: string;
}

const props = defineProps<{
    workspace: Workspace;
    isOwner: boolean;
    hasActiveSubscription: boolean;
    gracePeriodPreviewDate: string;
    exportHours: number;
    exports: ExportEntry[];
}>();

const { confirm } = useConfirm();

const confirmingDeletion = ref(false);
const passwordInput = ref<InstanceType<typeof TextInput> | null>(null);

const deleteForm = useForm({ password: '' });
const exportForm = useForm({});

function openConfirm() {
    confirmingDeletion.value = true;
    nextTick(() => passwordInput.value?.focus());
}

function closeConfirm() {
    confirmingDeletion.value = false;
    deleteForm.clearErrors();
    deleteForm.reset();
}

function requestDeletion() {
    deleteForm.post(route('settings.privacy.delete'), {
        preserveScroll: true,
        onSuccess: () => closeConfirm(),
    });
}

async function cancelDeletion() {
    if (!(await confirm('Prekliči izbris delovnega prostora?'))) return;
    router.delete(route('settings.privacy.cancel'), { preserveScroll: true });
}

function requestExport() {
    exportForm.post(route('settings.privacy.export.store'), { preserveScroll: true });
}

function isExportDownloadable(e: ExportEntry): boolean {
    return !e.downloaded_at && new Date(e.expires_at).getTime() > Date.now();
}
</script>

<template>
    <Head title="Nastavitve · Podatki in račun" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Podatki in račun</h1>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <SectionCard title="Izvozi podatke" subtitle="Prenesi vse podatke svojega delovnega prostora">
                        <p class="mb-4 text-sm text-neutral-600">
                            Izvoz vsebuje stranke, pogovore, sporočila, naročila, termine in ponudbo tvojega delovnega
                            prostora v strojno berljivi obliki. Izvoz je na voljo za prenos {{ exportHours }} ur, nato
                            ga samodejno izbrišemo.
                        </p>

                        <button
                            v-if="isOwner"
                            type="button"
                            class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50"
                            :disabled="exportForm.processing"
                            @click="requestExport"
                        >
                            Izvozi podatke delovnega prostora
                        </button>

                        <ul v-if="exports.length" class="mt-4 divide-y divide-neutral-100 text-sm">
                            <li v-for="e in exports" :key="e.id" class="flex items-center justify-between py-2">
                                <span class="text-neutral-600">{{ formatDateTime(e.created_at) }}</span>
                                <a
                                    v-if="isExportDownloadable(e)"
                                    :href="route('settings.privacy.export.download', e.id)"
                                    class="font-medium text-[var(--color-accent-500)] hover:underline"
                                >
                                    Prenesi
                                </a>
                                <span v-else class="text-xs text-neutral-400">
                                    {{ e.downloaded_at ? 'Preneseno' : 'Poteklo' }}
                                </span>
                            </li>
                        </ul>
                    </SectionCard>
                </div>

                <div class="lg:col-span-1">
                    <div class="lg:sticky lg:top-6">
                        <SectionCard v-if="isOwner" title="Izbriši delovni prostor">
                            <div v-if="workspace.deletion_requested_at" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                                <p>
                                    Delovni prostor bo izbrisan
                                    <strong>{{ formatDate(workspace.scheduled_deletion_at) }}</strong>.
                                </p>
                                <button
                                    type="button"
                                    class="mt-2 rounded-md border border-amber-300 bg-white px-3 py-1 text-xs font-medium text-amber-900 hover:bg-amber-100"
                                    @click="cancelDeletion"
                                >
                                    Prekliči izbris
                                </button>
                            </div>

                            <div v-else>
                                <p class="mb-4 text-sm text-neutral-600">
                                    Izbris trajno odstrani stranke, pogovore, naročila, termine in vse ostale podatke
                                    tega delovnega prostora. Podatkov po poteku obdobja za obnovitev ni več mogoče
                                    povrniti.
                                </p>
                                <p v-if="hasActiveSubscription" class="mb-4 text-sm text-amber-700">
                                    Ta delovni prostor ima aktivno naročnino — izbris jo bo ob dokončnem izbrisu
                                    podatkov tudi preklical.
                                </p>
                                <button
                                    type="button"
                                    class="rounded-md border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
                                    @click="openConfirm"
                                >
                                    Izbriši delovni prostor
                                </button>
                            </div>
                        </SectionCard>

                        <SectionCard v-else title="Izbriši delovni prostor">
                            <p class="text-sm text-neutral-500">Samo lastnik delovnega prostora lahko naroči izbris.</p>
                        </SectionCard>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="confirmingDeletion" max-width="sm" @close="closeConfirm">
            <div class="p-6">
                <h2 class="text-base font-semibold text-neutral-900">Izbriši delovni prostor?</h2>

                <p class="mt-1 text-sm text-neutral-500">
                    Delovni prostor bo označen za izbris. Podatke lahko obnoviš do
                    <strong>{{ formatDate(gracePeriodPreviewDate) }}</strong
                    >. Po tem datumu bodo podatki delovnega prostora trajno izbrisani.
                </p>

                <div class="mt-4">
                    <label for="privacy-password" class="sr-only">Geslo</label>
                    <TextInput
                        id="privacy-password"
                        ref="passwordInput"
                        v-model="deleteForm.password"
                        type="password"
                        placeholder="Geslo"
                        @keyup.enter="requestDeletion"
                    />
                    <p v-if="deleteForm.errors.password" class="mt-1 text-xs text-red-500">{{ deleteForm.errors.password }}</p>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-100" @click="closeConfirm">
                        Prekliči
                    </button>
                    <button
                        type="button"
                        :disabled="deleteForm.processing"
                        class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        @click="requestDeletion"
                    >
                        Izbriši delovni prostor
                    </button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
