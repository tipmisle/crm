<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'default_price' => 'nullable|numeric|min:0',
            'default_deposit_amount' => 'nullable|numeric|min:0',
        ]);

        Product::create($data);

        return back()->with('success', 'Produkt dodan.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'default_price' => 'nullable|numeric|min:0',
            'default_deposit_amount' => 'nullable|numeric|min:0',
            'active' => 'sometimes|boolean',
        ]);

        $product->update($data);

        return back()->with('success', 'Produkt posodobljen.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return back()->with('success', 'Produkt izbrisan.');
    }
}
