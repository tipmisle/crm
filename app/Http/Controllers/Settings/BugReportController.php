<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\BugReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BugReportController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        BugReport::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'page_url' => $this->normalizedPagePath($request),
        ]);

        return back()->with('success', 'Prijava napake je bila poslana.');
    }

    /**
     * Never trust a client-submitted page_url — it's an easy PII escape
     * hatch (a search query, email address, or customer name could end up
     * in the query string). Derive it server-side from the Referer header
     * instead, keeping only the same-app path — no query string or
     * fragment, which is all this field needs to be useful to support.
     */
    private function normalizedPagePath(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        if (! $referer) {
            return null;
        }

        $path = parse_url($referer, PHP_URL_PATH);

        return $path ? substr($path, 0, 255) : null;
    }
}
