<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PasswordStrengthMeter from '@/Components/PasswordStrengthMeter.vue';
import TextInput from '@/Components/TextInput.vue';

const passwordInput = ref<HTMLInputElement | null>(null);
const currentPasswordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <form class="space-y-4" @submit.prevent="updatePassword">
        <div>
            <label for="current_password" class="mb-1.5 block text-sm font-medium text-neutral-700">Trenutno geslo</label>
            <TextInput
                id="current_password"
                ref="currentPasswordInput"
                v-model="form.current_password"
                type="password"
                autocomplete="current-password"
                class="max-w-sm"
            />
            <p v-if="form.errors.current_password" class="mt-1 text-xs text-red-500">{{ form.errors.current_password }}</p>
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-neutral-700">Novo geslo</label>
            <TextInput
                id="password"
                ref="passwordInput"
                v-model="form.password"
                type="password"
                autocomplete="new-password"
                class="max-w-sm"
            />
            <PasswordStrengthMeter :password="form.password" class="max-w-sm" />
            <p v-if="form.errors.password" class="mt-1 text-xs text-red-500">{{ form.errors.password }}</p>
        </div>

        <div>
            <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-neutral-700">Potrdi geslo</label>
            <TextInput
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                autocomplete="new-password"
                class="max-w-sm"
            />
            <p v-if="form.errors.password_confirmation" class="mt-1 text-xs text-red-500">{{ form.errors.password_confirmation }}</p>
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
