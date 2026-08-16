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
