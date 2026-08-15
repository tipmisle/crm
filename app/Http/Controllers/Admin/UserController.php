<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::query()->with('currentWorkspace:id,name');

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderByDesc('created_at')->paginate(25)->withQueryString()->through(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'created_at' => $u->created_at,
            'email_verified_at' => $u->email_verified_at,
            'is_demo' => $u->is_demo,
            'is_active' => $u->is_active,
            'is_platform_admin' => $u->is_platform_admin,
            'current_workspace' => $u->currentWorkspace,
        ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only('q'),
        ]);
    }

    public function show(User $user): Response
    {
        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'email_verified_at' => $user->email_verified_at,
                'is_demo' => $user->is_demo,
                'is_active' => $user->is_active,
                'deactivated_at' => $user->deactivated_at,
                'is_platform_admin' => $user->is_platform_admin,
            ],
            'memberships' => $user->workspaces()->select('workspaces.id', 'workspaces.name', 'workspaces.is_demo')->get(),
        ]);
    }

    public function deactivate(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'Ne moreš deaktivirati lastnega računa.');

        $user->update(['is_active' => false, 'deactivated_at' => now()]);

        AuditLog::record('admin.user.changed', $request, null, $user, ['action' => 'deactivate']);

        return back()->with('success', 'Uporabnik je bil deaktiviran.');
    }

    public function reactivate(Request $request, User $user)
    {
        $user->update(['is_active' => true, 'deactivated_at' => null]);

        AuditLog::record('admin.user.changed', $request, null, $user, ['action' => 'reactivate']);

        return back()->with('success', 'Uporabnik je bil ponovno aktiviran.');
    }
}
