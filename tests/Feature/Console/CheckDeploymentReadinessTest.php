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
