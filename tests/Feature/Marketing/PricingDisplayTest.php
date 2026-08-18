<?php

test('marketing home page has no hardcoded price placeholder in its source', function () {
    $source = file_get_contents(resource_path('js/Pages/Marketing/Home.vue'));

    expect($source)->not->toContain("'19 €'");
    expect($source)->not->toContain('MONTHLY_PRICE');
});

test('marketing home page renders the server-configured display price', function () {
    config(['billing.display_price' => '29 €', 'billing.billing_period_label' => 'mesečno', 'billing.display_price_vat_included' => true]);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('displayPrice', '29 €')
        ->where('billingPeriodLabel', 'mesečno')
        ->where('displayPriceVatIncluded', true)
    );
});

test('marketing home page omits the price line entirely when unset', function () {
    config(['billing.display_price' => null]);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('displayPrice', null));
});

test('activation page and marketing page read the price from the same config source', function () {
    config(['billing.display_price' => '42 €', 'billing.display_price_vat_included' => false]);

    [$workspace, $user] = createWorkspaceWithUser(withSubscription: false);

    $home = $this->get(route('home'));
    $home->assertInertia(fn ($page) => $page->where('displayPrice', '42 €'));

    $activate = $this->actingAs($user)->get(route('billing.activate'));
    $activate->assertInertia(fn ($page) => $page
        ->where('displayPrice', '42 €')
        ->where('displayPriceVatIncluded', false)
    );
});

test('marketing home page and activation page both render the finalized launch price and VAT-inclusive treatment', function () {
    config([
        'billing.display_price' => '9,90 €',
        'billing.billing_period_label' => 'mesečno',
        'billing.display_price_vat_included' => true,
    ]);

    [$workspace, $user] = createWorkspaceWithUser(withSubscription: false);

    $home = $this->get(route('home'));
    $home->assertOk();
    $home->assertInertia(fn ($page) => $page
        ->where('displayPrice', '9,90 €')
        ->where('billingPeriodLabel', 'mesečno')
        ->where('displayPriceVatIncluded', true)
    );

    $activate = $this->actingAs($user)->get(route('billing.activate'));
    $activate->assertOk();
    $activate->assertInertia(fn ($page) => $page
        ->where('displayPrice', '9,90 €')
        ->where('billingPeriodLabel', 'mesečno')
        ->where('displayPriceVatIncluded', true)
    );
});
