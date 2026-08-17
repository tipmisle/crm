<?php

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Order;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\FollowUpDue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

afterEach(function () {
    Carbon::setTestNow();
});

test('a new real workspace defaults to Europe/Ljubljana rather than UTC', function () {
    $user = User::factory()->create();

    $workspace = Workspace::create([
        'name' => "{$user->name}'s Business",
        'slug' => Str::slug($user->name).'-'.Str::random(6),
    ]);

    expect($workspace->timezone)->toBe('Europe/Ljubljana');
});

test('an explicitly configured workspace timezone is not overridden by the default', function () {
    $workspace = Workspace::create([
        'name' => 'Custom TZ Biz',
        'slug' => 'custom-tz-'.Str::random(6),
        'timezone' => 'America/New_York',
    ]);

    expect($workspace->timezone)->toBe('America/New_York');
});

test('a follow-up saved for 10:00 local time converts to the correct UTC instant during summer time', function () {
    // Europe/Ljubljana is UTC+2 in August (CEST).
    [$workspace, $owner] = createWorkspaceWithUser();
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Ana']);

    $this->actingAs($owner)->post(route('follow-ups.store'), [
        'followable_type' => 'App\\Models\\Customer',
        'followable_id' => $customer->id,
        'note' => 'Pokliči',
        'due_at' => '2026-08-17T10:00',
    ])->assertRedirect();

    $followUp = FollowUp::where('followable_id', $customer->id)->firstOrFail();

    expect($followUp->due_at->utc()->format('Y-m-d H:i'))->toBe('2026-08-17 08:00');
});

test('a follow-up saved for 10:00 local time converts to the correct UTC instant during winter time', function () {
    // Europe/Ljubljana is UTC+1 in January (CET) — DST must be handled by
    // the IANA timezone, not a hardcoded offset.
    [$workspace, $owner] = createWorkspaceWithUser();
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Ana']);

    $this->actingAs($owner)->post(route('follow-ups.store'), [
        'followable_type' => 'App\\Models\\Customer',
        'followable_id' => $customer->id,
        'note' => 'Pokliči',
        'due_at' => '2026-01-17T10:00',
    ])->assertRedirect();

    $followUp = FollowUp::where('followable_id', $customer->id)->firstOrFail();

    expect($followUp->due_at->utc()->format('Y-m-d H:i'))->toBe('2026-01-17 09:00');
});

test('"due today" reflects the workspace local calendar date, not the server UTC date', function () {
    // 23:30 UTC on Aug 17 is already 01:30 on Aug 18 in Europe/Ljubljana
    // (UTC+2 in summer) — "today" for this workspace is the 18th. Built
    // from an explicit UTC instant (not a bare string) since this app's
    // own default timezone may itself be non-UTC — see config('app.timezone').
    Carbon::setTestNow(Carbon::parse('2026-08-17 23:30:00', 'UTC'));

    [$workspace, $user] = createWorkspaceWithUser();

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $dueLocalToday = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Torta',
        'price' => 60,
        'status' => 'confirmed',
        'due_date' => '2026-08-18',
    ]);

    // Due on the UTC calendar date instead — must NOT count as "today".
    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Druga torta',
        'price' => 40,
        'status' => 'confirmed',
        'due_date' => '2026-08-17',
    ]);

    $page = $this->actingAs($user)->get(route('dashboard'));

    $dueTodayItem = collect($page->inertiaProps('attention'))->firstWhere('key', 'due_today');

    expect($dueTodayItem['count'])->toBe(1);

    $filtered = $this->actingAs($user)->get($dueTodayItem['href']);
    $filtered->assertInertia(fn ($p) => $p
        ->has('orders.data', 1)
        ->where('orders.data.0.id', $dueLocalToday->id)
    );
});

test('Today stats use the workspace local day boundary, not the server UTC day boundary', function () {
    // 00:30 UTC on Aug 18 is 02:30 local (Europe/Ljubljana, UTC+2 in
    // summer) — well within Aug 18 locally, so an order created at this
    // instant must count toward "today"'s revenue for the workspace.
    Carbon::setTestNow(Carbon::parse('2026-08-18 00:30:00', 'UTC'));

    [$workspace, $user] = createWorkspaceWithUser();

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    // created_at will be stamped "now" (2026-08-18 00:30:00 UTC) by Eloquent,
    // which is 2026-08-18 02:30 local — counts toward today's revenue.
    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Torta ob polnoči',
        'price' => 75,
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page->where('stats.revenue.value', 75));
});

test('a due follow-up reminder fires at the correct UTC instant for a local 10:00 due time', function () {
    Notification::fake();

    [$workspace, $owner] = createWorkspaceWithUser();
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Ana']);

    $followUp = FollowUp::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'followable_type' => Customer::class,
        'followable_id' => $customer->id,
        'note' => 'Pokliči',
        // 10:00 Europe/Ljubljana in August (CEST, UTC+2) == 08:00 UTC.
        // Re-expressed in the app's own storage timezone, exactly like
        // FollowUpController::store() does, so it round-trips correctly
        // through Eloquent's datetime cast.
        'due_at' => Carbon::parse('2026-08-17T10:00', 'Europe/Ljubljana')->setTimezone(config('app.timezone')),
    ]);

    // One minute before the correct UTC instant: must not fire yet.
    Carbon::setTestNow(Carbon::parse('2026-08-17 07:59:00', 'UTC'));
    $this->artisan('app:send-due-follow-up-reminders');
    Notification::assertNothingSent();

    // At the correct UTC instant: must fire.
    Carbon::setTestNow(Carbon::parse('2026-08-17 08:00:00', 'UTC'));
    $this->artisan('app:send-due-follow-up-reminders');
    Notification::assertSentTo($owner, FollowUpDue::class);

    expect($followUp->fresh()->notified_at)->not->toBeNull();
});

test('an order due date does not shift a day when the workspace is ahead of UTC', function () {
    // Guards against date-only fields being routed through any instant/UTC
    // conversion — due_date must round-trip exactly as stored.
    Carbon::setTestNow('2026-08-17 23:00:00');

    [$workspace, $user] = createWorkspaceWithUser();

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $order = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Torta',
        'price' => 60,
        'status' => 'confirmed',
        'due_date' => '2026-08-20',
    ]);

    expect($order->fresh()->due_date->format('Y-m-d'))->toBe('2026-08-20');

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertInertia(fn ($page) => $page->where('order.due_date', '2026-08-20'));
});
