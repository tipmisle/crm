<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Messaging integration test helpers
|--------------------------------------------------------------------------
*/

function createWorkspaceWithUser(array $userAttributes = []): array
{
    $workspace = \App\Models\Workspace::create([
        'name' => 'Test Workspace',
        'slug' => \Illuminate\Support\Str::random(10),
        'timezone' => 'Europe/Ljubljana',
        'currency' => 'EUR',
    ]);

    $user = \App\Models\User::factory()->create(array_merge([
        'current_workspace_id' => $workspace->id,
    ], $userAttributes));

    \App\Models\WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => 'owner',
    ]);

    return [$workspace, $user];
}

function createMetaChannel(
    \App\Models\Workspace $workspace,
    string $type = 'instagram',
    string $externalAccountId = 'ig_123',
    string $accessToken = 'test-page-token',
    string $status = 'connected'
): \App\Models\Channel {
    $integration = \App\Models\Integration::create([
        'workspace_id' => $workspace->id,
        'provider' => 'meta',
        'external_account_id' => 'meta_user_'.$workspace->id,
        'status' => 'connected',
        'access_token' => 'test-user-token',
        'connected_at' => now(),
    ]);

    return \App\Models\Channel::create([
        'workspace_id' => $workspace->id,
        'integration_id' => $integration->id,
        'type' => $type,
        'external_account_id' => $externalAccountId,
        'display_name' => 'Test Account',
        'handle' => '@test',
        'status' => $status,
        'connected_at' => now(),
        'access_token' => $accessToken,
    ]);
}
