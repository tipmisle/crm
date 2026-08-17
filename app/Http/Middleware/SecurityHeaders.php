<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers that are safe to ship without a full CSP
 * rollout (which needs its own careful task — see docs/pre-launch-security.md).
 * Nothing here should risk breaking existing Meta OAuth/webhook integrations
 * or local development.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Same protection as X-Frame-Options: DENY, expressed the modern way.
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'");
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // Only ever sent over an already-HTTPS response — an HSTS header on
        // plain http (e.g. local dev) has no effect but is misleading to see
        // in a response; $request->secure() correctly reflects the real
        // scheme once TRUSTED_PROXIES is configured behind a TLS-terminating
        // proxy (see bootstrap/app.php).
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
