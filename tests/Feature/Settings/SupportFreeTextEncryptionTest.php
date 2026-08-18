<?php

use App\Models\BugReport;
use App\Models\FeatureRequest;
use Illuminate\Support\Facades\DB;

test('bug report message is encrypted at rest and round-trips through the model', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $plaintext = 'Ko kliknem na "Shrani", stranka Ana Novak (040 123 456) izgine iz seznama.';

    $report = BugReport::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'subject' => 'Stranka izgine',
        'message' => $plaintext,
    ]);

    $raw = DB::table('bug_reports')->where('id', $report->id)->value('message');
    expect($raw)->not->toBe($plaintext);
    expect($raw)->not->toContain('Ana Novak');

    // Subject stays plain so admins can filter/search on it.
    $rawSubject = DB::table('bug_reports')->where('id', $report->id)->value('subject');
    expect($rawSubject)->toBe('Stranka izgine');

    expect($report->fresh()->message)->toBe($plaintext);
});

test('feature request message is encrypted at rest and round-trips through the model', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $plaintext = 'Bilo bi super, če bi lahko za stranko Ana Novak nastavili opomnik.';

    $request = FeatureRequest::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'subject' => 'Opomniki za stranke',
        'message' => $plaintext,
    ]);

    $raw = DB::table('feature_requests')->where('id', $request->id)->value('message');
    expect($raw)->not->toBe($plaintext);
    expect($raw)->not->toContain('Ana Novak');

    expect($request->fresh()->message)->toBe($plaintext);
});
