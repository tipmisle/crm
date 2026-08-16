<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceStatusDefaults;
use Database\Seeders\BloomAndCrumbSeeder;
use Illuminate\Support\Str;

test('seeding a workspace that already has statuses does not duplicate or touch them', function () {
    [$workspace] = createWorkspaceWithUser();

    $before = OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count();

    WorkspaceStatusDefaults::seed($workspace);

    expect(OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count())->toBe($before);
});

test('deleted starter statuses are never recreated by a later seed call', function () {
    [$workspace] = createWorkspaceWithUser();

    OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->where('key', 'cancelled')->delete();
    $remaining = OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count();

    WorkspaceStatusDefaults::seed($workspace);

    expect(OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count())->toBe($remaining);
    expect(OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->where('key', 'cancelled')->exists())->toBeFalse();
});

test('order statuses and payment statuses are seeded independently', function () {
    [$workspace] = createWorkspaceWithUser();

    // Simulate a workspace that deleted every order status but kept its
    // payment statuses — the two lists must be backfilled independently.
    OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->delete();
    $paymentCountBefore = PaymentStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count();

    WorkspaceStatusDefaults::seed($workspace);

    expect(OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count())->toBe(9);
    expect(PaymentStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count())->toBe($paymentCountBefore);
});

test('a workspace that existed before the status tables gets backfilled by the console command', function () {
    // Simulate a pre-existing workspace created before WorkspaceStatusDefaults
    // was ever called — zero order/payment status rows, but with existing
    // Order/Appointment rows whose status values must survive untouched.
    $workspace = Workspace::create([
        'name' => 'Legacy Workspace',
        'slug' => 'legacy-'.Str::random(8),
        'timezone' => 'Europe/Ljubljana',
        'currency' => 'EUR',
    ]);

    $owner = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner']);

    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Legacy Customer']);

    $order = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Legacy order',
        'price' => 50,
        'status' => 'legacy_status',
        'payment_status' => 'legacy_payment',
    ]);

    expect(OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->exists())->toBeFalse();

    $this->artisan('workspaces:backfill-statuses')->assertSuccessful();

    expect(OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count())->toBe(9);
    expect(PaymentStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count())->toBe(5);

    // The legacy order's status values are untouched by the backfill.
    expect($order->fresh()->status)->toBe('legacy_status');
    expect($order->fresh()->payment_status)->toBe('legacy_payment');
});

test('the backfill command is idempotent and never re-seeds a workspace with existing statuses', function () {
    [$workspace] = createWorkspaceWithUser();

    OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->where('key', 'new')->delete();
    $countBefore = OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count();

    $this->artisan('workspaces:backfill-statuses')->assertSuccessful();

    expect(OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count())->toBe($countBefore);
    expect(OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->where('key', 'new')->exists())->toBeFalse();
});

test('the standalone dev seeder creates usable, workspace-scoped statuses', function () {
    (new BloomAndCrumbSeeder)->run();

    $workspace = Workspace::where('slug', 'belezka')->firstOrFail();

    expect(OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count())->toBe(9);
    expect(PaymentStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count())->toBe(5);

    // Orders the seeder creates reference real, resolvable statuses.
    $order = Order::withoutGlobalScopes()->where('workspace_id', $workspace->id)->firstOrFail();
    expect(OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->where('key', $order->status)->exists())->toBeTrue();
});
