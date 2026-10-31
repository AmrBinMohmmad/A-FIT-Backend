<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Composite home-screen payload built by App\Services\DailySummary. The array shape
 * is declared there; this resource is the single wire emitter for it (Rakaya D6).
 */
class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $summary */
        $summary = $this->resource;

        return $summary;
    }
}
