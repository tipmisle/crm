<?php

namespace App\Http\Controllers\Auth;

use App\Enums\LegalDocument;
use App\Http\Controllers\Controller;
use App\Models\LegalAcceptance;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms_dpa_accepted' => ['accepted'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $workspace = Workspace::create([
            'name' => "{$request->name}'s Business",
            'slug' => Str::slug($request->name).'-'.Str::random(6),
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $user->update(['current_workspace_id' => $workspace->id]);

        // Versions come from server-side config, never client input — see
        // docs/legal-compliance.md. Privacy Policy is intentionally not
        // recorded here: it's a notice, not something a user "agrees to".
        LegalAcceptance::record($user, LegalDocument::Terms, config('legal.terms_version'), $workspace, $request);
        LegalAcceptance::record($user, LegalDocument::Dpa, config('legal.dpa_version'), $workspace, $request);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('billing.activate', absolute: false));
    }
}
