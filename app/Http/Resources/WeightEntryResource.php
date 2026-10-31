<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WeightEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WeightEntry */
class WeightEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->toDateString(),
            'weight_kg' => (float) $this->weight_kg,
        ];
    }
}
