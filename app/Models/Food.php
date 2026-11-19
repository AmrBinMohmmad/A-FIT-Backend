<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FoodCategory;
use App\Enums\FoodSource;
use Database\Factories\FoodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A grocery item with nutrition per 100 g and a default serving. Catalog rows (user_id
 * null) are shared by everyone; a user's own rows are private. Variants hang off a generic
 * item through generic_id so "eggs" shows the kinds found in the shops.
 */
class Food extends Model
{
    /** @use HasFactory<FoodFactory> */
    use HasFactory;

    /** Laravel treats "food" as uncountable; the table is plural on purpose. */
    protected $table = 'foods';

    protected $fillable = [
        'generic_id',
        'category',
        'name',
        'name_ar',
        'brand',
        'barcode',
        'image_url',
        'serving_grams',
        'serving_label',
        'serving_label_ar',
        'calories',
        'protein',
        'carbs',
        'fat',
    ];

    protected function casts(): array
    {
        return [
            'category' => FoodCategory::class,
            'source' => FoodSource::class,
            'serving_grams' => 'float',
            'calories' => 'float',
            'protein' => 'float',
            'carbs' => 'float',
            'fat' => 'float',
            'is_verified' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generic(): BelongsTo
    {
        return $this->belongsTo(self::class, 'generic_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(self::class, 'generic_id');
    }

    /** Catalog rows plus the user's own; nobody else's private foods. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('foods.user_id')->orWhere('foods.user_id', $user->id));
    }

    /**
     * Nutrition for a portion, rounded like the meals table stores it.
     *
     * @return array{calories: int, protein: int, carbs: int, fat: int}
     */
    public function portion(float $grams): array
    {
        $factor = $grams / 100;

        return [
            'calories' => (int) round($this->calories * $factor),
            'protein' => (int) round($this->protein * $factor),
            'carbs' => (int) round($this->carbs * $factor),
            'fat' => (int) round($this->fat * $factor),
        ];
    }
}
