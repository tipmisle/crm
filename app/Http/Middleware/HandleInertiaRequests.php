<?php

namespace App\Http\Middleware;

use App\Models\Conversation;
use App\Services\Billing\WorkspaceSubscriptionStateService;
use App\Support\SupportSessionManager;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $activeSupportSession = $user?->isPlatformAdmin()
            ? app(SupportSessionManager::class)->current($request)?->load(['workspace:id,name'])
            : null;

        $billing = $user?->currentWorkspace && ! $user->currentWorkspace->is_demo
            ? ['status' => app(WorkspaceSubscriptionStateService::class)->for($user->currentWorkspace)->value]
            : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'workspace' => $user?->currentWorkspace,
            'unreadInboxCount' => $user?->current_workspace_id
                ? (int) Conversation::where('unread_count', '>', 0)->sum('unread_count')
                : 0,
            'activeSupportSession' => $activeSupportSession ? [
                'workspace' => $activeSupportSession->workspace,
                'expires_at' => $activeSupportSession->expires_at,
            ] : null,
            'vapidPublicKey' => config('webpush.vapid.public_key'),
            'billing' => $billing,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
