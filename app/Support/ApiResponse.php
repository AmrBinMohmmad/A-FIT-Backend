<?php

declare(strict_types=1);

/*
 * Follows kit/plumbing/backend/app/Support/ApiResponse.php verbatim.
 */

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Every API response is emitted through this trait so the payload is always the
 * exact same three-key envelope, in the same order:
 *
 *     { "flag": bool, "data": mixed|null, "message": string }
 *
 * There is deliberately no `errors` key: validation and other failures surface a
 * single clear sentence in `message` (see bootstrap/app.php for the exception
 * envelope). Keep it that way — don't add fields to the envelope.
 */
trait ApiResponse
{
    protected function success(mixed $data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'flag' => true,
            'data' => $data,
            'message' => $message ?? __('messages.ok'),
        ], $code);
    }

    protected function error(?string $message = null, int $code = 400): JsonResponse
    {
        return response()->json([
            'flag' => false,
            'data' => null,
            'message' => $message ?? __('errors.request_failed'),
        ], $code);
    }

    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return response()->json([
            'flag' => true,
            'data' => $data,
            'message' => $message ?? __('messages.created'),
        ], 201);
    }

    /**
     * A successful delete. HTTP 204 is impossible under this contract: the envelope
     * requires all three keys on every response, so a delete answers 200 + data: null.
     */
    protected function deleted(?string $message = null): JsonResponse
    {
        return response()->json([
            'flag' => true,
            'data' => null,
            'message' => $message ?? __('messages.deleted'),
        ], 200);
    }
}
