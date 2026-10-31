<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// The API has no web UI; the root answers with a minimal JSON identity so load
// balancers and humans see something deliberate instead of the Laravel welcome page.
Route::get('/', fn () => response()->json(['service' => 'a-fit-api', 'status' => 'ok']));
