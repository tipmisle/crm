<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\BugReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BugReportController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'page_url' => ['nullable', 'string', 'max:2048'],
        ]);

        BugReport::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'page_url' => $data['page_url'] ?? null,
        ]);

        return back()->with('success', 'Prijava napake je bila poslana.');
    }
}
