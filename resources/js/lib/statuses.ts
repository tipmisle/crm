import type { AppointmentStatus, ConversationStatus } from '@/types/models';

/**
 * Order/payment statuses are workspace-editable (Settings → Statusi) and come
 * from the shared Inertia props `orderStatuses`/`paymentStatuses`, not from
 * here. These MARKETING_DEMO_* constants are only a static mockup for the
 * public marketing page, which isn't tied to a real workspace.
 */
export const MARKETING_DEMO_ORDER_STATUS_META: Record<string, { label: string; color: string; bg: string }> = {
    new: { label: 'Novo', color: '#4B5563', bg: '#F1F2F4' },
    quote_needed: { label: 'Potrebna ponudba', color: '#B45309', bg: '#FEF3C7' },
    quote_sent: { label: 'Ponudba poslana', color: '#9D5CD1', bg: '#F3E8FF' },
    waiting_for_customer: { label: 'Čaka na stranko', color: '#B45309', bg: '#FEF3C7' },
    confirmed: { label: 'Potrjeno', color: '#0E7490', bg: '#E0F7FA' },
    in_progress: { label: 'V izdelavi', color: '#6A3CCB', bg: '#EBE5FD' },
    ready: { label: 'Pripravljeno', color: '#0F766E', bg: '#DBF6EF' },
    completed: { label: 'Zaključeno', color: '#15803D', bg: '#DCFCE7' },
    cancelled: { label: 'Preklicano', color: '#B91C1C', bg: '#FEE2E2' },
};

export const MARKETING_DEMO_PAYMENT_STATUS_META: Record<string, { label: string; color: string; bg: string }> = {
    unpaid: { label: 'Neplačano', color: '#B91C1C', bg: '#FEE2E2' },
    deposit_due: { label: 'Čaka se ara', color: '#B45309', bg: '#FEF3C7' },
    deposit_paid: { label: 'Ara plačana', color: '#0E7490', bg: '#E0F7FA' },
    partially_paid: { label: 'Delno plačano', color: '#6A3CCB', bg: '#EBE5FD' },
    paid: { label: 'Plačano', color: '#15803D', bg: '#DCFCE7' },
};

export const APPOINTMENT_STATUS_META: Record<AppointmentStatus, { label: string; color: string; bg: string }> = {
    requested: { label: 'Povpraševanje', color: '#B45309', bg: '#FEF3C7' },
    confirmed: { label: 'Potrjeno', color: '#0E7490', bg: '#E0F7FA' },
    completed: { label: 'Zaključeno', color: '#15803D', bg: '#DCFCE7' },
    cancelled: { label: 'Preklicano', color: '#B91C1C', bg: '#FEE2E2' },
    no_show: { label: 'Ni se zglasil/a', color: '#78716C', bg: '#F5F5F4' },
};

export const APPOINTMENT_STATUS_ORDER: AppointmentStatus[] = ['requested', 'confirmed', 'completed', 'cancelled', 'no_show'];

export const CONVERSATION_STATUS_META: Record<ConversationStatus, { label: string; color: string; bg: string }> = {
    new_enquiry: { label: 'Novo povpraševanje', color: '#6A3CCB', bg: '#EBE5FD' },
    needs_quote: { label: 'Potrebna ponudba', color: '#B45309', bg: '#FEF3C7' },
    waiting_for_customer: { label: 'Čaka na stranko', color: '#B45309', bg: '#FEF3C7' },
    order_confirmed: { label: 'Naročilo potrjeno', color: '#15803D', bg: '#DCFCE7' },
    closed: { label: 'Zaključeno', color: '#4B5563', bg: '#F1F2F4' },
};
