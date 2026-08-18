<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AppointmentStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AppointmentStatusController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => 'required|string|max:100',
            'color' => 'required|string|max:7',
            'bg' => 'required|string|max:7',
        ]);

        $workspace = $request->user()->currentWorkspace;

        AppointmentStatus::create([
            'workspace_id' => $workspace->id,
            'key' => $this->uniqueKey($workspace->id, $data['label']),
            'label' => $data['label'],
            'color' => $data['color'],
            'bg' => $data['bg'],
            'sort_order' => (AppointmentStatus::query()->max('sort_order') ?? -1) + 1,
        ]);

        return back()->with('success', 'Status termina dodan.');
    }

    public function update(Request $request, AppointmentStatus $appointmentStatus): RedirectResponse
    {
        $data = $request->validate([
            'label' => 'sometimes|string|max:100',
            'color' => 'sometimes|string|max:7',
            'bg' => 'sometimes|string|max:7',
            'is_default' => 'sometimes|boolean',
            'is_completed' => 'sometimes|boolean',
            'is_cancelled' => 'sometimes|boolean',
            'is_no_show' => 'sometimes|boolean',
            'is_refunded' => 'sometimes|boolean',
        ]);

        // is_default/is_completed/is_cancelled/is_no_show/is_refunded are
        // each a single-status role, exactly one status per role
        // workspace-wide (see Settings/Statuses.vue — a status either IS
        // one of these 5 fixed roles, shown without any selector, or it's a
        // plain status offering a dropdown to become one). A false value
        // for one of these is a no-op rather than leaving zero statuses
        // flagged; the only way to move a role off its current status is to
        // assign it to a different one.
        $roleFlags = ['is_default', 'is_completed', 'is_cancelled', 'is_no_show', 'is_refunded'];

        foreach ($roleFlags as $roleFlag) {
            if (array_key_exists($roleFlag, $data) && ! $data[$roleFlag]) {
                unset($data[$roleFlag]);
            }
        }

        $flagToMove = collect($roleFlags)->first(fn ($flag) => ! empty($data[$flag]));

        if ($flagToMove) {
            DB::transaction(function () use ($appointmentStatus, $data, $flagToMove) {
                AppointmentStatus::where('id', '!=', $appointmentStatus->id)->update([$flagToMove => false]);
                $appointmentStatus->update($data);
            });

            return back()->with('success', 'Status termina posodobljen.');
        }

        $appointmentStatus->update($data);

        return back()->with('success', 'Status termina posodobljen.');
    }

    public function destroy(Request $request, AppointmentStatus $appointmentStatus): RedirectResponse
    {
        abort_if(AppointmentStatus::query()->count() <= 1, 422, 'Delovni prostor mora imeti vsaj en status termina.');

        abort_if(
            $appointmentStatus->is_default || $appointmentStatus->is_completed || $appointmentStatus->is_cancelled || $appointmentStatus->is_no_show || $appointmentStatus->is_refunded,
            422,
            'Ta status je obvezen (privzet, zaključeno, preklicano, ni se zglasil/a ali vračilo) in ga ni mogoče izbrisati. Najprej premakni to oznako na drug status.'
        );

        $workspace = $request->user()->currentWorkspace;

        $data = $request->validate([
            'reassign_to' => ['nullable', 'string', Rule::exists('appointment_statuses', 'key')->where('workspace_id', $workspace->id)],
        ]);

        if ($appointmentStatus->appointments()->exists()) {
            $reassignTo = $data['reassign_to'] ?? null;

            abort_if(
                ! $reassignTo || $reassignTo === $appointmentStatus->key,
                422,
                'Ta status je v uporabi. Izberi status, na katerega naj se prestavijo obstoječi termini.'
            );

            abort_unless(
                AppointmentStatus::query()->where('key', $reassignTo)->exists(),
                422,
                'Izbrani status ne obstaja.'
            );

            DB::transaction(function () use ($appointmentStatus, $reassignTo) {
                $appointmentStatus->appointments()->update(['status' => $reassignTo]);
                $appointmentStatus->delete();
            });

            return back()->with('success', 'Status termina izbrisan, termini prestavljeni na nov status.');
        }

        $appointmentStatus->delete();

        return back()->with('success', 'Status termina izbrisan.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:appointment_statuses,id',
        ]);

        $workspace = $request->user()->currentWorkspace;

        DB::transaction(function () use ($data, $workspace) {
            foreach ($data['ids'] as $index => $id) {
                AppointmentStatus::where('id', $id)->where('workspace_id', $workspace->id)->update(['sort_order' => $index]);
            }
        });

        return back();
    }

    private function uniqueKey(int $workspaceId, string $label): string
    {
        $base = Str::slug($label, '_') ?: 'status';
        $key = $base;
        $suffix = 1;

        while (AppointmentStatus::withoutGlobalScopes()->where('workspace_id', $workspaceId)->where('key', $key)->exists()) {
            $key = "{$base}_{$suffix}";
            $suffix++;
        }

        return $key;
    }
}
