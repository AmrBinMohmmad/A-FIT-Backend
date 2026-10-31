<?php

declare(strict_types=1);

/*
 * Follows kit/plumbing/backend/app/Support/PaginatesResults.php (Rakaya D10.2) verbatim.
 */

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Resolves the `per_page` query parameter for every list endpoint. Anything that
 * isn't a usable page size falls back to the default (never clamps to 1), and the
 * ceiling stops a caller from asking for the whole table.
 */
trait PaginatesResults
{
    private const MAX_PER_PAGE = 100;

    protected function perPage(Request $request, int $default = 20): int
    {
        $requested = $request->input('per_page');

        if (! is_numeric($requested) || (int) $requested < 1) {
            return $default;
        }

        return min(self::MAX_PER_PAGE, (int) $requested);
    }
}
