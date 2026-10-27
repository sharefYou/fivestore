<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response headers for both the storefront and the admin panel.
 *
 * The plain headers below are safe everywhere and are applied everywhere. The
 * Content-Security-Policy is not: Livewire and Filament rely on inline
 * scripts that a useful policy would have to allow anyway, so the panel runs
 * this middleware as `security.headers:no-csp` and gets the rest.
 *
 * HSTS is deliberately absent: it is only safe once HTTPS is known to work on
 * every hostname the site answers on, so it belongs in the web server config
 * for the real domain (see docs/technical-decisions.md).
 */
class SecurityHeaders
{
    public const WITHOUT_CSP = 'no-csp';

    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), interest-cohort=()',
        ];

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value, false);
        }

        if ($mode !== self::WITHOUT_CSP && ($policy = $this->contentSecurityPolicy())) {
            $response->headers->set('Content-Security-Policy', $policy, false);
        }

        return $response;
    }

    /**
     * The storefront serves its own built assets and its own images, so the
     * policy is "same origin only".
     *
     * In local development Vite serves modules, opens a websocket for hot
     * reload and injects a style element, so the policy is relaxed there
     * rather than shipped broken.
     */
    protected function contentSecurityPolicy(): ?string
    {
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "object-src 'none'",
            "img-src 'self' data:",
            "font-src 'self'",
        ];

        if (app()->isLocal()) {
            $directives[] = "script-src 'self' 'unsafe-inline' http://localhost:* http://127.0.0.1:*";
            $directives[] = "style-src 'self' 'unsafe-inline' http://localhost:* http://127.0.0.1:*";
            $directives[] = "connect-src 'self' ws://localhost:* ws://127.0.0.1:* http://localhost:* http://127.0.0.1:*";
        } else {
            $directives[] = "script-src 'self'";
            // Vue sets inline styles on elements it animates or sizes.
            $directives[] = "style-src 'self' 'unsafe-inline'";
            $directives[] = "connect-src 'self'";
        }

        return implode('; ', $directives);
    }
}
