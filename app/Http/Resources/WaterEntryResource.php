<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WaterEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WaterEntry */
class WaterEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->date->toDateString(),
            'ml' => $this->ml,
        ];
    }
}
