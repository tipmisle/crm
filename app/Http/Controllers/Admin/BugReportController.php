<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BugReportStatus;
use App\Http\Controllers\Controller;
use App\Models\BugReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BugReportController extends Controller
{
    public function index(Request $request): Response
    {
        $query = BugReport::query()->with(['workspace:id,name', 'user:id,name,email'])->latest('created_at');

        if ($status = $request->string('status')->trim()->value()) {
            $query->where('status', $status);
        }

        return Inertia::render('Admin/BugReports/Index', [
            'reports' => $query->paginate(50)->withQueryString(),
            'filters' => $request->only('status'),
        ]);
    }

    public function update(Request $request, BugReport $bugReport): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(BugReportStatus::class)],
        ]);

        $bugReport->update([
            'status' => $data['status'],
            'resolved_at' => $data['status'] === BugReportStatus::Resolved->value ? now() : null,
        ]);

        return back()->with('success', 'Status prijave je bil posodobljen.');
    }
}
