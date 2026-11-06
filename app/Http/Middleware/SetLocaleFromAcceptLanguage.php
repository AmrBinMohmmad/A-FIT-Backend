<?php

declare(strict_types=1);

/*
 * Follows kit/plumbing/backend/app/Http/Middleware/SetLocaleFromAcceptLanguage.php.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the application locale from Accept-Language, allowlist-validated against
 * config('app.supported_locales'). The envelope's single `message` is user-visible,
 * so the server must answer in the caller's language.
 */
class SetLocaleFromAcceptLanguage
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Accept-Language');

        if (is_string($header) && trim($header) !== '') {
            $firstTag = explode(';', explode(',', $header)[0])[0];
            $primary = strtolower(trim(explode('-', trim($firstTag))[0]));

            /** @var list<string> $supported */
            $supported = (array) config('app.supported_locales', ['ar', 'en']);

            if (in_array($primary, $supported, true)) {
                app()->setLocale($primary);
            }
        }

        return $next($request);
    }
}
