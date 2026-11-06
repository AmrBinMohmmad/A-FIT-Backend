<?php

declare(strict_types=1);

/*
 * Follows kit/plumbing/backend/bootstrap-app.snippet.php, minus the
 * tenancy middleware (this app is single-tenant — see references/adopting.md).
 */

use App\Http\Middleware\SetLocaleFromAcceptLanguage;
use App\Http\Middleware\StripServerOwnedFields;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // StripServerOwnedFields runs first so no validator or controller ever sees
        // ownership keys from the client; SetLocaleFromAcceptLanguage runs last so
        // every server-authored sentence (validation, auth, the map below) localizes.
        $middleware->api(
            prepend: [
                HandleCors::class,
                StripServerOwnedFields::class,
            ],
        );

        // Global, not api-group: an unmatched URL 404s before any route group runs, and
        // that 404 must still speak the caller's language.
        $middleware->append(SetLocaleFromAcceptLanguage::class);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Force EVERY api/* failure into the exact same three-key envelope the
        // ApiResponse trait uses on success: { flag, data, message }.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $validationMessage = static function (ValidationException $e): string {
                $messages = array_values(array_unique(array_filter(
                    Arr::flatten($e->errors()),
                    'is_string',
                )));

                if ($messages === []) {
                    return $e->getMessage();
                }

                $shown = array_slice($messages, 0, 5);
                $hidden = count($messages) - count($shown);

                return implode(' ', $shown).($hidden > 0 ? ' '.__('errors.and_n_more', ['count' => $hidden]) : '');
            };

            [$status, $message] = match (true) {
                $e instanceof ValidationException => [$e->status, $validationMessage($e)],
                $e instanceof AuthenticationException => [401, __('errors.unauthenticated')],
                $e instanceof AuthorizationException => [403, $e->getMessage() && $e->getMessage() !== 'This action is unauthorized.'
                    ? $e->getMessage()
                    : __('errors.forbidden')],
                $e instanceof AccessDeniedHttpException => [403, __('errors.forbidden')],
                $e instanceof ModelNotFoundException => [404, __('errors.record_not_found')],
                $e instanceof NotFoundHttpException => [404, __('errors.resource_not_found')],
                $e instanceof MethodNotAllowedHttpException => [405, __('errors.method_not_allowed')],
                $e instanceof ThrottleRequestsException => [429, __('errors.throttled')],
                $e instanceof HttpExceptionInterface => [$e->getStatusCode(), $e->getMessage() ?: __('errors.request_failed')],
                default => [500, config('app.debug') ? $e->getMessage() : __('errors.server_error')],
            };

            return response()->json([
                'flag' => false,
                'data' => null,
                'message' => $message,
            ], $status);
        });
    })->create();
