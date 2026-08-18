import { reactive } from 'vue';

type ConfirmOptions = {
    title?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    danger?: boolean;
};

type ConfirmState = ConfirmOptions & {
    show: boolean;
    message: string;
    resolve: ((value: boolean) => void) | null;
};

const state = reactive<ConfirmState>({
    show: false,
    message: '',
    title: undefined,
    confirmLabel: 'Potrdi',
    cancelLabel: 'Prekliči',
    danger: false,
    resolve: null,
});

function confirm(message: string, options: ConfirmOptions = {}): Promise<boolean> {
    state.show = true;
    state.message = message;
    state.title = options.title;
    state.confirmLabel = options.confirmLabel ?? 'Potrdi';
    state.cancelLabel = options.cancelLabel ?? 'Prekliči';
    state.danger = options.danger ?? false;

    return new Promise((resolve) => {
        state.resolve = resolve;
    });
}

function settle(value: boolean) {
    state.show = false;
    state.resolve?.(value);
    state.resolve = null;
}

export function useConfirm() {
    return { confirm };
}

export function useConfirmState() {
    return { state, settle };
}
