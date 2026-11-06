<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foods', function (Blueprint $table): void {
            $table->id();
            // null = shared catalog row; set = a user's private food (visible only to them).
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            // A brand/size variant points at its generic item ("Egg" ← "Egg, large, brand X").
            $table->foreignId('generic_id')->nullable()->constrained('foods')->nullOnDelete();
            $table->string('catalog_key', 80)->nullable()->unique();
            $table->string('category', 24)->index();
            $table->string('source', 24);
            $table->string('name', 120)->index();
            $table->string('name_ar', 120)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('barcode', 32)->nullable()->index();
            $table->decimal('serving_grams', 7, 1);
            $table->string('serving_label', 60)->nullable();
            $table->string('serving_label_ar', 60)->nullable();
            // Nutrition per 100 g — the one base every portion is scaled from.
            $table->decimal('calories', 7, 1);
            $table->decimal('protein', 6, 1);
            $table->decimal('carbs', 6, 1);
            $table->decimal('fat', 6, 1);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });

        Schema::create('meal_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('food_id')->nullable()->constrained('foods')->nullOnDelete();
            // Snapshots: a later catalog edit must not rewrite what was eaten.
            $table->string('name', 120);
            $table->string('brand', 80)->nullable();
            $table->decimal('grams', 7, 1);
            $table->decimal('servings', 6, 2)->nullable();
            $table->unsignedInteger('calories');
            $table->unsignedInteger('protein');
            $table->unsignedInteger('carbs');
            $table->unsignedInteger('fat');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_items');
        Schema::dropIfExists('foods');
    }
};
