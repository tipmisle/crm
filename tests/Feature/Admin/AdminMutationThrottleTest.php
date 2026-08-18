<?php

use App\Models\User;

test('sensitive admin mutations are rate limited per admin', function () {
    $admin = createPlatformAdmin();
    $targets = User::factory()->count(31)->create();

    $session = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

    $lastResponse = null;
    foreach ($targets as $target) {
        $lastResponse = $session->post(route('admin.users.reactivate', $target));
    }

    // 30 mutations/minute allowed (AppServiceProvider's 'admin-mutations'
    // limiter); the 31st in the same minute must be throttled.
    $lastResponse->assertStatus(429);
});

test('read-only admin routes are not affected by the mutation throttle', function () {
    $admin = createPlatformAdmin();

    $session = $this->actingAs($admin);

    for ($i = 0; $i < 35; $i++) {
        $session->get(route('admin.users.index'))->assertOk();
    }
});
