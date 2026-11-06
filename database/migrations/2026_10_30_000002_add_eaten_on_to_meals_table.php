<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->date('eaten_on')->nullable()->after('meal_type');
        });

        // Backfill: every existing meal belongs to the calendar day it was created on.
        DB::table('meals')->whereNull('eaten_on')->update(['eaten_on' => DB::raw('DATE(created_at)')]);

        Schema::table('meals', function (Blueprint $table) {
            $table->date('eaten_on')->nullable(false)->change();
            $table->index(['user_id', 'eaten_on']);
        });
    }

    public function down(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'eaten_on']);
            $table->dropColumn('eaten_on');
        });
    }
};
