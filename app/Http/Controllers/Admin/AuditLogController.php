<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AuditLog::query()->with('actor:id,name,email')->latest('created_at');

        if ($event = $request->string('event')->trim()->value()) {
            $query->where('event', 'like', "%{$event}%");
        }

        if ($workspaceId = $request->integer('workspace_id')) {
            $query->where('workspace_id', $workspaceId);
        }

        return Inertia::render('Admin/AuditLog/Index', [
            'entries' => $query->paginate(50)->withQueryString(),
            'filters' => $request->only(['event', 'workspace_id']),
        ]);
    }
}
