<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StepSource;
use Database\Factories\StepEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StepEntry extends Model
{
    /** @use HasFactory<StepEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'date',
        'steps',
        'source',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'steps' => 'integer',
            'source' => StepSource::class,
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
