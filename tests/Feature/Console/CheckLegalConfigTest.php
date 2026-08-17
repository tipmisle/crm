<?php

use Illuminate\Support\Facades\Artisan;

function setCompleteLegalConfig(): void
{
    config([
        'legal.company_name' => 'Primer d.o.o.',
        'legal.registered_address' => 'Testna ulica 1, 1000 Ljubljana',
        'legal.registration_number' => '1234567000',
        'legal.tax_number' => 'SI12345678',
        'legal.legal_email' => 'legal@example.com',
        'legal.terms_version' => '2026-08-15',
        'legal.dpa_version' => '2026-08-15',
        'legal.privacy_version' => '2026-08-15',
        'legal.cookie_version' => '2026-08-15',
        'legal.vat_registered' => false,
        'legal.vat_number' => null,
    ]);
}

test('legal:check fails when required config is missing', function () {
    config(['legal.company_name' => null]);

    Artisan::call('legal:check');

    expect(Artisan::output())->toContain('Missing required legal config: legal.company_name');
    expect(Artisan::call('legal:check'))->toBe(1);
});

test('legal:check passes when all required config is present', function () {
    setCompleteLegalConfig();

    expect(Artisan::call('legal:check'))->toBe(0);
});

test('legal:check warns but does not fail on missing advisory config', function () {
    setCompleteLegalConfig();
    config(['legal.dpo_contact' => null, 'legal.competent_court' => null]);

    Artisan::call('legal:check');

    expect(Artisan::output())->toContain('Advisory: legal.dpo_contact');
    expect(Artisan::call('legal:check'))->toBe(0);
});

test('legal:check fails when vat_registered is true but vat_number is missing', function () {
    setCompleteLegalConfig();
    config(['legal.vat_registered' => true, 'legal.vat_number' => null]);

    expect(Artisan::call('legal:check'))->toBe(1);
});

test('legal:check fails when a display price is set without its VAT-inclusion flag', function () {
    setCompleteLegalConfig();
    config(['billing.display_price' => '19 €', 'billing.display_price_vat_included' => null]);

    Artisan::call('legal:check');

    expect(Artisan::output())->toContain('billing.display_price_vat_included');
    expect(Artisan::call('legal:check'))->toBe(1);
});

test('legal:check passes when a display price is set together with its VAT-inclusion flag', function () {
    setCompleteLegalConfig();
    config(['billing.display_price' => '19 €', 'billing.display_price_vat_included' => true]);

    expect(Artisan::call('legal:check'))->toBe(0);
});

test('legal:check passes when no display price is set at all', function () {
    setCompleteLegalConfig();
    config(['billing.display_price' => null, 'billing.display_price_vat_included' => null]);

    expect(Artisan::call('legal:check'))->toBe(0);
});
