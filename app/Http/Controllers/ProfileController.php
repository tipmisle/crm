<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\AuditLog;
use App\Services\WorkspaceDeletionService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->safe()->only(['name', 'email']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     *
     * Account deletion is distinct from workspace deletion (see
     * Settings\WorkspacePrivacyController) — a user may belong to more
     * than one workspace. Rules, see docs/data-lifecycle.md:
     *  - Owns 0 workspaces: delete normally.
     *  - Owns a workspace where they're the only member: that workspace
     *    has no one else to inherit it, so it's cascade-deleted as part of
     *    account deletion (consent is implicit in deleting the account).
     *  - Owns a workspace with other members too: blocked — there is no
     *    ownership-transfer flow yet, so this is a hard stop rather than a
     *    silent data-loss risk.
     */
    public function destroy(Request $request, WorkspaceDeletionService $workspaceDeletion): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        $ownedWorkspaces = $user->workspaces()->wherePivot('role', 'owner')->get();

        $blocking = $ownedWorkspaces->filter(fn ($w) => $w->members()->count() > 1);

        if ($blocking->isNotEmpty()) {
            return back()->withErrors([
                'workspace' => 'Ne moreš izbrisati računa, dokler si lastnik delovnega prostora z drugimi člani. Najprej prenesi lastništvo ali izbriši delovni prostor.',
            ]);
        }

        $soloOwned = $ownedWorkspaces->filter(fn ($w) => $w->members()->count() === 1 && ! $w->is_demo);

        foreach ($soloOwned as $workspace) {
            $workspaceDeletion->delete($workspace);
        }

        $user->pushSubscriptions()->delete();

        AuditLog::record('privacy.account.deleted', $request, null, $user);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
