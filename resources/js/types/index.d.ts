import type { Workspace } from './models';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    avatar_url?: string | null;
    is_platform_admin?: boolean;
}

export interface ActiveSupportSession {
    workspace: { id: number; name: string };
    scope: 'technical' | 'workspace_content';
    expires_at: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    workspace: Workspace | null;
    unreadInboxCount: number;
    activeSupportSession: ActiveSupportSession | null;
    vapidPublicKey: string | null;
    flash: {
        success?: string | null;
        error?: string | null;
    };
};
