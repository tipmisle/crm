<?php

use App\Models\Customer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Confirms the documented rotation mechanism in
 * docs/encryption-key-runbook.md actually works against this installed
 * Laravel version: data encrypted under an old APP_KEY remains readable
 * once that key is moved into APP_PREVIOUS_KEYS and a new APP_KEY is set.
 */
test('data encrypted under a previous key is still readable after rotation', function () {
    $oldKey = 'base64:'.base64_encode(random_bytes(32));
    config(['app.key' => $oldKey]);
    app()->forgetInstance('encrypter');

    [$workspace] = createWorkspaceWithUser();
    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Rotation Test',
        'notes' => 'Šifrirano pod starim ključem.',
    ]);

    $newKey = 'base64:'.base64_encode(random_bytes(32));
    config([
        'app.key' => $newKey,
        'app.previous_keys' => [$oldKey],
    ]);
    app()->forgetInstance('encrypter');

    expect($customer->fresh()->notes)->toBe('Šifrirano pod starim ključem.');

    // New writes use the new key.
    $customer->fresh()->update(['notes' => 'Šifrirano pod novim ključem.']);
    $raw = DB::table('customers')->where('id', $customer->id)->value('notes');
    expect(Crypt::decryptString($raw))->toBe('Šifrirano pod novim ključem.');
});
