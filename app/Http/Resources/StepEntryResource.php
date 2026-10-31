<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StepEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StepEntry */
class StepEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->date->toDateString(),
            'steps' => $this->steps,
            'source' => $this->source->value,
            'synced_at' => $this->synced_at->toIso8601String(),
        ];
    }
}
