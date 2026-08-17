<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Public legal pages — never redirect authenticated users away (unlike
 * MarketingController::home()), always accessible without a workspace.
 * See docs/legal-compliance.md.
 */
class LegalController extends Controller
{
    public function terms(): Response
    {
        return Inertia::render('Legal/Terms', ['legal' => config('legal')]);
    }

    public function privacy(): Response
    {
        return Inertia::render('Legal/Privacy', [
            'legal' => config('legal'),
            'retention' => [
                'workspace_grace_days' => config('retention.workspace_grace_days'),
                'export_hours' => config('retention.export_hours'),
                'audit_log_days' => config('retention.audit_log_days'),
            ],
        ]);
    }

    public function cookies(): Response
    {
        return Inertia::render('Legal/Cookies', ['legal' => config('legal')]);
    }

    public function dpa(): Response
    {
        return Inertia::render('Legal/Dpa', ['legal' => config('legal')]);
    }

    public function provider(): Response
    {
        return Inertia::render('Legal/Provider', ['legal' => config('legal')]);
    }

    public function subprocessors(): Response
    {
        return Inertia::render('Legal/Subprocessors', ['legal' => config('legal')]);
    }
}
