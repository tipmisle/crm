<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        $pending = $request->session()->get('meta_pending_accounts');

        return Inertia::render('Settings/Edit', [
            'workspace' => $workspace,
            'channels' => Channel::where('workspace_id', $workspace->id)
                ->whereIn('type', ['instagram', 'facebook_messenger'])
                ->orderBy('type')
                ->get(),
            'services' => Service::where('workspace_id', $workspace->id)->orderBy('name')->get(),
            'metaPendingAccounts' => $pending
                ? collect($pending['accounts'])->map(fn ($a) => [
                    'channel_type' => $a['channel_type'],
                    'external_account_id' => $a['external_account_id'],
                    'display_name' => $a['display_name'],
                    'username' => $a['username'],
                ])->values()
                : null,
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

        return back()->with('success', 'Nastavitve podjetja shranjene.');
    }

    public function updateCapabilities(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'orders_enabled' => 'required|boolean',
            'appointments_enabled' => 'required|boolean',
        ]);

        if (! $data['orders_enabled'] && ! $data['appointments_enabled']) {
            return back()->with('error', 'Vsaj eno od Naročila ali Termini mora ostati vklopljeno.');
        }

        $request->user()->currentWorkspace->update($data);

        return back()->with('success', 'Način poslovanja posodobljen.');
    }
}
