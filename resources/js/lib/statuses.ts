import type { AppointmentStatus, ConversationStatus, OrderStatus, PaymentStatus } from '@/types/models';

export const ORDER_STATUS_META: Record<OrderStatus, { label: string; color: string; bg: string }> = {
    new: { label: 'Novo', color: '#4B5563', bg: '#F1F2F4' },
    quote_needed: { label: 'Potrebna ponudba', color: '#B45309', bg: '#FEF3C7' },
    quote_sent: { label: 'Ponudba poslana', color: '#9D5CD1', bg: '#F3E8FF' },
    waiting_for_customer: { label: 'Čaka na stranko', color: '#B45309', bg: '#FEF3C7' },
    confirmed: { label: 'Potrjeno', color: '#0E7490', bg: '#E0F7FA' },
    in_progress: { label: 'V izdelavi', color: '#5A4FD1', bg: '#EEECFC' },
    ready: { label: 'Pripravljeno', color: '#0F766E', bg: '#DBF6EF' },
    completed: { label: 'Zaključeno', color: '#15803D', bg: '#DCFCE7' },
    cancelled: { label: 'Preklicano', color: '#B91C1C', bg: '#FEE2E2' },
};

export const ORDER_STATUS_ORDER: OrderStatus[] = [
    'new',
    'quote_needed',
    'quote_sent',
    'waiting_for_customer',
    'confirmed',
    'in_progress',
    'ready',
    'completed',
    'cancelled',
];

export const APPOINTMENT_STATUS_META: Record<AppointmentStatus, { label: string; color: string; bg: string }> = {
    requested: { label: 'Povpraševanje', color: '#B45309', bg: '#FEF3C7' },
    confirmed: { label: 'Potrjeno', color: '#0E7490', bg: '#E0F7FA' },
    completed: { label: 'Zaključeno', color: '#15803D', bg: '#DCFCE7' },
    cancelled: { label: 'Preklicano', color: '#B91C1C', bg: '#FEE2E2' },
    no_show: { label: 'Ni se zglasil/a', color: '#78716C', bg: '#F5F5F4' },
};

export const APPOINTMENT_STATUS_ORDER: AppointmentStatus[] = ['requested', 'confirmed', 'completed', 'cancelled', 'no_show'];

export const PAYMENT_STATUS_META: Record<PaymentStatus, { label: string; color: string; bg: string }> = {
    unpaid: { label: 'Neplačano', color: '#B91C1C', bg: '#FEE2E2' },
    deposit_due: { label: 'Čaka se ara', color: '#B45309', bg: '#FEF3C7' },
    deposit_paid: { label: 'Ara plačana', color: '#0E7490', bg: '#E0F7FA' },
    partially_paid: { label: 'Delno plačano', color: '#5A4FD1', bg: '#EEECFC' },
    paid: { label: 'Plačano', color: '#15803D', bg: '#DCFCE7' },
};

export const CONVERSATION_STATUS_META: Record<ConversationStatus, { label: string; color: string; bg: string }> = {
    new_enquiry: { label: 'Novo povpraševanje', color: '#5A4FD1', bg: '#EEECFC' },
    needs_quote: { label: 'Potrebna ponudba', color: '#B45309', bg: '#FEF3C7' },
    waiting_for_customer: { label: 'Čaka na stranko', color: '#B45309', bg: '#FEF3C7' },
    order_confirmed: { label: 'Naročilo potrjeno', color: '#15803D', bg: '#DCFCE7' },
    closed: { label: 'Zaključeno', color: '#4B5563', bg: '#F1F2F4' },
};
