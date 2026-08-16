<?php

use App\Models\SalesDocument;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

function issueSalesDocument($testCase, $workspace, $user, $order): SalesDocument
{
    $testCase->actingAs($user)->post(route('orders.documents.store', $order), [
        'type' => 'invoice',
        'issued_at' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(8)->format('Y-m-d'),
        'recipient' => ['name' => 'Nina Novak'],
        'line_items' => [
            ['description' => 'Torta', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 100, 'vat_rate' => 22],
        ],
    ]);

    return SalesDocument::where('order_id', $order->id)->firstOrFail();
}

test('a user from the correct workspace can download the document', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);
    $document = issueSalesDocument($this, $workspace, $user, $order);

    $this->actingAs($user)->get(route('documents.download', $document))->assertOk();
});

test('a user from a different workspace cannot download the document', function () {
    [$workspaceA, $userA] = createWorkspaceWithUser();
    configureInvoicing($workspaceA);
    [$orderA] = createOrderWithConversation($workspaceA);
    $document = issueSalesDocument($this, $workspaceA, $userA, $orderA);

    [, $userB] = createWorkspaceWithUser();

    $this->actingAs($userB)->get(route('documents.download', $document))->assertStatus(404);
});

test('an anonymous user cannot download a document', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);
    $document = issueSalesDocument($this, $workspace, $user, $order);

    $this->app['auth']->forgetGuards();

    $this->get(route('documents.download', $document))->assertRedirect(route('login'));
});
