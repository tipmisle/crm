<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\OrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderStatusController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => 'required|string|max:100',
            'color' => 'required|string|max:7',
            'bg' => 'required|string|max:7',
        ]);

        $workspace = $request->user()->currentWorkspace;

        OrderStatus::create([
            'workspace_id' => $workspace->id,
            'key' => $this->uniqueKey($workspace->id, $data['label']),
            'label' => $data['label'],
            'color' => $data['color'],
            'bg' => $data['bg'],
            'sort_order' => (OrderStatus::query()->max('sort_order') ?? -1) + 1,
        ]);

        return back()->with('success', 'Status naročila dodan.');
    }

    public function update(Request $request, OrderStatus $orderStatus): RedirectResponse
    {
        $data = $request->validate([
            'label' => 'sometimes|string|max:100',
            'color' => 'sometimes|string|max:7',
            'bg' => 'sometimes|string|max:7',
            'is_default' => 'sometimes|boolean',
            'is_completed' => 'sometimes|boolean',
            'is_cancelled' => 'sometimes|boolean',
            'is_refunded' => 'sometimes|boolean',
        ]);

        // is_default/is_completed/is_cancelled/is_refunded are each a
        // single-status role, exactly one status per role workspace-wide
        // (see Settings/Statuses.vue — a status either IS one of these 4
        // fixed roles, shown without any selector, or it's a plain status
        // offering a dropdown to become one). A false value for one of
        // these is a no-op rather than leaving zero statuses flagged; the
        // only way to move a role off its current status is to assign it
        // to a different one.
        $roleFlags = ['is_default', 'is_completed', 'is_cancelled', 'is_refunded'];

        // is_completed/is_cancelled/is_refunded describe mutually exclusive
        // terminal lifecycle states — an order can't simultaneously BE
        // completed and cancelled, so a status can never legitimately hold
        // more than one of these at once. is_default (the starting state)
        // is not part of that exclusion group.
        $lifecycleFlags = ['is_completed', 'is_cancelled', 'is_refunded'];

        foreach ($roleFlags as $roleFlag) {
            if (array_key_exists($roleFlag, $data) && ! $data[$roleFlag]) {
                unset($data[$roleFlag]);
            }
        }

        $flagsToMove = collect($roleFlags)->filter(fn ($flag) => ! empty($data[$flag]))->values();

        $requestedLifecycleFlags = $flagsToMove->intersect($lifecycleFlags);

        abort_if(
            $requestedLifecycleFlags->count() > 1,
            422,
            'Status ne more hkrati imeti več izključujočih se vlog (zaključeno, preklicano, vračilo).'
        );

        if ($requestedLifecycleFlags->isNotEmpty()) {
            // This status must not already hold a DIFFERENT lifecycle role
            // that this request isn't addressing — silently clearing it
            // here could drop the workspace to zero statuses holding that
            // role. Require the conflict to be resolved explicitly first
            // (move the other role to a different status).
            $conflicting = collect($lifecycleFlags)
                ->diff($flagsToMove)
                ->first(fn ($flag) => (bool) $orderStatus->{$flag});

            abort_if(
                $conflicting !== null,
                422,
                'Ta status že ima drugo izključujočo se vlogo. Najprej jo premakni na drug status.'
            );
        }

        if ($flagsToMove->isNotEmpty()) {
            DB::transaction(function () use ($orderStatus, $data, $flagsToMove) {
                foreach ($flagsToMove as $flag) {
                    OrderStatus::where('id', '!=', $orderStatus->id)->update([$flag => false]);
                }
                $orderStatus->update($data);
            });

            return back()->with('success', 'Status naročila posodobljen.');
        }

        $orderStatus->update($data);

        return back()->with('success', 'Status naročila posodobljen.');
    }

    public function destroy(Request $request, OrderStatus $orderStatus): RedirectResponse
    {
        abort_if(OrderStatus::query()->count() <= 1, 422, 'Delovni prostor mora imeti vsaj en status naročila.');

        abort_if(
            $orderStatus->is_default || $orderStatus->is_completed || $orderStatus->is_cancelled || $orderStatus->is_refunded,
            422,
            'Ta status je obvezen (privzet, zaključeno, preklicano ali vračilo) in ga ni mogoče izbrisati. Najprej premakni to oznako na drug status.'
        );

        $workspace = $request->user()->currentWorkspace;

        $data = $request->validate([
            'reassign_to' => ['nullable', 'string', Rule::exists('order_statuses', 'key')->where('workspace_id', $workspace->id)],
        ]);

        if ($orderStatus->orders()->exists()) {
            $reassignTo = $data['reassign_to'] ?? null;

            abort_if(
                ! $reassignTo || $reassignTo === $orderStatus->key,
                422,
                'Ta status je v uporabi. Izberi status, na katerega naj se prestavijo obstoječa naročila.'
            );

            abort_unless(
                OrderStatus::query()->where('key', $reassignTo)->exists(),
                422,
                'Izbrani status ne obstaja.'
            );

            DB::transaction(function () use ($orderStatus, $reassignTo) {
                $orderStatus->orders()->update(['status' => $reassignTo]);
                $orderStatus->delete();
            });

            return back()->with('success', 'Status naročila izbrisan, naročila prestavljena na nov status.');
        }

        $orderStatus->delete();

        return back()->with('success', 'Status naročila izbrisan.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:order_statuses,id',
        ]);

        $workspace = $request->user()->currentWorkspace;

        DB::transaction(function () use ($data, $workspace) {
            foreach ($data['ids'] as $index => $id) {
                OrderStatus::where('id', $id)->where('workspace_id', $workspace->id)->update(['sort_order' => $index]);
            }
        });

        return back();
    }

    private function uniqueKey(int $workspaceId, string $label): string
    {
        $base = Str::slug($label, '_') ?: 'status';
        $key = $base;
        $suffix = 1;

        while (OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspaceId)->where('key', $key)->exists()) {
            $key = "{$base}_{$suffix}";
            $suffix++;
        }

        return $key;
    }
}
