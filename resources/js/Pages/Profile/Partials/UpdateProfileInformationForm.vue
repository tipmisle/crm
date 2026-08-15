<script setup lang="ts">
import { ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import Avatar from '@/Components/Avatar.vue';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
    avatar: null as File | null,
});

const avatarPreview = ref<string | null>(null);
const avatarInput = ref<HTMLInputElement | null>(null);

function onAvatarChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.avatar = file;

    if (avatarPreview.value) {
        URL.revokeObjectURL(avatarPreview.value);
    }
    avatarPreview.value = file ? URL.createObjectURL(file) : null;
}

function submit() {
    form.transform((data) => ({ ...data, _method: 'patch' })).post(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.avatar = null;
            if (avatarPreview.value) {
                URL.revokeObjectURL(avatarPreview.value);
                avatarPreview.value = null;
            }
            if (avatarInput.value) {
                avatarInput.value.value = '';
            }
        },
    });
}
</script>

<template>
    <form class="space-y-4" @submit.prevent="submit">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Profilna slika</label>
            <div class="flex items-center gap-4">
                <Avatar :name="user.name" :src="avatarPreview ?? user.avatar_url" size="lg" />
                <div>
                    <button
                        type="button"
                        class="rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-700 hover:bg-neutral-50"
                        @click="avatarInput?.click()"
                    >
                        Izberi sliko
                    </button>
                    <input
                        ref="avatarInput"
                        type="file"
                        accept="image/*"
                        class="hidden"
                        @change="onAvatarChange"
                    />
                    <p v-if="form.errors.avatar" class="mt-1 text-xs text-red-500">{{ form.errors.avatar }}</p>
                </div>
            </div>
        </div>

        <div>
            <label for="name" class="mb-1.5 block text-sm font-medium text-neutral-700">Ime</label>
            <input
                id="name"
                v-model="form.name"
                type="text"
                required
                autofocus
                autocomplete="name"
                class="w-full max-w-sm rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
            />
            <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
        </div>

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-neutral-700">E-pošta</label>
            <input
                id="email"
                v-model="form.email"
                type="email"
                required
                autocomplete="username"
                class="w-full max-w-sm rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
            />
            <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
        </div>

        <div v-if="mustVerifyEmail && user.email_verified_at === null">
            <p class="text-sm text-neutral-600">
                Vaš e-poštni naslov ni potrjen.
                <Link
                    :href="route('verification.send')"
                    method="post"
                    as="button"
                    class="font-medium text-[var(--color-accent-600)] underline hover:text-[var(--color-accent-700)]"
                >
                    Kliknite tukaj za ponovno pošiljanje potrditvene e-pošte.
                </Link>
            </p>

            <div v-show="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-emerald-600">
                Nova potrditvena povezava je bila poslana na vaš e-poštni naslov.
            </div>
        </div>

        <div class="flex items-center gap-3 pt-1">
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-md bg-[var(--color-ink-900)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
            >
                Shrani
            </button>

            <Transition
                enter-active-class="transition ease-in-out"
                enter-from-class="opacity-0"
                leave-active-class="transition ease-in-out"
                leave-to-class="opacity-0"
            >
                <p v-if="form.recentlySuccessful" class="text-sm text-neutral-500">Shranjeno.</p>
            </Transition>
        </div>
    </form>
</template>
