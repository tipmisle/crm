<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\OrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        ]);

        if (! empty($data['is_default'])) {
            DB::transaction(function () use ($orderStatus, $data) {
                OrderStatus::where('id', '!=', $orderStatus->id)->update(['is_default' => false]);
                $orderStatus->update($data);
            });

            return back()->with('success', 'Status naročila posodobljen.');
        }

        $orderStatus->update($data);

        return back()->with('success', 'Status naročila posodobljen.');
    }

    public function destroy(OrderStatus $orderStatus): RedirectResponse
    {
        abort_if($orderStatus->orders()->exists(), 422, 'Tega statusa ni mogoče izbrisati, ker ga uporablja vsaj eno naročilo.');
        abort_if(OrderStatus::query()->count() <= 1, 422, 'Delovni prostor mora imeti vsaj en status naročila.');

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
