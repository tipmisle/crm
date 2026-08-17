<?php

test('responses include baseline security headers', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
});

test('HSTS is not sent over plain http (local dev)', function () {
    $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
});

test('HSTS is sent once the request is detected as secure', function () {
    $this->get('https://localhost/')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});
