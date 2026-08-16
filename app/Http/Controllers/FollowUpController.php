<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FollowUpController extends Controller
{
    private const ALLOWED_FOLLOWABLE_TYPES = [
        'App\\Models\\Customer',
        'App\\Models\\Order',
        'App\\Models\\Conversation',
        'App\\Models\\Appointment',
    ];

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'followable_type' => ['required', 'string', 'in:'.implode(',', self::ALLOWED_FOLLOWABLE_TYPES)],
            // Model classes above are all workspace-scoped (BelongsToWorkspace
            // global scope), so an existence check via the model's own query
            // also enforces the record belongs to the current workspace and
            // rejects crafted cross-workspace followable_id values.
            'followable_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    $type = $request->input('followable_type');

                    if (
                        ! is_string($type)
                        || ! in_array($type, self::ALLOWED_FOLLOWABLE_TYPES, true)
                        || ! $type::query()->whereKey($value)->exists()
                    ) {
                        $fail('Izbrani zapis ne obstaja.');
                    }
                },
            ],
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

        return back()->with('success', 'Opomnik dodan.');
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
