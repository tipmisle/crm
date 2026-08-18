<?php

use App\Models\User;
use App\Models\WorkspaceMember;
use Laravel\Cashier\Subscription;

test('a subscription belongs to the workspace, not the user', function () {
    [$workspace, $owner] = createWorkspaceWithSubscription();
    $subscription = $workspace->subscription(config('billing.subscription_name'));

    expect($subscription)->toBeInstanceOf(Subscription::class);
    expect($subscription->workspace_id)->toBe($workspace->id);

    // A second member of the SAME workspace sees the SAME subscription —
    // it's not tied to whichever user happens to be logged in.
    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'member']);

    $this->actingAs($member);
    expect($workspace->fresh()->subscription(config('billing.subscription_name'))->id)->toBe($subscription->id);
});

test('a non-owner member cannot open the billing portal', function () {
    [$workspace, $owner] = createWorkspaceWithSubscription();
    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'member']);

    $this->actingAs($member)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.billing.portal'))
        ->assertForbidden();
});

test('opening the billing portal requires a recently confirmed password', function () {
    [$workspace, $owner] = createWorkspaceWithSubscription();

    $this->actingAs($owner)->get(route('settings.billing.portal'))
        ->assertRedirect(route('password.confirm.app'));
});

test('the owner can view billing settings', function () {
    [$workspace, $owner] = createWorkspaceWithSubscription();

    $this->actingAs($owner)->get(route('settings.billing.edit'))->assertOk();
});

test('deleting a non-owner member does not affect the workspace subscription', function () {
    [$workspace, $owner] = createWorkspaceWithSubscription();
    $subscription = $workspace->subscription(config('billing.subscription_name'));

    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    $membership = WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'member']);
    $membership->delete();

    expect($workspace->fresh()->subscription(config('billing.subscription_name'))->id)->toBe($subscription->id);
    expect($workspace->fresh()->subscribed(config('billing.subscription_name')))->toBeTrue();
});
