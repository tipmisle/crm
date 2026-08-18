<?php

/**
 * Inertia pages render client-side (no SSR configured — see
 * resources/views/app.blade.php), so a feature-test HTTP response never
 * contains the Vue template's rendered text, only the component name and
 * JSON props. These tests read the shipped .vue source directly to verify
 * the actual legal copy, alongside HTTP tests for the server-sourced props
 * that genuinely do round-trip through the response.
 */
function legalPageSource(string $component): string
{
    return file_get_contents(resource_path("js/Pages/Legal/{$component}.vue"));
}

test('terms page does not claim the service is free or payment-less', function () {
    $source = legalPageSource('Terms');

    expect($source)->not->toContain('brezplačna uporaba');
    expect($source)->not->toContain('nima vzpostavljenega plačilnega sistema');
});

test('privacy page does not claim payments are not processed', function () {
    $source = legalPageSource('Privacy');

    expect($source)->not->toContain('ne obračunava plačil in nima vzpostavljenega plačilnega sistema');
});

test('privacy page demo section does not claim zero personal data is processed', function () {
    $source = legalPageSource('Privacy');

    expect($source)->not->toContain('ne zbira nobenih vaših osebnih podatkov');
});

test('terms and dpa pages do not claim FURS fiscalization or accounting compliance', function () {
    foreach (['Terms', 'Dpa'] as $component) {
        $source = legalPageSource($component);

        expect($source)->not->toContain('Beležka je skladna s FURS');
        expect($source)->not->toContain('Beležka izpolnjuje zahteve fiskalizacije');
    }
});

test('terms page discloses sales documents and disclaims accounting/tax advisory role', function () {
    $source = legalPageSource('Terms');

    expect($source)->toContain('predračun');
    expect($source)->toContain('storno');
    expect($source)->not->toContain('dobropis');
    expect($source)->not->toContain('Dobropis');
    expect($source)->toContain('računovodski ali davčno-svetovalni servis');
});

test('terms page discloses subscription is paid, recurring, and cancellation does not delete data', function () {
    $source = legalPageSource('Terms');

    expect($source)->toContain('Stripe Checkout');
    expect($source)->toContain('samodejno obnavlja');
    expect($source)->toContain('ne pomeni izbrisa podatkov delovnega prostora');
});

test('terms page separates workspace deletion from subscription cancellation', function () {
    $source = legalPageSource('Terms');

    expect($source)->toContain('izbris delovnega prostora');
    expect($source)->toContain('ločen');
});

test('cookies page discloses the remember-me cookie', function () {
    $source = legalPageSource('Cookies');

    expect($source)->toContain('Zapomni si me');
    expect($source)->toContain('remember_web');
});

test('cookies page states fonts are self-hosted, not loaded from Google Fonts', function () {
    $source = legalPageSource('Cookies');

    expect($source)->not->toContain('fonts.googleapis.com');
    expect($source)->toContain('lastnih strežnikih');
});

test('app shell no longer loads a remote Google Fonts stylesheet', function () {
    $blade = file_get_contents(resource_path('views/app.blade.php'));

    expect($blade)->not->toContain('fonts.googleapis.com');
    expect($blade)->not->toContain('fonts.gstatic.com');
});

test('app css imports the self-hosted rubik font package', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('@fontsource-variable/rubik');
});

test('subprocessors page separates article 28 subprocessors from account/billing providers', function () {
    $source = legalPageSource('Subprocessors');

    expect($source)->toContain('VAŠIH strank');
    expect($source)->toContain('VAŠE lastne podatke');

    $response = $this->get(route('legal.subprocessors'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('legal.subprocessors')
        ->has('legal.account_billing_providers')
    );
});

test('config keeps stripe and push providers out of the article 28 customer-data subprocessor list', function () {
    $subprocessorNames = collect(config('legal.subprocessors'))->pluck('name');

    expect($subprocessorNames)->not->toContain('Stripe, Inc. / Stripe Payments Europe, Ltd.');

    $accountProviderNames = collect(config('legal.account_billing_providers'))->pluck('name');

    expect($accountProviderNames)->toContain('Stripe, Inc. / Stripe Payments Europe, Ltd.');
});

test('dpa page states erasure of a customer does not delete already-issued sales documents', function () {
    $source = legalPageSource('Dpa');

    $normalized = preg_replace('/\s+/', ' ', $source);

    expect($normalized)->toContain('izbris stranke torej ne pomeni tudi izbrisa že izdanih dokumentov');
});

test('dpa page discloses article 28 processor-instruction and audit-assistance obligations', function () {
    $source = legalPageSource('Dpa');

    expect($source)->toContain('kršila');
    expect($source)->toContain('revizij');
});

test('privacy page states no article 22 automated decision-making applies', function () {
    $source = legalPageSource('Privacy');

    expect($source)->toContain('člen 22 GDPR');
});

test('no public legal page source contains unfinished/internal placeholder wording', function () {
    foreach (['Terms', 'Privacy', 'Cookies', 'Dpa', 'Provider', 'Subprocessors'] as $component) {
        $source = legalPageSource($component);

        expect($source)->not->toContain('NEEDS OWNER INPUT');
        expect($source)->not->toContain('še ni določena');
        expect($source)->not->toContain('še ni določeno');
        expect($source)->not->toContain('še ni določen');
        expect($source)->not->toContain('bo določeno');
        expect($source)->not->toContain('ni potrjeno');
        expect($source)->not->toContain('dokončno določen');
    }
});

test('privacy page discloses the Instagram/Facebook Messenger integration clearly', function () {
    $source = legalPageSource('Privacy');

    expect($source)->toContain('Instagram');
    expect($source)->toContain('Facebook Messenger');
    expect($source)->toContain('Povezava z Instagramom in Facebook Messengerjem');
});

test('privacy page states the Meta integration is optional and initiated by the workspace', function () {
    $source = legalPageSource('Privacy');
    $normalized = preg_replace('/\s+/', ' ', $source);

    expect($normalized)->toContain('Povezava je neobvezna in jo vedno vzpostavi delovni prostor sam');
});

test('privacy page states Beležka does not sell Meta-derived data or build ad profiles from message content', function () {
    $source = legalPageSource('Privacy');
    $normalized = preg_replace('/\s+/', ' ', $source);

    expect($normalized)->toContain('ne prodajamo');
    expect($normalized)->toContain('ne uporabljamo za oblikovanje oglaševalskih profilov strank');
});

test('privacy page does not claim Meta is a confirmed Article 28 subprocessor', function () {
    $source = legalPageSource('Privacy');

    expect($source)->not->toContain('Meta je podobdelovalec');
    expect($source)->not->toContain('Meta je Article 28 podobdelovalec');
});

test('privacy contact email renders when configured', function () {
    config(['legal.legal_email' => 'info@belezka.com']);

    $response = $this->get(route('legal.privacy'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('legal.legal_email', 'info@belezka.com'));
});

test('external-platform classification for Meta remains distinct from Article 28 subprocessors', function () {
    $subprocessorNames = collect(config('legal.subprocessors'))->pluck('name');
    expect($subprocessorNames->filter(fn ($name) => str_contains($name, 'Meta')))->toBeEmpty();

    $metaPlatform = collect(config('legal.external_platforms'))->first(fn ($p) => str_contains($p['name'], 'Meta'));

    expect($metaPlatform)->not->toBeNull();
    expect($metaPlatform['role_note'])->not->toContain('je Article 28 podobdelovalec');
});
