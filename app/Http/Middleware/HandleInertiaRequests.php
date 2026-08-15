<?php

namespace App\Http\Middleware;

use App\Models\Conversation;
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
                'scope' => $activeSupportSession->scope->value,
                'expires_at' => $activeSupportSession->expires_at,
            ] : null,
            'vapidPublicKey' => config('webpush.vapid.public_key'),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
