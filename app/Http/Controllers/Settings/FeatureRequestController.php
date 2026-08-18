<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\FeatureRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FeatureRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        FeatureRequest::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'subject' => $data['subject'],
            'message' => $data['message'],
        ]);

        return back()->with('success', 'Predlog je bil poslan.');
    }
}
