<?php

test('deploy:check passes in a normally-configured test environment', function () {
    $this->artisan('deploy:check')->assertSuccessful();
});

test('deploy:check fails when APP_ENV=production is missing critical config', function () {
    config([
        'app.env' => 'production',
        'app.debug' => true,
        'app.url' => 'http://example.com',
        'session.secure' => null,
        'cashier.key' => null,
        'cashier.secret' => null,
        'cashier.webhook.secret' => null,
        'billing.monthly_price_id' => null,
    ]);

    $this->artisan('deploy:check')->assertFailed();
});

test('deploy:check does not print secret values, only whether they are missing', function () {
    config(['cashier.secret' => 'sk_live_super_secret_value']);

    $this->artisan('deploy:check')
        ->expectsOutputToContain('DB: connection OK')
        ->doesntExpectOutputToContain('sk_live_super_secret_value');
});

test('deploy:check fails when legal config is missing in production, even if all infra config is present', function () {
    config([
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://example.com',
        'session.secure' => true,
        'cashier.key' => 'pk_live_x',
        'cashier.secret' => 'sk_live_x',
        'cashier.webhook.secret' => 'whsec_x',
        'billing.monthly_price_id' => 'price_x',
        'legal.company_name' => null,
        'legal.registered_address' => null,
        'legal.registration_number' => null,
        'legal.tax_number' => null,
        'legal.legal_email' => null,
        'billing.display_price' => null,
        'billing.display_price_vat_included' => null,
    ]);

    $this->artisan('deploy:check')
        ->expectsOutputToContain('legal:check failed')
        ->assertFailed();
});

test('deploy:check does not require legal config outside production', function () {
    config([
        'legal.company_name' => null,
        'legal.registered_address' => null,
    ]);

    $this->artisan('deploy:check')->assertSuccessful();
});

function fullyConfiguredProductionEnv(): void
{
    config([
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://example.com',
        'session.secure' => true,
        'queue.default' => 'database',
        'cashier.key' => 'pk_live_x',
        'cashier.secret' => 'sk_live_x',
        'cashier.webhook.secret' => 'whsec_x',
        'billing.monthly_price_id' => 'price_x',
        'legal.company_name' => 'Web8 Josip Rajković s.p.',
        'legal.registered_address' => 'Zelena pot 3, 1241 Kamnik',
        'legal.registration_number' => '8829888000',
        'legal.tax_number' => '30631564',
        'legal.legal_email' => 'info@belezka.com',
        'legal.terms_version' => '2026-08-18',
        'legal.dpa_version' => '2026-08-18',
        'legal.privacy_version' => '2026-08-18',
        'legal.cookie_version' => '2026-08-18',
        'legal.vat_registered' => true,
        'legal.vat_number' => 'SI30631564',
        'billing.display_price' => '9,90€',
        'billing.display_price_vat_included' => true,
    ]);
}

test('deploy:check warns about platform admins without confirmed 2FA in production', function () {
    fullyConfiguredProductionEnv();
    $admin = createPlatformAdmin(withMfa: false);

    $this->artisan('deploy:check')
        ->expectsOutputToContain('Platform admin(s) without confirmed 2FA')
        ->assertSuccessful();

    expect($admin)->not->toBeNull();
});

test('deploy:check does not warn when all platform admins have confirmed 2FA', function () {
    fullyConfiguredProductionEnv();
    createPlatformAdmin();

    $this->artisan('deploy:check')
        ->doesntExpectOutputToContain('without confirmed 2FA')
        ->assertSuccessful();
});
