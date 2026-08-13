<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderNoteController extends Controller
{
    public function store(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $order->notes()->create([
            'user_id' => auth()->id(),
            'body' => $data['body'],
        ]);

        return back();
    }
}
