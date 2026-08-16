<?php

use App\Models\SalesDocument;
use App\Services\Invoicing\SalesDocumentPdfService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

function issuanceAtomicityPayload(string $type): array
{
    return [
        'type' => $type,
        'issued_at' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(8)->format('Y-m-d'),
        'recipient' => ['name' => 'Nina Novak'],
        'line_items' => [
            ['description' => 'Torta', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 100, 'vat_rate' => 22],
        ],
    ];
}

function poisonPdfRender(): void
{
    $mock = Mockery::mock(SalesDocumentPdfService::class);
    $mock->shouldReceive('renderUpnQr')->andReturn(null);
    $mock->shouldReceive('render')->andThrow(new RuntimeException('simulated PDF render failure'));
    app()->instance(SalesDocumentPdfService::class, $mock);
}

function poisonLocalDiskPut(): void
{
    $mock = Mockery::mock(FilesystemAdapter::class);
    $mock->shouldReceive('put')->andReturn(false);
    app('filesystem')->set('local', $mock);
}

foreach (['invoice', 'proforma'] as $docType) {
    test("a PDF render failure during {$docType} issuance leaves no persisted document and no file", function () use ($docType) {
        [$workspace, $user] = createWorkspaceWithUser();
        configureInvoicing($workspace);
        [$order] = createOrderWithConversation($workspace);

        poisonPdfRender();

        $this->actingAs($user)->post(route('orders.documents.store', $order), issuanceAtomicityPayload($docType));

        expect(SalesDocument::where('order_id', $order->id)->where('type', $docType)->exists())->toBeFalse();
        expect(Storage::disk('local')->allFiles("invoices/{$workspace->id}"))->toBe([]);
    });

    test("a Storage failure during {$docType} issuance leaves no persisted document and does not consume a number for a phantom document", function () use ($docType) {
        [$workspace, $user] = createWorkspaceWithUser();
        configureInvoicing($workspace);
        [$order] = createOrderWithConversation($workspace);

        poisonLocalDiskPut();

        $this->actingAs($user)->post(route('orders.documents.store', $order), issuanceAtomicityPayload($docType));

        expect(SalesDocument::where('order_id', $order->id)->where('type', $docType)->exists())->toBeFalse();
    });

    test("a failure right after the {$docType} PDF is written but before commit leaves no orphan file and no document row", function () use ($docType) {
        [$workspace, $user] = createWorkspaceWithUser();
        configureInvoicing($workspace);
        [$order] = createOrderWithConversation($workspace);

        SalesDocument::updating(function ($model) {
            if ($model->isDirty('pdf_path')) {
                throw new RuntimeException('simulated post-write failure');
            }
        });

        try {
            $this->actingAs($user)->post(route('orders.documents.store', $order), issuanceAtomicityPayload($docType));
        } finally {
            SalesDocument::flushEventListeners();
        }

        expect(SalesDocument::where('order_id', $order->id)->where('type', $docType)->exists())->toBeFalse();
        expect(Storage::disk('local')->allFiles("invoices/{$workspace->id}"))->toBe([]);
    });
}

test('a PDF render failure during storno leaves the original issued, untouched, and no storno document persisted', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);

    $this->actingAs($user)->post(route('orders.documents.store', $order), issuanceAtomicityPayload('invoice'))
        ->assertRedirect();

    $original = SalesDocument::where('order_id', $order->id)->where('type', 'invoice')->firstOrFail();
    expect($original->status)->toBe('issued');

    poisonPdfRender();

    $this->actingAs($user)->post(route('documents.storno', $original), ['reason' => 'Test storno']);

    expect($original->fresh()->status)->toBe('issued');
    expect(SalesDocument::where('corrects_document_id', $original->id)->exists())->toBeFalse();
});

test('a Storage failure during storno leaves the original issued and no storno document persisted', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);

    $this->actingAs($user)->post(route('orders.documents.store', $order), issuanceAtomicityPayload('invoice'))
        ->assertRedirect();

    $original = SalesDocument::where('order_id', $order->id)->where('type', 'invoice')->firstOrFail();

    poisonLocalDiskPut();

    $this->actingAs($user)->post(route('documents.storno', $original), ['reason' => 'Test storno']);

    expect($original->fresh()->status)->toBe('issued');
    expect(SalesDocument::where('corrects_document_id', $original->id)->exists())->toBeFalse();
});

test('a failure marking the original reversed after the storno PDF is written leaves no orphan file and the original stays issued', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);

    $this->actingAs($user)->post(route('orders.documents.store', $order), issuanceAtomicityPayload('invoice'))
        ->assertRedirect();

    $original = SalesDocument::where('order_id', $order->id)->where('type', 'invoice')->firstOrFail();
    $filesBeforeStorno = Storage::disk('local')->allFiles("invoices/{$workspace->id}");

    SalesDocument::updating(function ($model) {
        if ($model->isDirty('status') && $model->status === 'reversed') {
            throw new RuntimeException('simulated failure marking original reversed');
        }
    });

    try {
        $this->actingAs($user)->post(route('documents.storno', $original), ['reason' => 'Test storno']);
    } finally {
        SalesDocument::flushEventListeners();
    }

    expect($original->fresh()->status)->toBe('issued');
    expect(SalesDocument::where('corrects_document_id', $original->id)->exists())->toBeFalse();
    expect(Storage::disk('local')->allFiles("invoices/{$workspace->id}"))->toBe($filesBeforeStorno);
});
