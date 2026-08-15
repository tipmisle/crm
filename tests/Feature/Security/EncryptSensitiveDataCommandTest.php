<?php

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Order;
use App\Models\OrderNote;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Exercises App\Console\Commands\EncryptSensitiveData directly against raw
 * DB rows — bypassing the Customer model's 'encrypted' cast entirely, the
 * same way a real legacy-plaintext production row would look the moment
 * before this command runs.
 */
function insertRawCustomer(int $workspaceId, ?string $notes): int
{
    return DB::table('customers')->insertGetId([
        'workspace_id' => $workspaceId,
        'full_name' => 'Migration Test '.uniqid(),
        'notes' => $notes,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('plaintext rows are encrypted and remain readable as the original value', function () {
    [$workspace] = createWorkspaceWithUser();
    $id = insertRawCustomer($workspace->id, 'Navadno besedilo o stranki.');

    Artisan::call('security:encrypt-sensitive-data');

    $raw = DB::table('customers')->where('id', $id)->value('notes');
    expect($raw)->not->toBe('Navadno besedilo o stranki.');
    expect(Crypt::decryptString($raw))->toBe('Navadno besedilo o stranki.');

    expect(Customer::find($id)->notes)->toBe('Navadno besedilo o stranki.');
});

test('null values are left untouched', function () {
    [$workspace] = createWorkspaceWithUser();
    $id = insertRawCustomer($workspace->id, null);

    Artisan::call('security:encrypt-sensitive-data');

    expect(DB::table('customers')->where('id', $id)->value('notes'))->toBeNull();
    expect(Customer::find($id)->notes)->toBeNull();
});

test('Slovenian diacritics, emoji, and multiline text survive migration', function () {
    [$workspace] = createWorkspaceWithUser();

    $plaintext = "Čšž preverjanje 🎉🥳\nDruga vrstica z več besedila.\nTretja vrstica.";
    $id = insertRawCustomer($workspace->id, $plaintext);

    Artisan::call('security:encrypt-sensitive-data');

    expect(Customer::find($id)->notes)->toBe($plaintext);
});

test('a long note survives migration without truncation', function () {
    [$workspace] = createWorkspaceWithUser();

    $plaintext = str_repeat('Dolgo besedilo o stranki. ', 100);
    $id = insertRawCustomer($workspace->id, $plaintext);

    Artisan::call('security:encrypt-sensitive-data');

    expect(Customer::find($id)->notes)->toBe($plaintext);
});

test('running the command twice never double-encrypts a row', function () {
    [$workspace] = createWorkspaceWithUser();
    $id = insertRawCustomer($workspace->id, 'Enkratno šifriranje.');

    Artisan::call('security:encrypt-sensitive-data');
    $firstPass = DB::table('customers')->where('id', $id)->value('notes');

    Artisan::call('security:encrypt-sensitive-data');
    $secondPass = DB::table('customers')->where('id', $id)->value('notes');

    expect($secondPass)->toBe($firstPass);
    expect(Customer::find($id)->notes)->toBe('Enkratno šifriranje.');
});

test('the command processes more than one chunk correctly', function () {
    [$workspace] = createWorkspaceWithUser();

    $ids = [];
    for ($i = 0; $i < 12; $i++) {
        $ids[] = insertRawCustomer($workspace->id, "Stranka številka {$i}.");
    }

    Artisan::call('security:encrypt-sensitive-data', ['--chunk' => 5]);

    foreach ($ids as $i => $id) {
        expect(Customer::find($id)->notes)->toBe("Stranka številka {$i}.");
    }
});

test('dry run reports what would change without writing anything', function () {
    [$workspace] = createWorkspaceWithUser();
    $id = insertRawCustomer($workspace->id, 'Ne bi smelo biti spremenjeno.');

    Artisan::call('security:encrypt-sensitive-data', ['--dry-run' => true]);

    expect(DB::table('customers')->where('id', $id)->value('notes'))->toBe('Ne bi smelo biti spremenjeno.');
});

test('a legacy empty string on a nullable column is normalized to null, not encrypted', function () {
    [$workspace] = createWorkspaceWithUser();
    $id = insertRawCustomer($workspace->id, '');

    Artisan::call('security:encrypt-sensitive-data');

    expect(DB::table('customers')->where('id', $id)->value('notes'))->toBeNull();
    expect(Customer::find($id)->notes)->toBeNull();
});

test('a legacy empty string on a NOT NULL column is encrypted as an empty string', function () {
    [$workspace] = createWorkspaceWithUser();
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Test']);
    $order = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Order',
        'price' => 10,
        'status' => 'new',
        'payment_status' => 'unpaid',
    ]);

    $noteId = DB::table('order_notes')->insertGetId([
        'order_id' => $order->id,
        'body' => '',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $followUpId = DB::table('follow_ups')->insertGetId([
        'workspace_id' => $workspace->id,
        'followable_type' => Customer::class,
        'followable_id' => $customer->id,
        'note' => '',
        'due_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('security:encrypt-sensitive-data');

    // Raw column is still non-null ciphertext, never a bare empty string.
    $rawNote = DB::table('order_notes')->where('id', $noteId)->value('body');
    expect($rawNote)->not->toBeNull();
    expect($rawNote)->not->toBe('');
    expect(Crypt::decryptString($rawNote))->toBe('');

    expect(OrderNote::find($noteId)->body)->toBe('');
    expect(FollowUp::find($followUpId)->note)->toBe('');
});

test('reading every encrypted field through the model succeeds for null, legacy empty string, plaintext, and already-encrypted values', function () {
    [$workspace] = createWorkspaceWithUser();

    $nullId = insertRawCustomer($workspace->id, null);
    $emptyId = insertRawCustomer($workspace->id, '');
    $plainId = insertRawCustomer($workspace->id, 'Navadno besedilo.');
    $encryptedId = insertRawCustomer($workspace->id, Crypt::encryptString('Že šifrirano besedilo.'));

    Artisan::call('security:encrypt-sensitive-data');

    expect(Customer::find($nullId)->notes)->toBeNull();
    expect(Customer::find($emptyId)->notes)->toBeNull();
    expect(Customer::find($plainId)->notes)->toBe('Navadno besedilo.');
    expect(Customer::find($encryptedId)->notes)->toBe('Že šifrirano besedilo.');
});

test('running the command twice never double-encrypts a normalized empty-string row', function () {
    [$workspace] = createWorkspaceWithUser();
    $id = insertRawCustomer($workspace->id, '');

    Artisan::call('security:encrypt-sensitive-data');
    Artisan::call('security:encrypt-sensitive-data');

    expect(DB::table('customers')->where('id', $id)->value('notes'))->toBeNull();
    expect(Customer::find($id)->notes)->toBeNull();
});
