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

        foreach ($roleFlags as $roleFlag) {
            if (array_key_exists($roleFlag, $data) && ! $data[$roleFlag]) {
                unset($data[$roleFlag]);
            }
        }

        $flagToMove = collect($roleFlags)->first(fn ($flag) => ! empty($data[$flag]));

        if ($flagToMove) {
            DB::transaction(function () use ($orderStatus, $data, $flagToMove) {
                OrderStatus::where('id', '!=', $orderStatus->id)->update([$flagToMove => false]);
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
