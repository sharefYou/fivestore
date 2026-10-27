<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the locale segment of the URL to the application.
 *
 * The route pattern already restricts {locale} to the supported values, so an
 * unknown locale is a 404 before this runs; the check here is the second line
 * of defence for routes reached another way.
 */
class SetLocale
{
    public const COOKIE = 'preferred_locale';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        if (! is_string($locale) || ! Locales::isSupported($locale)) {
            abort(404);
        }

        app()->setLocale($locale);

        $response = $next($request);

        // Remembered only so that "/" can send a returning visitor to the
        // language they were last reading. It holds no personal data, and it
        // is read on the server, so the browser gets no access to it.
        return $response->withCookie(
            new Cookie(
                name: self::COOKIE,
                value: $locale,
                expire: now()->addYear()->getTimestamp(),
                path: '/',
                secure: $request->isSecure(),
                httpOnly: true,
                sameSite: Cookie::SAMESITE_LAX,
            )
        );
    }
}
