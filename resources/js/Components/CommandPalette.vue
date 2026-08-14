<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import { Search, User, Package, MessageSquare, CalendarDays } from 'lucide-vue-next';

const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ 'update:open': [boolean] }>();

interface SearchResult {
    type: 'customer' | 'order' | 'conversation' | 'appointment';
    id: number;
    title: string;
    subtitle: string;
    href: string;
}

const query = ref('');
const results = ref<SearchResult[]>([]);
const loading = ref(false);
const inputRef = ref<HTMLInputElement | null>(null);

let debounceTimer: ReturnType<typeof setTimeout> | undefined;

watch(query, (value) => {
    clearTimeout(debounceTimer);

    if (!value.trim()) {
        results.value = [];
        return;
    }

    debounceTimer = setTimeout(async () => {
        loading.value = true;
        try {
            const response = await fetch(`/search?q=${encodeURIComponent(value)}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();
            results.value = data.results ?? [];
        } finally {
            loading.value = false;
        }
    }, 200);
});

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            query.value = '';
            results.value = [];
            await nextTick();
            inputRef.value?.focus();
        }
    },
);

function close() {
    emit('update:open', false);
}

function select(result: SearchResult) {
    close();
    router.visit(result.href);
}

function onKeydown(e: KeyboardEvent) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        emit('update:open', !props.open);
    }
    if (e.key === 'Escape') {
        close();
    }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));

const iconFor = { customer: User, order: Package, conversation: MessageSquare, appointment: CalendarDays };
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 pt-[12vh]"
        @click.self="close"
    >
        <div class="w-full max-w-lg overflow-hidden rounded-lg bg-white shadow-2xl">
            <div class="flex items-center gap-2 border-b border-neutral-100 px-4 py-3">
                <Search :size="16" class="text-neutral-400" />
                <input
                    ref="inputRef"
                    v-model="query"
                    type="text"
                    placeholder="Iskanje strank, naročil, pogovorov …"
                    class="flex-1 border-0 text-sm text-neutral-900 outline-none placeholder:text-neutral-400"
                />
                <kbd class="rounded border border-neutral-200 px-1.5 py-0.5 text-[10px] text-neutral-400">Esc</kbd>
            </div>

            <div class="max-h-80 overflow-y-auto">
                <div v-if="loading" class="px-4 py-6 text-center text-sm text-neutral-400">Iskanje …</div>

                <div v-else-if="query && results.length === 0" class="px-4 py-6 text-center text-sm text-neutral-400">
                    Ni rezultatov za "{{ query }}"
                </div>

                <button
                    v-for="result in results"
                    :key="`${result.type}-${result.id}`"
                    type="button"
                    class="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-neutral-50"
                    @click="select(result)"
                >
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-neutral-100 text-neutral-500">
                        <component :is="iconFor[result.type]" :size="15" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-neutral-900">{{ result.title }}</span>
                        <span class="block truncate text-xs text-neutral-500">{{ result.subtitle }}</span>
                    </span>
                </button>

                <div v-if="!query" class="px-4 py-6 text-center text-sm text-neutral-400">
                    Poskusite z imenom stranke, številko naročila ali pogovorom.
                </div>
            </div>
        </div>
    </div>
</template>
