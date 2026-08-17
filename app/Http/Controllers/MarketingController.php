<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MarketingController extends Controller
{
    public function home(): Response|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Marketing/Home', [
            'displayPrice' => config('billing.display_price'),
            'billingPeriodLabel' => config('billing.billing_period_label'),
            'displayPriceVatIncluded' => config('billing.display_price_vat_included'),
        ]);
    }
}
