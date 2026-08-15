<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        return Inertia::render('Catalog/Index', [
            'products' => $workspace->orders_enabled
                ? Product::orderBy('name')->get()
                : [],
            'services' => $workspace->appointments_enabled
                ? Service::orderBy('name')->get()
                : [],
        ]);
    }
}
