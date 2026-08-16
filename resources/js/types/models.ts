export type ChannelType =
    | 'instagram'
    | 'facebook_messenger'
    | 'tiktok'
    | 'whatsapp'
    | 'email'
    | 'website';

export type ConversationStatus =
    | 'new_enquiry'
    | 'needs_quote'
    | 'waiting_for_customer'
    | 'order_confirmed'
    | 'closed';

/**
 * Order status and payment status are workspace-editable (Settings → Statusi
 * naročil in plačil) — the actual list of keys/labels/colors in use comes
 * from the shared Inertia props `orderStatuses`/`paymentStatuses`
 * (`StatusOption[]`), not from a fixed union.
 */
export type OrderStatus = string;
export type PaymentStatus = string;

export interface StatusOption {
    id: number;
    key: string;
    label: string;
    color: string;
    bg: string;
    is_default: boolean;
    is_completed?: boolean;
    is_cancelled?: boolean;
    is_deposit_default?: boolean;
    is_outstanding?: boolean;
}

export type AppointmentStatus = 'requested' | 'confirmed' | 'completed' | 'cancelled' | 'no_show';

export type MessageSenderType = 'customer' | 'business' | 'system';
export type MessageType = 'text' | 'image' | 'note' | 'system';

export interface Workspace {
    id: number;
    name: string;
    slug: string;
    email: string | null;
    logo_path: string | null;
    timezone: string;
    currency: string;
    orders_enabled: boolean;
    appointments_enabled: boolean;
    accepts_deposit: boolean;
    is_demo: boolean;
    demo_expires_at: string | null;
    demo_variant: 'services' | 'orders' | 'both' | null;
}

export interface Channel {
    id: number;
    workspace_id: number;
    integration_id: number | null;
    type: ChannelType;
    external_account_id: string | null;
    display_name: string | null;
    handle: string | null;
    status: 'connected' | 'disconnected' | 'not_connected';
    connected_at: string | null;
    last_synced_at: string | null;
}

export interface CustomerIdentity {
    id: number;
    customer_id: number;
    channel_type: ChannelType;
    external_id: string | null;
    username: string | null;
    display_name: string | null;
}

export interface Customer {
    id: number;
    full_name: string;
    email: string | null;
    phone: string | null;
    address_line?: string | null;
    postal_code?: string | null;
    city?: string | null;
    country?: string | null;
    tax_number?: string | null;
    notes: string | null;
    tags: string[] | null;
    primary_channel_id: number | null;
    primary_channel?: Channel;
    first_contacted_at: string | null;
    last_interaction_at: string | null;
    identities?: CustomerIdentity[];
    orders?: Order[];
    appointments?: Appointment[];
    conversations?: Conversation[];
    lifetime_spend?: number;
    open_orders_count?: number;
    completed_orders_count?: number;
    total_orders_count?: number;
    appointments_lifetime_spend?: number;
    previous_appointments_count?: number;
    no_show_appointments_count?: number;
}

export type MessageStatus = 'pending' | 'sent' | 'failed' | 'delivered' | 'read';

export interface MessageAttachment {
    type: 'image' | 'video' | 'audio' | 'file';
    source?: 'local' | 'external';
    path?: string;
    url: string | null;
}

export interface Message {
    id: number;
    conversation_id: number;
    sender_type: MessageSenderType;
    sender_user_id: number | null;
    external_message_id: string | null;
    body: string | null;
    message_type: MessageType;
    status: MessageStatus;
    failure_reason: string | null;
    metadata: { attachments?: MessageAttachment[] } | null;
    sent_at: string;
}

export interface Conversation {
    id: number;
    channel_id: number;
    channel?: Channel;
    customer_id: number | null;
    customer?: Customer;
    customer_display_name: string | null;
    customer_username: string | null;
    status: ConversationStatus;
    last_message_preview: string | null;
    last_message_at: string | null;
    unread_count: number;
    messages?: Message[];
    orders?: Order[];
}

export interface OrderNote {
    id: number;
    order_id: number;
    user_id: number | null;
    user?: { id: number; name: string };
    body: string;
    created_at: string;
}

export interface Order {
    id: number;
    order_number: string;
    customer_id: number;
    customer?: Customer;
    conversation_id: number | null;
    conversation?: Conversation;
    channel_id: number | null;
    channel?: Channel;
    catalog_item_id: number | null;
    product?: Product;
    title: string;
    description: string | null;
    due_date: string | null;
    due_time: string | null;
    price: string | number;
    deposit_amount: string | number;
    amount_paid: string | number;
    payment_status: PaymentStatus;
    status: OrderStatus;
    internal_notes: string | null;
    customer_notes: string | null;
    tags: string[] | null;
    tracking_number: string | null;
    tracking_url: string | null;
    shipped_at: string | null;
    notes?: OrderNote[];
    sales_documents?: SalesDocument[];
    created_at: string;
}

export interface SalesDocument {
    id: number;
    order_id: number | null;
    customer_id: number | null;
    type: 'proforma' | 'invoice' | 'other';
    source: 'issued' | 'external';
    document_number: string | null;
    external_document_number: string | null;
    issued_at: string;
    due_date: string | null;
    currency: string;
    total: string | number;
    sent_at: string | null;
    created_at: string;
}

export interface FollowUp {
    id: number;
    user_id: number | null;
    followable_type: string;
    followable_id: number;
    note: string;
    due_at: string;
    completed_at: string | null;
}

export interface ActivityLogEntry {
    id: number;
    subject_type: string;
    subject_id: number;
    type: string;
    description: string;
    created_at: string;
}

export interface AuthUser {
    id: number;
    name: string;
    email: string;
}

export interface Service {
    id: number;
    workspace_id: number;
    type: 'service';
    name: string;
    description: string | null;
    default_duration_minutes: number;
    default_price: string | number | null;
    default_deposit_amount: string | number | null;
    active: boolean;
}

export interface Product {
    id: number;
    workspace_id: number;
    type: 'product';
    name: string;
    description: string | null;
    default_price: string | number | null;
    default_deposit_amount: string | number | null;
    active: boolean;
}

export interface Appointment {
    id: number;
    appointment_number: string;
    customer_id: number;
    customer?: Customer;
    conversation_id: number | null;
    conversation?: Conversation;
    channel_id: number | null;
    channel?: Channel;
    service_id: number | null;
    service?: Service;
    service_name: string;
    description: string | null;
    appointment_date: string;
    start_time: string;
    duration_minutes: number;
    price: string | number | null;
    deposit_amount: string | number;
    amount_paid: string | number;
    payment_status: PaymentStatus;
    status: AppointmentStatus;
    internal_notes: string | null;
    customer_notes: string | null;
    tags: string[] | null;
    created_at: string;
}
