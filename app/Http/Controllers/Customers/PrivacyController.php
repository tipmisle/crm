<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Services\CustomerErasureService;
use App\Services\CustomerExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Data-subject-request tooling a workspace member uses on behalf of a
 * business customer — not a public self-service form (Beležka is a
 * processor for this data, the workspace owner's business is the
 * controller). Gated on workspace membership only: there is no real
 * second WorkspaceMember role in production today, and nothing else in
 * the CRM (OrderController, AppointmentController) gates by role either.
 * See docs/data-lifecycle.md Part 13-16.
 */
class PrivacyController extends Controller
{
    public function exportData(Customer $customer, CustomerExportService $service): StreamedResponse
    {
        AuditLog::record('privacy.customer.export', request(), request()->user()->current_workspace_id, $customer);

        return $service->build($customer);
    }

    public function eraseData(Request $request, Customer $customer, CustomerErasureService $service): RedirectResponse
    {
        $request->validate([
            'confirm' => ['required', 'accepted'],
        ]);

        $service->erase($customer);

        AuditLog::record('privacy.customer.erased', $request, $request->user()->current_workspace_id, $customer);

        return back()->with('success', 'Podatki stranke so bili izbrisani/anonimizirani.');
    }
}
