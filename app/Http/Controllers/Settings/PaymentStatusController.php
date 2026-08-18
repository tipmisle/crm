<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\PaymentStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            'is_paid' => 'sometimes|boolean',
            'is_refunded' => 'sometimes|boolean',
        ]);

        // The is_default/is_paid/is_refunded FLAG is fixed (protected from
        // deletion and from being duplicated onto another status below) —
        // but the user-facing label/color is not a frozen product identity.
        // "Neplačano"/"Plačano"/"Vračilo" are just the seeded starting
        // labels; same as order/appointment statuses, the owner can rename
        // or recolor any status regardless of which semantic role it holds.

        // is_default/is_paid/is_refunded are each a single-status role
        // (radio buttons in the UI, never unchecked directly) — a workspace
        // must always have exactly one status filling each role, so a false
        // value for one of these is a no-op rather than leaving zero
        // statuses flagged.
        foreach (['is_default', 'is_paid', 'is_refunded'] as $mandatoryFlag) {
            if (array_key_exists($mandatoryFlag, $data) && ! $data[$mandatoryFlag]) {
                unset($data[$mandatoryFlag]);
            }
        }

        // is_default/is_paid/is_refunded describe mutually exclusive
        // payment states — a status can't simultaneously BE "the default
        // unpaid status" and "the paid status". is_deposit_default is
        // independent and optional/single where set (see class docblock).
        $exclusiveRoleFlags = ['is_default', 'is_paid', 'is_refunded'];
        $allSingletonFlags = ['is_default', 'is_deposit_default', 'is_paid', 'is_refunded'];

        $flagsToMove = collect($allSingletonFlags)->filter(fn ($flag) => ! empty($data[$flag]))->values();

        $requestedExclusiveFlags = $flagsToMove->intersect($exclusiveRoleFlags);

        abort_if(
            $requestedExclusiveFlags->count() > 1,
            422,
            'Status ne more hkrati imeti več izključujočih se vlog (neplačano, plačano, vračilo).'
        );

        if ($requestedExclusiveFlags->isNotEmpty()) {
            // This status must not already hold a DIFFERENT exclusive role
            // that this request isn't addressing — silently clearing it
            // here could drop the workspace to zero statuses holding that
            // role. Require the conflict to be resolved explicitly first
            // (move the other role to a different status). See
            // OrderStatusController::update() for the same pattern.
            $conflicting = collect($exclusiveRoleFlags)
                ->diff($flagsToMove)
                ->first(fn ($flag) => (bool) $paymentStatus->{$flag});

            abort_if(
                $conflicting !== null,
                422,
                'Ta status že ima drugo izključujočo se vlogo. Najprej jo premakni na drug status.'
            );
        }

        if ($flagsToMove->isNotEmpty()) {
            DB::transaction(function () use ($paymentStatus, $data, $flagsToMove) {
                foreach ($flagsToMove as $flag) {
                    PaymentStatus::where('id', '!=', $paymentStatus->id)->update([$flag => false]);
                }
                $paymentStatus->update($data);
            });

            return back()->with('success', 'Status plačila posodobljen.');
        }

        $paymentStatus->update($data);

        return back()->with('success', 'Status plačila posodobljen.');
    }

    public function destroy(Request $request, PaymentStatus $paymentStatus): RedirectResponse
    {
        abort_if(PaymentStatus::query()->count() <= 1, 422, 'Delovni prostor mora imeti vsaj en status plačila.');

        abort_if(
            $paymentStatus->is_default || $paymentStatus->is_paid || $paymentStatus->is_refunded,
            422,
            'Ta status je obvezen (neplačano, plačano ali vračilo) in ga ni mogoče izbrisati. Najprej premakni to oznako na drug status.'
        );

        $workspace = $request->user()->currentWorkspace;

        $data = $request->validate([
            'reassign_to' => ['nullable', 'string', Rule::exists('payment_statuses', 'key')->where('workspace_id', $workspace->id)],
        ]);

        $inUse = Order::where('payment_status', $paymentStatus->key)->exists()
            || Appointment::where('payment_status', $paymentStatus->key)->exists();

        if ($inUse) {
            $reassignTo = $data['reassign_to'] ?? null;

            abort_if(
                ! $reassignTo || $reassignTo === $paymentStatus->key,
                422,
                'Ta status je v uporabi. Izberi status, na katerega naj se prestavijo obstoječa naročila/termini.'
            );

            abort_unless(
                PaymentStatus::query()->where('key', $reassignTo)->exists(),
                422,
                'Izbrani status ne obstaja.'
            );

            DB::transaction(function () use ($paymentStatus, $reassignTo) {
                Order::where('payment_status', $paymentStatus->key)->update(['payment_status' => $reassignTo]);
                Appointment::where('payment_status', $paymentStatus->key)->update(['payment_status' => $reassignTo]);
                $paymentStatus->delete();
            });

            return back()->with('success', 'Status plačila izbrisan, obstoječi zapisi prestavljeni na nov status.');
        }

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
