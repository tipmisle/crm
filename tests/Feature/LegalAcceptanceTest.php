<?php

use App\Enums\LegalDocument;
use App\Models\LegalAcceptance;
use App\Models\User;

test('LegalAcceptance::record creates a row with the expected fields', function () {
    $user = User::factory()->create();

    $acceptance = LegalAcceptance::record($user, LegalDocument::Terms, '1.0');

    expect($acceptance->user_id)->toBe($user->id);
    expect($acceptance->document)->toBe(LegalDocument::Terms);
    expect($acceptance->version)->toBe('1.0');
    expect($acceptance->accepted_at)->not->toBeNull();
});

test('deleting the user removes their legal acceptance rows', function () {
    $user = User::factory()->create();
    $acceptance = LegalAcceptance::record($user, LegalDocument::Dpa, '1.0');

    $user->delete();

    expect(LegalAcceptance::find($acceptance->id))->toBeNull();
});

test('registration records Terms and DPA acceptance with configured versions', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'newuser@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms_dpa_accepted' => true,
    ]);

    $user = User::where('email', 'newuser@example.com')->firstOrFail();

    expect(LegalAcceptance::where('user_id', $user->id)->count())->toBe(2);

    $terms = LegalAcceptance::where('user_id', $user->id)->where('document', LegalDocument::Terms)->first();
    $dpa = LegalAcceptance::where('user_id', $user->id)->where('document', LegalDocument::Dpa)->first();

    expect($terms)->not->toBeNull();
    expect($terms->version)->toBe(config('legal.terms_version'));
    expect($terms->workspace_id)->toBe($user->current_workspace_id);

    expect($dpa)->not->toBeNull();
    expect($dpa->version)->toBe(config('legal.dpa_version'));
});

test('registration does not record a privacy-policy acceptance', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'newuser@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms_dpa_accepted' => true,
    ]);

    $user = User::where('email', 'newuser@example.com')->firstOrFail();

    expect(LegalAcceptance::where('user_id', $user->id)->count())->toBe(2);
});

test('registration without accepting terms/dpa creates no legal acceptance rows', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'newuser@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(LegalAcceptance::count())->toBe(0);
});
