<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Small first-run flow shown once after Stripe activation, before a new
 * workspace lands in an empty Today. Reuses Settings' own routes/validation
 * for the "Podjetje" step (settings.update / settings.capabilities.update)
 * and the existing Meta OAuth flow for "Sporočila" — this controller only
 * renders the page and marks it done, it doesn't duplicate any of that logic.
 */
class OnboardingController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace->needsOnboarding()) {
            return redirect()->route('dashboard');
        }

        $pending = $request->session()->get('meta_pending_accounts');

        return Inertia::render('Onboarding/Show', [
            'workspace' => $workspace,
            'channels' => Channel::where('workspace_id', $workspace->id)
                ->whereIn('type', ['instagram', 'facebook_messenger'])
                ->orderBy('type')
                ->get(),
            'metaPendingAccounts' => $pending
                ? collect($pending['accounts'])->map(fn ($a) => [
                    'channel_type' => $a['channel_type'],
                    'external_account_id' => $a['external_account_id'],
                    'display_name' => $a['display_name'],
                    'username' => $a['username'],
                ])->values()
                : null,
            'hasCatalogItems' => $workspace->products()->exists() || $workspace->services()->exists(),
        ]);
    }

    public function complete(Request $request): RedirectResponse
    {
        $request->user()->currentWorkspace->update(['onboarding_completed_at' => now()]);

        return redirect()->route('dashboard')->with('success', 'Dobrodošel/-la v Beležki!');
    }
}
