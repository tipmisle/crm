<?php

use App\Models\Customer;
use App\Services\CustomerErasureService;

test('erasing a customer also anonymizes B2B fields', function () {
    [$workspace] = createWorkspaceWithUser();

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Podjetje d.o.o.',
        'is_business' => true,
        'company_name' => 'Podjetje d.o.o.',
        'vat_registered' => true,
        'tax_number' => 'SI12345678',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    app(CustomerErasureService::class)->erase($customer);

    $customer->refresh();

    expect($customer->full_name)->toBe('Izbrisana stranka');
    expect($customer->company_name)->toBeNull();
    expect($customer->is_business)->toBeFalse();
    expect($customer->vat_registered)->toBeFalse();
    expect($customer->tax_number)->toBeNull();
});
