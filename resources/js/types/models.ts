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

export type OrderStatus =
    | 'new'
    | 'quote_needed'
    | 'quote_sent'
    | 'waiting_for_customer'
    | 'confirmed'
    | 'in_progress'
    | 'ready'
    | 'completed'
    | 'cancelled';

export type PaymentStatus =
    | 'unpaid'
    | 'deposit_due'
    | 'deposit_paid'
    | 'partially_paid'
    | 'paid';

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
}

export interface Channel {
    id: number;
    workspace_id: number;
    type: ChannelType;
    display_name: string | null;
    handle: string | null;
    status: 'connected' | 'not_connected';
    connected_at: string | null;
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
    notes: string | null;
    tags: string[] | null;
    primary_channel_id: number | null;
    primary_channel?: Channel;
    first_contacted_at: string | null;
    last_interaction_at: string | null;
    identities?: CustomerIdentity[];
    orders?: Order[];
    conversations?: Conversation[];
    lifetime_spend?: number;
    open_orders_count?: number;
    completed_orders_count?: number;
    total_orders_count?: number;
}

export interface Message {
    id: number;
    conversation_id: number;
    sender_type: MessageSenderType;
    sender_user_id: number | null;
    body: string;
    message_type: MessageType;
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
    notes?: OrderNote[];
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
