<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FeatureRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\FeatureRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FeatureRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $query = FeatureRequest::query()->with(['workspace:id,name', 'user:id,name,email'])->latest('created_at');

        if ($status = $request->string('status')->trim()->value()) {
            $query->where('status', $status);
        }

        return Inertia::render('Admin/FeatureRequests/Index', [
            'requests' => $query->paginate(50)->withQueryString(),
            'filters' => $request->only('status'),
        ]);
    }

    public function update(Request $request, FeatureRequest $featureRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(FeatureRequestStatus::class)],
        ]);

        $featureRequest->update(['status' => $data['status']]);

        return back()->with('success', 'Status predloga je bil posodobljen.');
    }
}
