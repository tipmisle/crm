<?php

$routes = [
    'legal.terms' => 'terms_version',
    'legal.privacy' => 'privacy_version',
    'legal.cookies' => 'cookie_version',
    'legal.dpa' => 'dpa_version',
];

foreach ($routes as $routeName => $versionKey) {
    test("{$routeName} is accessible when logged out and shows the configured version", function () use ($routeName, $versionKey) {
        $response = $this->get(route($routeName));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('legal.'.$versionKey, config("legal.{$versionKey}")));
    });

    test("{$routeName} is accessible when logged in (no auth redirect)", function () use ($routeName) {
        [, $user] = createWorkspaceWithUser();

        $this->actingAs($user)->get(route($routeName))->assertOk();
    });
}

test('provider info page is accessible when logged out', function () {
    $this->get(route('legal.provider'))->assertOk();
});

test('provider info page is accessible when logged in', function () {
    [, $user] = createWorkspaceWithUser();

    $this->actingAs($user)->get(route('legal.provider'))->assertOk();
});

test('subprocessors page is accessible when logged out', function () {
    $this->get(route('legal.subprocessors'))->assertOk();
});

test('provider info page renders without a fake placeholder when config is unset', function () {
    config(['legal.company_name' => null, 'legal.registered_address' => null]);

    $response = $this->get(route('legal.provider'));

    $response->assertOk();
    $response->assertDontSee('NEEDS OWNER INPUT');
    $response->assertDontSee('TODO');
});

test('provider info page renders the finalized owner-supplied company identity', function () {
    config([
        'legal.company_name' => 'Web8, Josip Rajković s.p.',
        'legal.company_legal_form' => 'samostojni podjetnik (s.p.)',
        'legal.registered_address' => 'Zelena pot 3, 1241 Kamnik, Slovenija',
        'legal.registration_number' => '8829888000',
        'legal.tax_number' => '30631564',
        'legal.vat_registered' => true,
        'legal.vat_number' => 'SI30631564',
        'legal.legal_email' => 'info@belezka.com',
        'legal.dpo_contact' => null,
        'legal.competent_court' => 'Okrožno sodišče v Ljubljani',
    ]);

    $response = $this->get(route('legal.provider'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('legal.company_name', 'Web8, Josip Rajković s.p.')
        ->where('legal.company_legal_form', 'samostojni podjetnik (s.p.)')
        ->where('legal.registered_address', 'Zelena pot 3, 1241 Kamnik, Slovenija')
        ->where('legal.registration_number', '8829888000')
        ->where('legal.tax_number', '30631564')
        ->where('legal.vat_registered', true)
        ->where('legal.vat_number', 'SI30631564')
        ->where('legal.legal_email', 'info@belezka.com')
        ->where('legal.competent_court', 'Okrožno sodišče v Ljubljani')
    );
});

test('provider info page omits the DPO row when no DPO is configured', function () {
    config([
        'legal.company_name' => 'Web8',
        'legal.registered_address' => 'Zelena pot 3, 1241 Kamnik, Slovenija',
        'legal.registration_number' => '8829888000',
        'legal.tax_number' => '30631564',
        'legal.legal_email' => 'info@belezka.com',
        'legal.dpo_contact' => null,
    ]);

    $response = $this->get(route('legal.provider'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('legal.dpo_contact', null));
});

test('terms page renders the configured competent court instead of hardcoded text', function () {
    config(['legal.competent_court' => 'Okrožno sodišče v Ljubljani']);

    $response = $this->get(route('legal.terms'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('legal.competent_court', 'Okrožno sodišče v Ljubljani'));
});
