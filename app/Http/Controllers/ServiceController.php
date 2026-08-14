<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'default_duration_minutes' => 'required|integer|min:5',
            'default_price' => 'nullable|numeric|min:0',
            'default_deposit_amount' => 'nullable|numeric|min:0',
        ]);

        Service::create($data);

        return back()->with('success', 'Storitev dodana.');
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'default_duration_minutes' => 'sometimes|integer|min:5',
            'default_price' => 'nullable|numeric|min:0',
            'default_deposit_amount' => 'nullable|numeric|min:0',
            'active' => 'sometimes|boolean',
        ]);

        $service->update($data);

        return back()->with('success', 'Storitev posodobljena.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return back()->with('success', 'Storitev izbrisana.');
    }
}
