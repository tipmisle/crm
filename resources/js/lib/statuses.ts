import type { ConversationStatus, OrderStatus, PaymentStatus } from '@/types/models';

export const ORDER_STATUS_META: Record<OrderStatus, { label: string; color: string; bg: string }> = {
    new: { label: 'New', color: '#4B5563', bg: '#F1F2F4' },
    quote_needed: { label: 'Quote needed', color: '#B45309', bg: '#FEF3C7' },
    quote_sent: { label: 'Quote sent', color: '#9D5CD1', bg: '#F3E8FF' },
    waiting_for_customer: { label: 'Waiting for customer', color: '#B45309', bg: '#FEF3C7' },
    confirmed: { label: 'Confirmed', color: '#0E7490', bg: '#E0F7FA' },
    in_progress: { label: 'In progress', color: '#5A4FD1', bg: '#EEECFC' },
    ready: { label: 'Ready', color: '#0F766E', bg: '#DBF6EF' },
    completed: { label: 'Completed', color: '#15803D', bg: '#DCFCE7' },
    cancelled: { label: 'Cancelled', color: '#B91C1C', bg: '#FEE2E2' },
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

export const PAYMENT_STATUS_META: Record<PaymentStatus, { label: string; color: string; bg: string }> = {
    unpaid: { label: 'Unpaid', color: '#B91C1C', bg: '#FEE2E2' },
    deposit_due: { label: 'Deposit due', color: '#B45309', bg: '#FEF3C7' },
    deposit_paid: { label: 'Deposit paid', color: '#0E7490', bg: '#E0F7FA' },
    partially_paid: { label: 'Partially paid', color: '#5A4FD1', bg: '#EEECFC' },
    paid: { label: 'Paid', color: '#15803D', bg: '#DCFCE7' },
};

export const CONVERSATION_STATUS_META: Record<ConversationStatus, { label: string; color: string; bg: string }> = {
    new_enquiry: { label: 'New enquiry', color: '#5A4FD1', bg: '#EEECFC' },
    needs_quote: { label: 'Needs quote', color: '#B45309', bg: '#FEF3C7' },
    waiting_for_customer: { label: 'Waiting for customer', color: '#B45309', bg: '#FEF3C7' },
    order_confirmed: { label: 'Order confirmed', color: '#15803D', bg: '#DCFCE7' },
    closed: { label: 'Closed', color: '#4B5563', bg: '#F1F2F4' },
};
