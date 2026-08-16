// No analytics provider is wired up in this project yet. These helpers push
// to window.dataLayer (the standard GTM convention) when present, and are a
// harmless no-op otherwise, so demo intent is captured the moment any
// analytics tool is installed without further code changes.
declare global {
    interface Window {
        dataLayer?: unknown[];
    }
}

export type DemoCtaPlacement = 'header' | 'hero' | 'orders_appointments' | 'pricing' | 'final_cta' | 'footer';
export type DemoVariant = 'services' | 'orders' | 'both';

function pushEvent(event: string, payload: Record<string, unknown> = {}) {
    if (typeof window === 'undefined') return;
    window.dataLayer = window.dataLayer ?? [];
    window.dataLayer.push({ event, ...payload });
}

export function trackDemoClick(placement: DemoCtaPlacement) {
    pushEvent('demo_cta_clicked', { placement });
}

export function trackDemoVariantSelected(variant: DemoVariant) {
    pushEvent('demo_variant_selected', { variant });
}
