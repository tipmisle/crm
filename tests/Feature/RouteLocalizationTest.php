<?php

use App\Models\Customer;
use Illuminate\Support\Facades\Route;

test('politika-zasebnosti is publicly accessible, returns 200, and never redirects to login', function () {
    $response = $this->get('/politika-zasebnosti');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Legal/Privacy'));
});

test('canonical Slovenian public legal URLs all return 200', function () {
    foreach ([
        '/pogoji-poslovanja',
        '/politika-zasebnosti',
        '/politika-piskotkov',
        '/dogovor-o-obdelavi-osebnih-podatkov',
        '/podatki-o-ponudniku',
        '/podobdelovalci',
    ] as $path) {
        $this->get($path)->assertOk();
    }
});

test('legacy legal GET paths permanently redirect to the canonical Slovenian URLs', function () {
    $this->get('/zasebnost')->assertRedirect('/politika-zasebnosti')->assertStatus(301);
    $this->get('/piskotki')->assertRedirect('/politika-piskotkov')->assertStatus(301);
    $this->get('/obdelava-osebnih-podatkov')->assertRedirect('/dogovor-o-obdelavi-osebnih-podatkov')->assertStatus(301);
});

test('legal route names generate the new canonical paths', function () {
    expect(route('legal.terms', absolute: false))->toBe('/pogoji-poslovanja');
    expect(route('legal.privacy', absolute: false))->toBe('/politika-zasebnosti');
    expect(route('legal.cookies', absolute: false))->toBe('/politika-piskotkov');
    expect(route('legal.dpa', absolute: false))->toBe('/dogovor-o-obdelavi-osebnih-podatkov');
    expect(route('legal.provider', absolute: false))->toBe('/podatki-o-ponudniku');
    expect(route('legal.subprocessors', absolute: false))->toBe('/podobdelovalci');
});

test('main app route names generate the new canonical Slovenian paths', function () {
    expect(route('dashboard', absolute: false))->toBe('/danes');
    expect(route('search', absolute: false))->toBe('/iskanje');
    expect(route('inbox.index', absolute: false))->toBe('/sporocila');
    expect(route('customers.index', absolute: false))->toBe('/stranke');
    expect(route('customers.create', absolute: false))->toBe('/stranke/create');
    expect(route('orders.index', absolute: false))->toBe('/narocila');
    expect(route('orders.create', absolute: false))->toBe('/narocila/create');
    expect(route('appointments.index', absolute: false))->toBe('/termini');
    expect(route('appointments.create', absolute: false))->toBe('/termini/create');
    expect(route('settings.edit', absolute: false))->toBe('/nastavitve');
    expect(route('settings.support.edit', absolute: false))->toBe('/nastavitve/podpora');
    expect(route('settings.billing.edit', absolute: false))->toBe('/nastavitve/narocnina');
    expect(route('settings.statuses.edit', absolute: false))->toBe('/nastavitve/statusi');
    expect(route('settings.invoicing.edit', absolute: false))->toBe('/nastavitve/izdajanje-racunov');
    expect(route('settings.privacy.edit', absolute: false))->toBe('/nastavitve/zasebnost');
    expect(route('profile.edit', absolute: false))->toBe('/profil');
    expect(route('onboarding.show', absolute: false))->toBe('/zacetna-nastavitev');
});

test('resource show route names still generate customer/order/appointment IDs under the new Slovenian base paths', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Ana']);

    expect(route('customers.show', $customer, absolute: false))->toBe("/stranke/{$customer->id}");
});

test('old main human-facing GET paths permanently redirect to the new Slovenian paths', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    $this->actingAs($owner)->get('/today')->assertRedirect('/danes')->assertStatus(301);
    $this->actingAs($owner)->get('/search')->assertRedirect('/iskanje')->assertStatus(301);
    $this->actingAs($owner)->get('/inbox')->assertRedirect('/sporocila')->assertStatus(301);
    $this->actingAs($owner)->get('/customers')->assertRedirect('/stranke')->assertStatus(301);
    $this->actingAs($owner)->get('/orders')->assertRedirect('/narocila')->assertStatus(301);
    $this->actingAs($owner)->get('/appointments')->assertRedirect('/termini')->assertStatus(301);
    $this->actingAs($owner)->get('/settings')->assertRedirect('/nastavitve')->assertStatus(301);
    $this->actingAs($owner)->get('/settings/support')->assertRedirect('/nastavitve/podpora')->assertStatus(301);
    $this->actingAs($owner)->get('/settings/billing')->assertRedirect('/nastavitve/narocnina')->assertStatus(301);
    $this->actingAs($owner)->get('/settings/statuses')->assertRedirect('/nastavitve/statusi')->assertStatus(301);
    $this->actingAs($owner)->get('/settings/invoicing')->assertRedirect('/nastavitve/izdajanje-racunov')->assertStatus(301);
    $this->actingAs($owner)->get('/settings/privacy')->assertRedirect('/nastavitve/zasebnost')->assertStatus(301);
    $this->actingAs($owner)->get('/profile')->assertRedirect('/profil')->assertStatus(301);
    $this->actingAs($owner)->get('/onboarding')->assertRedirect('/zacetna-nastavitev')->assertStatus(301);
});

