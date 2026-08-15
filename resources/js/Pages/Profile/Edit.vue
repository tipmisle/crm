<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();
</script>

<template>
    <Head title="Profil" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Profil</h1>
        </template>

        <div class="mx-auto max-w-2xl space-y-6 px-4 py-6 sm:px-6 sm:py-8">
            <h1 class="text-2xl font-semibold text-neutral-900">Profil</h1>

            <SectionCard title="Informacije o profilu" subtitle="Posodobite podatke o profilu svojega računa in e-poštni naslov.">
                <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />
            </SectionCard>

            <SectionCard title="Posodobi geslo" subtitle="Za varnost vašega računa uporabite dolgo, naključno geslo.">
                <UpdatePasswordForm />
            </SectionCard>

            <SectionCard
                title="Izbriši račun"
                subtitle="Ko je vaš račun izbrisan, bodo vsi njegovi viri in podatki trajno izbrisani."
            >
                <DeleteUserForm />
            </SectionCard>
        </div>
    </AppLayout>
</template>
