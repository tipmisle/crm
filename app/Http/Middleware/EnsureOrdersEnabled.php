<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrdersEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->currentWorkspace?->orders_enabled, 404);

        return $next($request);
    }
}