test('old auth GET paths permanently redirect to the new Slovenian paths', function () {
    $this->get('/register')->assertRedirect('/registracija')->assertStatus(301);
    $this->get('/login')->assertRedirect('/prijava')->assertStatus(301);
    $this->get('/forgot-password')->assertRedirect('/pozabljeno-geslo')->assertStatus(301);
    $this->get('/confirm-password')->assertRedirect('/potrdi-geslo')->assertStatus(301);
});

test('old mutation paths do not silently redirect POST/PATCH/DELETE requests', function () {
    // The old URI only has a GET route registered (the redirect); a stale
    // POST/PATCH/DELETE there hits that same URI on the wrong method and
    // gets a 405, never a silent redirect with the method changed.
    // (/login's old POST path is separately matched by Fortify's own
    // pre-existing shadow route — unrelated to this localization's
    // redirects and not a method-changing redirect either.)
    $this->post('/register', ['name' => 'x'])->assertStatus(405);

    [$workspace, $owner] = createWorkspaceWithUser();
    $this->actingAs($owner)->patch('/profile', ['name' => 'x'])->assertStatus(405);
    $this->actingAs($owner)->delete('/profile')->assertStatus(405);
});

test('authenticated navigation to the new canonical app paths works', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    $this->actingAs($owner)->get('/danes')->assertOk();
    $this->actingAs($owner)->get('/iskanje')->assertOk();
    $this->actingAs($owner)->get('/sporocila')->assertOk();
    $this->actingAs($owner)->get('/stranke')->assertOk();
    $this->actingAs($owner)->get('/nastavitve')->assertOk();
    $this->actingAs($owner)->get('/profil')->assertOk();
});

test('password reset URL generation still works and uses the new canonical path', function () {
    $url = route('password.reset', 'sample-token', absolute: false);

    expect($url)->toBe('/ponastavitev-gesla/sample-token');
});

test('email verification route generation is unchanged (not in scope of this localization)', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    expect(route('verification.notice', absolute: false))->toBe('/verify-email');
});

test('meta callback and webhook URLs are unchanged (external Meta App Dashboard config)', function () {
    expect(route('integrations.meta.callback', absolute: false))->toBe('/settings/integrations/meta/callback');
    expect(route('webhooks.meta.verify', absolute: false))->toBe('/webhooks/meta');
    expect(route('webhooks.meta.handle', absolute: false))->toBe('/webhooks/meta');
});

test('stripe webhook URL is unchanged', function () {
    expect(route('cashier.webhook', absolute: false))->toBe('/stripe/webhook');
});

test('password-confirmation-gated actions redirect to this app\'s own working confirm-password page, not Fortify\'s unconfigured one', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    // settings.billing.portal is gated by the 'password.confirm' middleware.
    // Before the alias override in bootstrap/app.php, this would 500
    // (Target [Laravel\Fortify\Contracts\ConfirmPasswordViewResponse] is
    // not instantiable) because route('password.confirm') resolved to
    // Fortify's own always-registered-but-unconfigured confirm-password
    // route instead of this app's.
    $response = $this->actingAs($owner)->get(route('settings.billing.portal'));

    $response->assertRedirect('/potrdi-geslo');
});

test('route names touched by this localization are not duplicated', function () {
    // Excludes password.confirm/password.request, which Fortify itself
    // also registers under an internal path (pre-existing, out of scope
    // for this URL localization — routes/auth.php's definitions win when
    // route() resolves the name).
    $localizedNames = [
        'legal.terms', 'legal.privacy', 'legal.cookies', 'legal.dpa', 'legal.provider', 'legal.subprocessors',
        'dashboard', 'search', 'inbox.index', 'inbox.show',
        'customers.index', 'customers.create', 'customers.show', 'customers.store', 'customers.update',
        'orders.index', 'orders.create', 'orders.show', 'orders.store', 'orders.update', 'orders.destroy',
        'appointments.index', 'appointments.create', 'appointments.show', 'appointments.store', 'appointments.update', 'appointments.destroy',
        'settings.edit', 'settings.support.edit', 'settings.billing.edit', 'settings.statuses.edit',
        'settings.invoicing.edit', 'settings.privacy.edit',
        'profile.edit', 'profile.update', 'profile.destroy',
        'onboarding.show', 'onboarding.complete',
        'register', 'login',
    ];

    $allNames = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter();

    foreach ($localizedNames as $name) {
        expect($allNames->filter(fn ($n) => $n === $name)->count())->toBe(1, "route name [{$name}] is duplicated");
    }
});
