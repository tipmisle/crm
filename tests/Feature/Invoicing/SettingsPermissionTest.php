<?php

use App\Models\InvoiceSettings;
use App\Models\User;
use App\Models\WorkspaceMember;

test('a non-owner member cannot update invoice settings', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'member']);

    $this->actingAs($member)->patch(route('settings.invoicing.update'), [
        'default_payment_deadline_days' => 14,
        'invoice_prefix' => '2026-',
        'proforma_prefix' => 'P-2026-',
    ])->assertForbidden();
});

test('the owner can update invoice settings', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    configureInvoicing($workspace);

    $this->actingAs($owner)->patch(route('settings.invoicing.update'), [
        'company_name' => 'Moje Podjetje d.o.o.',
        'default_payment_deadline_days' => 14,
        'invoice_prefix' => '2026-',
        'proforma_prefix' => 'P-2026-',
    ])->assertRedirect();

    expect(InvoiceSettings::where('workspace_id', $workspace->id)->first()->company_name)->toBe('Moje Podjetje d.o.o.');
});

test('a non-owner member cannot change the invoice logo', function () {
    [$workspace] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'member']);

    $this->actingAs($member)->delete(route('settings.invoicing.logo.destroy'))->assertForbidden();
});
