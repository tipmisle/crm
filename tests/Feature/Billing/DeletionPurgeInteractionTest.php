<?php

use App\Models\InvoiceSettings;
use App\Models\SalesDocument;
use App\Models\Workspace;
use App\Models\WorkspaceExport;
use App\Services\WorkspaceDeletionService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;
use Stripe\ApiRequestor;
use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\ClientInterface;
use Tests\Support\FakeStripeHttpClient;

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

test('purging a workspace with an active subscription cancels it as a non-blocking step', function () {
    [$workspace] = createWorkspaceWithSubscription('active');
    $subscription = $workspace->subscription(config('billing.subscription_name'));

    $fake = new FakeStripeHttpClient;
    ApiRequestor::setHttpClient($fake);

    app(WorkspaceDeletionService::class)->delete($workspace->fresh());

    expect(Workspace::find($workspace->id))->toBeNull();
    expect(Subscription::find($subscription->id))->toBeNull();

    // cancelNow() -> Stripe subscriptions.cancel, a DELETE-shaped call to
    // the subscriptions endpoint, was actually attempted before deletion.
    $cancelRequest = collect($fake->requests)->first(fn ($r) => str_contains($r['url'], '/v1/subscriptions/'));
    expect($cancelRequest)->not->toBeNull();
});

test('a stripe cancellation failure during purge never blocks the actual data purge', function () {
    [$workspace] = createWorkspaceWithSubscription('active');

    $throwing = new class implements ClientInterface
    {
        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1')
        {
            throw new ApiConnectionException('Simulated network failure.');
        }
    };
    ApiRequestor::setHttpClient($throwing);

    app(WorkspaceDeletionService::class)->delete($workspace->fresh());

    expect(Workspace::find($workspace->id))->toBeNull();
});

test('purging a workspace deletes every workspace-owned file: attachments, sales document PDFs, exports, and invoice logo', function () {
    Storage::fake('local');
    Storage::fake('public');

    [$workspace, $owner] = createWorkspaceWithUser();
    configureInvoicing($workspace, ['logo_path' => null]);
    [$order, $conversation] = createOrderWithConversation($workspace);

    $this->actingAs($owner)->post(route('orders.documents.store', $order), [
        'type' => 'invoice',
        'issued_at' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(8)->format('Y-m-d'),
        'recipient' => ['name' => 'Nina Novak'],
        'line_items' => [
            ['description' => 'Torta', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 100, 'vat_rate' => 22],
        ],
    ])->assertRedirect();

    $document = SalesDocument::where('order_id', $order->id)->firstOrFail();
    expect($document->pdf_path)->not->toBeNull();
    Storage::disk('local')->assertExists($document->pdf_path);

    $logoPath = 'invoice-logos/'.Str::random(20).'.png';
    Storage::disk('public')->put($logoPath, 'fake-logo-bytes');
    InvoiceSettings::where('workspace_id', $workspace->id)->update(['logo_path' => $logoPath]);

    $attachmentPath = "attachments/{$workspace->id}/".Str::random(20).'.jpg';
    Storage::disk('local')->put($attachmentPath, 'fake-attachment-bytes');
    $conversation->messages()->create([
        'sender_type' => 'customer',
        'body' => 'hi',
        'message_type' => 'text',
        'status' => 'sent',
        'sent_at' => now(),
        'metadata' => ['attachments' => [['source' => 'local', 'path' => $attachmentPath, 'type' => 'image']]],
    ]);

    $exportPath = 'exports/'.Str::random(20).'.zip';
    Storage::disk('local')->put($exportPath, 'fake-export-bytes');
    WorkspaceExport::create([
        'workspace_id' => $workspace->id,
        'requested_by_user_id' => $owner->id,
        'disk_path' => $exportPath,
        'status' => 'ready',
        'expires_at' => now()->addDay(),
    ]);

    app(WorkspaceDeletionService::class)->delete($workspace->fresh());

    expect(Workspace::find($workspace->id))->toBeNull();
    Storage::disk('local')->assertMissing($document->pdf_path);
    Storage::disk('local')->assertMissing($attachmentPath);
    Storage::disk('local')->assertMissing($exportPath);
    Storage::disk('public')->assertMissing($logoPath);
});

test('a workspace with no subscription purges cleanly without attempting a stripe call', function () {
    [$workspace] = createWorkspaceWithUser(withSubscription: false);

    $fake = new FakeStripeHttpClient;
    ApiRequestor::setHttpClient($fake);

    app(WorkspaceDeletionService::class)->delete($workspace->fresh());

    expect(Workspace::find($workspace->id))->toBeNull();
    expect($fake->requests)->toBeEmpty();
});
