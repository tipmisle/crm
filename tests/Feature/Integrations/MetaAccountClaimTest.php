<?php

use App\Models\Channel;
use App\Models\Integration;
use Illuminate\Support\Facades\Http;

function pendingMetaAccountSession(int $integrationId, string $externalAccountId, string $displayName = 'Studio Photos'): array
{
    return [
        'integration_id' => $integrationId,
        'accounts' => [[
            'channel_type' => 'instagram',
            'external_account_id' => $externalAccountId,
            'display_name' => $displayName,
            'username' => 'studio.photos',
            'page_access_token' => 'test-page-token',
            'parent_page_id' => null,
        ]],
    ];
}

test('connecting a meta account already claimed by another workspace is rejected', function () {
    [$otherWorkspace] = createWorkspaceWithUser();
    createMetaChannel($otherWorkspace, 'instagram', 'ig_claimed');

    [$workspace, $user] = createWorkspaceWithUser();
    $integration = Integration::create([
        'workspace_id' => $workspace->id,
        'provider' => 'meta',
        'external_account_id' => 'meta_user_x',
        'status' => 'connected',
        'access_token' => 'token',
        'connected_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['meta_pending_accounts' => pendingMetaAccountSession($integration->id, 'ig_claimed')])
        ->post(route('integrations.meta.store'), ['external_account_ids' => ['ig_claimed']])
        ->assertSessionHas('error');

    // Nothing was connected in the requesting workspace.
    expect(Channel::withoutGlobalScopes()->where('workspace_id', $workspace->id)->where('external_account_id', 'ig_claimed')->exists())->toBeFalse();

    // The other workspace's claim is untouched.
    expect(Channel::withoutGlobalScopes()->where('workspace_id', $otherWorkspace->id)->where('external_account_id', 'ig_claimed')->where('status', 'connected')->exists())->toBeTrue();
});

test('connecting an unclaimed meta account succeeds', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);

    [$workspace, $user] = createWorkspaceWithUser();
    $integration = Integration::create([
        'workspace_id' => $workspace->id,
        'provider' => 'meta',
        'external_account_id' => 'meta_user_y',
        'status' => 'connected',
        'access_token' => 'token',
        'connected_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['meta_pending_accounts' => pendingMetaAccountSession($integration->id, 'ig_fresh')])
        ->post(route('integrations.meta.store'), ['external_account_ids' => ['ig_fresh']])
        ->assertSessionHas('success');

    expect(Channel::withoutGlobalScopes()->where('workspace_id', $workspace->id)->where('external_account_id', 'ig_fresh')->where('status', 'connected')->exists())->toBeTrue();
});
