<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        return Inertia::render('Settings/Edit', [
            'workspace' => $workspace,
            'channels' => Channel::where('workspace_id', $workspace->id)->orderBy('type')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'timezone' => 'required|string|max:64',
            'currency' => 'required|string|size:3',
        ]);

        $request->user()->currentWorkspace->update($data);

        return back()->with('success', 'Business settings saved.');
    }
}
