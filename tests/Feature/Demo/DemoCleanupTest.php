<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Artisan;

function makeDemoWorkspace(string $expiresRelative): array
{
    $workspace = Workspace::create([
        'name' => 'Cleanup Test Biz',
        'slug' => 'cleanup-test-'.uniqid(),
        'is_demo' => true,
        'demo_variant' => 'services',
        'demo_expires_at' => now()->{$expiresRelative === 'past' ? 'subHour' : 'addHours'}($expiresRelative === 'past' ? 1 : 4),
    ]);

    $user = User::factory()->create(['current_workspace_id' => $workspace->id, 'is_demo' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'owner']);

    return [$workspace, $user];
}

test('demos:cleanup deletes only expired demo workspaces and their demo users', function () {
    [$expiredWorkspace, $expiredUser] = makeDemoWorkspace('past');
    [$activeWorkspace, $activeUser] = makeDemoWorkspace('future');
    [$realWorkspace, $realUser] = createWorkspaceWithUser();

    Artisan::call('demos:cleanup');

    expect(Workspace::find($expiredWorkspace->id))->toBeNull();
    expect(User::find($expiredUser->id))->toBeNull();

    expect(Workspace::find($activeWorkspace->id))->not->toBeNull();
    expect(User::find($activeUser->id))->not->toBeNull();

    expect(Workspace::find($realWorkspace->id))->not->toBeNull();
    expect(User::find($realUser->id))->not->toBeNull();
});

test('demos:cleanup cascades away the expired workspace data', function () {
    [$workspace, $user] = makeDemoWorkspace('past');

    $channel = \App\Models\Channel::create([
        'workspace_id' => $workspace->id,
        'type' => 'instagram',
        'display_name' => 'x',
        'status' => 'not_connected',
    ]);

    $customer = \App\Models\Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Test Customer',
    ]);

    Artisan::call('demos:cleanup');

    expect(\App\Models\Channel::withoutGlobalScopes()->find($channel->id))->toBeNull();
    expect(\App\Models\Customer::withoutGlobalScopes()->find($customer->id))->toBeNull();
});
