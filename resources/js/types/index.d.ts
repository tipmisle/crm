import type { Workspace } from './models';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    avatar_url?: string | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    workspace: Workspace | null;
    unreadInboxCount: number;
    vapidPublicKey: string | null;
    flash: {
        success?: string | null;
        error?: string | null;
    };
};
