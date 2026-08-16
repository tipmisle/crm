<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PaymentStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentStatusController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => 'required|string|max:100',
            'color' => 'required|string|max:7',
            'bg' => 'required|string|max:7',
        ]);

        $workspace = $request->user()->currentWorkspace;

        PaymentStatus::create([
            'workspace_id' => $workspace->id,
            'key' => $this->uniqueKey($workspace->id, $data['label']),
            'label' => $data['label'],
            'color' => $data['color'],
            'bg' => $data['bg'],
            'sort_order' => (PaymentStatus::query()->max('sort_order') ?? -1) + 1,
        ]);

        return back()->with('success', 'Status plačila dodan.');
    }

    public function update(Request $request, PaymentStatus $paymentStatus): RedirectResponse
    {
        $data = $request->validate([
            'label' => 'sometimes|string|max:100',
            'color' => 'sometimes|string|max:7',
            'bg' => 'sometimes|string|max:7',
            'is_default' => 'sometimes|boolean',
            'is_deposit_default' => 'sometimes|boolean',
            'is_outstanding' => 'sometimes|boolean',
        ]);

        $exclusiveFlag = ! empty($data['is_default']) ? 'is_default' : (! empty($data['is_deposit_default']) ? 'is_deposit_default' : null);

        if ($exclusiveFlag) {
            DB::transaction(function () use ($paymentStatus, $data, $exclusiveFlag) {
                PaymentStatus::where('id', '!=', $paymentStatus->id)->update([$exclusiveFlag => false]);
                $paymentStatus->update($data);
            });

            return back()->with('success', 'Status plačila posodobljen.');
        }

        $paymentStatus->update($data);

        return back()->with('success', 'Status plačila posodobljen.');
    }

    public function destroy(PaymentStatus $paymentStatus): RedirectResponse
    {
        $inUse = \App\Models\Order::where('payment_status', $paymentStatus->key)->exists()
            || \App\Models\Appointment::where('payment_status', $paymentStatus->key)->exists();

        abort_if($inUse, 422, 'Tega statusa ni mogoče izbrisati, ker ga uporablja vsaj eno naročilo ali termin.');
        abort_if(PaymentStatus::query()->count() <= 1, 422, 'Delovni prostor mora imeti vsaj en status plačila.');

        $paymentStatus->delete();

        return back()->with('success', 'Status plačila izbrisan.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:payment_statuses,id',
        ]);

        $workspace = $request->user()->currentWorkspace;

        DB::transaction(function () use ($data, $workspace) {
            foreach ($data['ids'] as $index => $id) {
                PaymentStatus::where('id', $id)->where('workspace_id', $workspace->id)->update(['sort_order' => $index]);
            }
        });

        return back();
    }

    private function uniqueKey(int $workspaceId, string $label): string
    {
        $base = Str::slug($label, '_') ?: 'status';
        $key = $base;
        $suffix = 1;

        while (PaymentStatus::withoutGlobalScopes()->where('workspace_id', $workspaceId)->where('key', $key)->exists()) {
            $key = "{$base}_{$suffix}";
            $suffix++;
        }

        return $key;
    }
}
