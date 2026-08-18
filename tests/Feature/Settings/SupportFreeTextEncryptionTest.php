<?php

use App\Models\BugReport;
use App\Models\FeatureRequest;
use Illuminate\Support\Facades\DB;

test('bug report subject and message are encrypted at rest and round-trip through the model', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $plaintextMessage = 'Ko kliknem na "Shrani", stranka Ana Novak (040 123 456) izgine iz seznama.';
    $plaintextSubject = 'Stranka Ana Novak izgine';

    $report = BugReport::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'subject' => $plaintextSubject,
        'message' => $plaintextMessage,
    ]);

    $raw = DB::table('bug_reports')->where('id', $report->id)->first();
    expect($raw->message)->not->toBe($plaintextMessage);
    expect($raw->message)->not->toContain('Ana Novak');

    // subject is not SQL-searched/filtered anywhere (admin only filters by
    // status), so it's encrypted too — never left plaintext just because
    // it's short.
    expect($raw->subject)->not->toBe($plaintextSubject);
    expect($raw->subject)->not->toContain('Ana Novak');

    expect($report->fresh()->subject)->toBe($plaintextSubject);
    expect($report->fresh()->message)->toBe($plaintextMessage);
});

test('feature request subject and message are encrypted at rest and round-trip through the model', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $plaintextMessage = 'Bilo bi super, če bi lahko za stranko Ana Novak nastavili opomnik.';
    $plaintextSubject = 'Opomniki za stranko Ana Novak';

    $request = FeatureRequest::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'subject' => $plaintextSubject,
        'message' => $plaintextMessage,
    ]);

    $raw = DB::table('feature_requests')->where('id', $request->id)->first();
    expect($raw->message)->not->toBe($plaintextMessage);
    expect($raw->message)->not->toContain('Ana Novak');
    expect($raw->subject)->not->toBe($plaintextSubject);
    expect($raw->subject)->not->toContain('Ana Novak');

    expect($request->fresh()->subject)->toBe($plaintextSubject);
    expect($request->fresh()->message)->toBe($plaintextMessage);
});

test('bug report page_url is derived server-side from the Referer path only, never a client-submitted query string', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $this->actingAs($user)
        ->withHeader('referer', 'https://app.example.com/nastavitve/podpora?q=ana@example.com#section')
        ->post(route('settings.support.bug-reports.store'), [
            'subject' => 'Test',
            'message' => 'Test message',
            // A crafted client-submitted page_url must be ignored entirely.
            'page_url' => 'https://evil.example.com/?leak=ana@example.com',
        ])
        ->assertRedirect();

    $report = BugReport::first();
    expect($report->page_url)->toBe('/nastavitve/podpora');
    expect($report->page_url)->not->toContain('evil.example.com');
    expect($report->page_url)->not->toContain('ana@example.com');
});
