<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

/**
 * Generates a currently-valid TOTP code for a user's real (Fortify-
 * encrypted) secret — using the same Google2FA engine Fortify itself uses
 * to verify codes, never a hand-rolled TOTP implementation.
 */
function currentTotpFor(User $user): string
{
    $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

    return app(Google2FA::class)->getCurrentOtp($secret);
}

function confirmedSessionFor($test, User $user)
{
    return $test->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
}

test('a user can enable 2FA, see the QR/secret, and confirm with a valid code', function () {
    [, $user] = createWorkspaceWithUser();

    confirmedSessionFor($this, $user)->post(route('two-factor.enable'))->assertRedirect();

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull();
    expect($user->two_factor_confirmed_at)->toBeNull();
    // Setup is not active until confirmed.
    expect($user->hasEnabledTwoFactorAuthentication())->toBeFalse();

    $code = currentTotpFor($user);

    confirmedSessionFor($this, $user)->post(route('two-factor.confirm'), ['code' => $code])->assertRedirect();

    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();
    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

test('confirming with an invalid code fails and leaves 2FA unconfirmed', function () {
    [, $user] = createWorkspaceWithUser();
    confirmedSessionFor($this, $user)->post(route('two-factor.enable'));

    // Fortify puts confirm errors in a named error bag.
    confirmedSessionFor($this, $user)->post(route('two-factor.confirm'), ['code' => '000000'])
        ->assertSessionHasErrors(['code'], null, 'confirmTwoFactorAuthentication');

    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
});

test('recovery codes are generated on enable and shown once via the recovery-codes endpoint', function () {
    [, $user] = createWorkspaceWithUser();
    confirmedSessionFor($this, $user)->post(route('two-factor.enable'));
    confirmedSessionFor($this, $user)->post(route('two-factor.confirm'), ['code' => currentTotpFor($user)]);

    $response = confirmedSessionFor($this, $user)->getJson(route('two-factor.recovery-codes'));

    $response->assertOk();
    expect($response->json())->toHaveCount(8);
});

test('a recovery code is single-use: it works once, then is rejected', function () {
    [, $user] = createWorkspaceWithUser();
    app(EnableTwoFactorAuthentication::class)($user);
    app(ConfirmTwoFactorAuthentication::class)($user->fresh(), currentTotpFor($user->fresh()));
    $user->refresh();

    $code = $user->recoveryCodes()[0];

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    $this->post(route('two-factor.login.store'), ['recovery_code' => $code])->assertRedirect();
    $this->assertAuthenticatedAs($user);
    Auth::logout();

    // Using the same recovery code again must fail.
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    $this->post(route('two-factor.login.store'), ['recovery_code' => $code])
        ->assertSessionHasErrors('recovery_code');
    $this->assertGuest();
});

test('the login flow redirects a 2FA-enabled user to the challenge instead of logging them in directly', function () {
    [, $user] = createWorkspaceWithUser();
    app(EnableTwoFactorAuthentication::class)($user);
    app(ConfirmTwoFactorAuthentication::class)($user->fresh(), currentTotpFor($user->fresh()));

    $response = $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();

    // Google2FA/Fortify reject reusing the exact same OTP twice within its
    // window (anti-replay, keyed in cache by the code's own value) — the
    // confirm step above already consumed the current code. Google2FA
    // computes windows from real wall-clock time (not Carbon), so
    // time-travel doesn't change it in a fast-running test; clear the
    // anti-replay cache instead so the same still-current code can be
    // used again for this second, unrelated login.
    Cache::flush();
    $valid = currentTotpFor($user->fresh());
    $this->post(route('two-factor.login.store'), ['code' => $valid])->assertRedirect();
    $this->assertAuthenticatedAs($user->fresh());
});

test('a user without 2FA logs straight in without a challenge', function () {
    [, $user] = createWorkspaceWithUser();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

test('regenerating recovery codes requires a recently confirmed password', function () {
    [, $user] = createWorkspaceWithUser();
    app(EnableTwoFactorAuthentication::class)($user);
    app(ConfirmTwoFactorAuthentication::class)($user->fresh(), currentTotpFor($user->fresh()));

    $this->actingAs($user->fresh())->post(route('two-factor.regenerate-recovery-codes'))
        ->assertRedirect(route('password.confirm'));
});

test('disabling 2FA requires a recently confirmed password', function () {
    [, $user] = createWorkspaceWithUser();
    app(EnableTwoFactorAuthentication::class)($user);
    app(ConfirmTwoFactorAuthentication::class)($user->fresh(), currentTotpFor($user->fresh()));

    $this->actingAs($user->fresh())->delete(route('two-factor.disable'))
        ->assertRedirect(route('password.confirm'));

    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();

    confirmedSessionFor($this, $user->fresh())->delete(route('two-factor.disable'))->assertRedirect();

    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

test('enabling 2FA in the first place also requires a recently confirmed password', function () {
    [, $user] = createWorkspaceWithUser();

    $this->actingAs($user)->post(route('two-factor.enable'))
        ->assertRedirect(route('password.confirm'));

    expect($user->fresh()->two_factor_secret)->toBeNull();
});

test('the two-factor challenge endpoint is rate limited', function () {
    [, $user] = createWorkspaceWithUser();
    app(EnableTwoFactorAuthentication::class)($user);
    app(ConfirmTwoFactorAuthentication::class)($user->fresh(), currentTotpFor($user->fresh()));

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('two-factor.login.store'), ['code' => '000000']);
    }

    // The 6th attempt within a minute is throttled, even with a genuinely
    // valid code — proves the limiter is keyed on the pending login, not
    // just failing on bad codes.
    $response = $this->post(route('two-factor.login.store'), ['code' => currentTotpFor($user->fresh())]);
    $response->assertStatus(429);
});

test('demo visitors cannot enable, confirm, disable, or regenerate 2FA', function () {
    [, $user] = createWorkspaceWithUser();
    $user->forceFill(['is_demo' => true])->save();

    confirmedSessionFor($this, $user)->post(route('two-factor.enable'))->assertForbidden();
    expect($user->fresh()->two_factor_secret)->toBeNull();

    // Even a demo user with a (test-fixture-only) secret already set can't
    // confirm/disable/regenerate through these endpoints.
    app(EnableTwoFactorAuthentication::class)($user);
    $code = currentTotpFor($user->fresh());

    confirmedSessionFor($this, $user->fresh())->post(route('two-factor.confirm'), ['code' => $code])
        ->assertForbidden();
    confirmedSessionFor($this, $user->fresh())->post(route('two-factor.regenerate-recovery-codes'))
        ->assertForbidden();
    confirmedSessionFor($this, $user->fresh())->delete(route('two-factor.disable'))
        ->assertForbidden();
});
