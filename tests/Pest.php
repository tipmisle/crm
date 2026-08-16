<?php

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\InvoiceSettings;
use App\Models\Order;
use App\Models\SupportAccessGrant;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceStatusDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;
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

/**
 * Defaults to an active subscription — the pre-billing-milestone baseline
 * every existing test suite assumes ("a normal, working workspace"). Pass
 * withSubscription: false for billing tests that specifically need an
 * unpaid/no-subscription workspace (see App\Http\Middleware\EnsureWorkspaceHasActiveSubscription).
 *
 * Also defaults to onboarding already completed, same reasoning — most
 * tests assume a normal workspace past first-run setup. Pass
 * onboardingCompleted: false for Onboarding feature tests.
 */
function createWorkspaceWithUser(array $userAttributes = [], bool $withSubscription = true, bool $onboardingCompleted = true): array
{
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => Str::random(10),
        'timezone' => 'Europe/Ljubljana',
        'currency' => 'EUR',
        'onboarding_completed_at' => $onboardingCompleted ? now() : null,
    ]);

    $user = User::factory()->create(array_merge([
        'current_workspace_id' => $workspace->id,
    ], $userAttributes));

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => 'owner',
    ]);

    // Mirrors RegisteredUserController::store()/DemoController::create() —
    // every real workspace has these seeded, so test workspaces must too.
    WorkspaceStatusDefaults::seed($workspace);

    if ($withSubscription) {
        attachSubscription($workspace, 'active');
    }

    return [$workspace, $user];
}

function attachSubscription(Workspace $workspace, string $status = 'active', array $overrides = []): Subscription
{
    if (! $workspace->stripe_id) {
        $workspace->forceFill(['stripe_id' => 'cus_test_'.Str::random(10)])->save();
    }

    return Subscription::create(array_merge([
        'workspace_id' => $workspace->id,
        'type' => config('billing.subscription_name'),
        'stripe_id' => 'sub_test_'.Str::random(10),
        'stripe_status' => $status,
        'stripe_price' => config('billing.monthly_price_id') ?? 'price_test',
        'quantity' => 1,
        'ends_at' => null,
    ], $overrides));
}

function createMetaChannel(
    Workspace $workspace,
    string $type = 'instagram',
    string $externalAccountId = 'ig_123',
    string $accessToken = 'test-page-token',
    string $status = 'connected'
): Channel {
    $integration = Integration::create([
        'workspace_id' => $workspace->id,
        'provider' => 'meta',
        'external_account_id' => 'meta_user_'.$workspace->id,
        'status' => 'connected',
        'access_token' => 'test-user-token',
        'connected_at' => now(),
    ]);

    return Channel::create([
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

function createPlatformAdmin(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'current_workspace_id' => null,
    ], $attributes));

    $user->forceFill(['is_platform_admin' => true])->save();

    return $user->fresh();
}

function createSupportGrant(
    Workspace $workspace,
    User $grantedBy,
    string $scope = 'workspace_content',
    int $minutes = 60
): SupportAccessGrant {
    return SupportAccessGrant::create([
        'workspace_id' => $workspace->id,
        'granted_by_user_id' => $grantedBy->id,
        'granted_at' => now(),
        'expires_at' => now()->addMinutes($minutes),
        'scope' => $scope,
    ]);
}

/**
 * @return array{0: Workspace, 1: User}
 */
function createWorkspaceWithSubscription(string $status = 'active', array $overrides = []): array
{
    [$workspace, $user] = createWorkspaceWithUser(withSubscription: false);

    attachSubscription($workspace, $status, $overrides);

    return [$workspace, $user];
}

/*
|--------------------------------------------------------------------------
| Invoicing test helpers
|--------------------------------------------------------------------------
*/

function configureInvoicing(Workspace $workspace, array $overrides = []): InvoiceSettings
{
    return InvoiceSettings::create(array_merge([
        'workspace_id' => $workspace->id,
        'company_name' => 'Test Obrt d.o.o.',
        'address_line' => 'Testna cesta 1',
        'postal_code' => '1000',
        'city' => 'Ljubljana',
        'country' => 'Slovenija',
        'tax_number' => 'SI12345678',
        'vat_registered' => true,
        'iban' => 'SI56020170014356205',
        'bank_name' => 'Test banka',
        'default_payment_deadline_days' => 8,
        'invoice_prefix' => now()->format('Y').'-',
        'invoice_next_number' => 1,
        'proforma_prefix' => 'P-'.now()->format('Y').'-',
        'proforma_next_number' => 1,
    ], $overrides));
}

/**
 * Reuses an existing Meta channel for the workspace if one was already
 * created (e.g. by an earlier call for the same workspace) — createMetaChannel()
 * gives its Integration a fixed workspace-scoped external_account_id, so a
 * second call for the same workspace would otherwise violate its unique
 * constraint.
 *
 * @return array{0: Order, 1: Conversation, 2: Channel}
 */
function createOrderWithConversation(Workspace $workspace, array $customerOverrides = []): array
{
    $channel = Channel::where('workspace_id', $workspace->id)->first() ?? createMetaChannel($workspace);

    $customer = Customer::create(array_merge([
        'workspace_id' => $workspace->id,
        'full_name' => 'Nina Novak',
        'email' => 'nina@example.com',
    ], $customerOverrides));

    $conversation = Conversation::create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'customer_id' => $customer->id,
        'external_conversation_id' => 'sender_'.Str::random(8),
        'status' => 'order_confirmed',
    ]);

    $order = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'conversation_id' => $conversation->id,
        'channel_id' => $channel->id,
        'title' => 'Rojstnodnevna torta',
        'price' => 100,
        'amount_paid' => 40,
        'payment_status' => 'unpaid',
        'status' => 'new',
    ]);

    return [$order, $conversation, $channel];
}
