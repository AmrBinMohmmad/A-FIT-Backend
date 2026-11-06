<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Photo uploads were withdrawn the same day; users now pick a predefined avatar key. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('avatar_path');
            $table->string('avatar', 24)->nullable()->after('locale');
        });

        Schema::table('groups', fn (Blueprint $table) => $table->dropColumn('photo_path'));
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('avatar');
            $table->string('avatar_path', 191)->nullable();
        });

        Schema::table('groups', fn (Blueprint $table) => $table->string('photo_path', 191)->nullable());
    }
};
