<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FollowUpController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'followable_type' => 'required|string|in:App\\Models\\Customer,App\\Models\\Order,App\\Models\\Conversation',
            'followable_id' => 'required|integer',
            'note' => 'required|string|max:255',
            'due_at' => 'required|date',
        ]);

        FollowUp::create([
            'user_id' => auth()->id(),
            'followable_type' => $data['followable_type'],
            'followable_id' => $data['followable_id'],
            'note' => $data['note'],
            'due_at' => Carbon::parse($data['due_at']),
        ]);

        return back()->with('success', 'Follow-up scheduled.');
    }

    public function complete(FollowUp $followUp): RedirectResponse
    {
        $followUp->update(['completed_at' => Carbon::now()]);

        return back();
    }

    public function destroy(FollowUp $followUp): RedirectResponse
    {
        $followUp->delete();

        return back();
    }
}
