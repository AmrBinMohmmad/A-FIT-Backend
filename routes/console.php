<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Expired personal access tokens are dead weight in the index; prune them nightly.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Telescope is local-only, but if a developer leaves it on, keep its table bounded.
Schedule::command('telescope:prune --hours=48')->daily()->environments(['local']);
