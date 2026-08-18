<?php

use App\Models\User;

// Uses the literal path, not route('password.confirm') — that name is
// ambiguous (also auto-registered by Laravel Fortify at a different URI;
// pre-existing, unrelated to this URL localization) and currently
// resolves to Fortify's own unconfigured confirm-password endpoint. See
// docs/production-launch.md follow-up note.
test('confirm password screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/potrdi-geslo');

    $response->assertStatus(200);
});

test('password can be confirmed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/potrdi-geslo', [
        'password' => 'password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

test('password is not confirmed with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/potrdi-geslo', [
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors();
});
