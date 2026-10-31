<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Path on the public disk; the URL is derived on read (App\Services\Photos).
            $table->string('avatar_path', 191)->nullable()->after('locale');
        });

        Schema::table('groups', function (Blueprint $table): void {
            $table->string('photo_path', 191)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('avatar_path'));
        Schema::table('groups', fn (Blueprint $table) => $table->dropColumn('photo_path'));
    }
};
