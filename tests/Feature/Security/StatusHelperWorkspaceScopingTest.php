<?php

use App\Models\AppointmentStatus;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;

/**
 * OrderStatus/AppointmentStatus/PaymentStatus's semantic static helpers
 * (openExclusionKeys(), defaultKey(), etc.) are called from domain/service
 * code, not just authenticated HTTP controllers. Called with no explicit
 * workspace id they rely on BelongsToWorkspace's auth-derived global scope
 * (unchanged, existing behavior) — but called from a CLI/job/service
 * context with no authenticated user, that scope silently omits itself
 * rather than leaking every workspace's rows, so callers that already know
 * the workspace must pass it explicitly instead. This proves that path is
 * correctly isolated.
 */
test('order status semantic helpers scoped by explicit workspace id never see another workspace, even with no authenticated user', function () {
    [$workspaceA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    OrderStatus::where('workspace_id', $workspaceB->id)->where('key', 'cancelled')
        ->update(['key' => 'workspace_b_only_cancelled']);

    expect(auth()->check())->toBeFalse();

    $keysA = OrderStatus::cancelledKeys($workspaceA->id);
    $keysB = OrderStatus::cancelledKeys($workspaceB->id);

    expect($keysA)->toContain('cancelled');
    expect($keysA)->not->toContain('workspace_b_only_cancelled');
    expect($keysB)->toContain('workspace_b_only_cancelled');
    expect($keysB)->not->toContain('cancelled');

    expect(OrderStatus::defaultKey($workspaceA->id))->not->toBeNull();
    expect(OrderStatus::openExclusionKeys($workspaceA->id))->not->toBeEmpty();
});

test('appointment status semantic helpers scoped by explicit workspace id never see another workspace', function () {
    [$workspaceA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    AppointmentStatus::where('workspace_id', $workspaceB->id)->where('key', 'no_show')
        ->update(['key' => 'workspace_b_only_no_show']);

    $keysA = AppointmentStatus::noShowKeys($workspaceA->id);
    $keysB = AppointmentStatus::noShowKeys($workspaceB->id);

    expect($keysA)->toContain('no_show');
    expect($keysA)->not->toContain('workspace_b_only_no_show');
    expect($keysB)->toContain('workspace_b_only_no_show');
    expect($keysB)->not->toContain('no_show');
});

test('payment status semantic helpers scoped by explicit workspace id never see another workspace', function () {
    [$workspaceA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    PaymentStatus::where('workspace_id', $workspaceB->id)->where('key', 'paid')
        ->update(['key' => 'workspace_b_only_paid']);

    $keysA = PaymentStatus::outstandingKeys($workspaceA->id);
    expect(PaymentStatus::defaultKey($workspaceA->id))->not->toBeNull();
    expect(PaymentStatus::defaultKey($workspaceB->id))->not->toBeNull();

    // Sanity: workspace B's renamed "paid" key never leaks into A's results.
    expect($keysA)->not->toContain('workspace_b_only_paid');
});
