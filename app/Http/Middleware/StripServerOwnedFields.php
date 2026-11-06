<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Removes payload keys the server owns before any validator or controller sees them.
 * Ownership (user_id, owner_id), membership role, invite codes and timestamps are
 * always derived from the authenticated user or generated server-side — a client
 * that sends them is either confused or hostile, and either way the key is dropped.
 * Mirrors the Rakaya kit's StripServerOwnedFields (D4.3) for a single-tenant app.
 */
class StripServerOwnedFields
{
    /** @var list<string> */
    private const SERVER_OWNED = [
        'id',
        'user_id',
        'owner_id',
        'creator_id',
        'group_id',
        'invite_code',
        'role',
        'joined_at',
        'created_at',
        'updated_at',
        'email_verified_at',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/*')) {
            return $next($request);
        }

        $request->request->remove('_token');

        foreach (self::SERVER_OWNED as $key) {
            if ($request->request->has($key)) {
                $request->request->remove($key);
            }
            if ($request->query->has($key)) {
                $request->query->remove($key);
            }
        }

        return $next($request);
    }
}
