<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foods', function (Blueprint $table): void {
            // Product photo from Open Food Facts (served by them); never uploaded by users.
            $table->string('image_url', 500)->nullable()->after('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('foods', fn (Blueprint $table) => $table->dropColumn('image_url'));
    }
};
