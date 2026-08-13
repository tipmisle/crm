import { Instagram, Facebook, MessageCircle, Music2, Mail, Globe } from 'lucide-vue-next';
import type { ChannelType } from '@/types/models';

export const CHANNEL_META: Record<
    ChannelType,
    { label: string; color: string; bg: string; icon: unknown }
> = {
    instagram: { label: 'Instagram', color: '#E1306C', bg: '#FCE7F0', icon: Instagram },
    facebook_messenger: { label: 'Messenger', color: '#0084FF', bg: '#E3F1FF', icon: Facebook },
    tiktok: { label: 'TikTok', color: '#111111', bg: '#EEEEEE', icon: Music2 },
    whatsapp: { label: 'WhatsApp', color: '#25D366', bg: '#E4F9EC', icon: MessageCircle },
    email: { label: 'Email', color: '#6B7280', bg: '#F1F2F4', icon: Mail },
    website: { label: 'Website', color: '#6C62DF', bg: '#EEECFC', icon: Globe },
};

export function channelMeta(type: ChannelType) {
    return CHANNEL_META[type] ?? CHANNEL_META.website;
}
