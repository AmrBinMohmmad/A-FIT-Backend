<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterEntry extends Model
{
    protected $fillable = [
        'date',
        'ml',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'ml' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
