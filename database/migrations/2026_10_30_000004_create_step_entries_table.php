<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('step_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The user's LOCAL calendar day, as reported by the device.
            $table->date('date');
            $table->unsignedInteger('steps');
            $table->string('source', 20);
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['date', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_entries');
    }
};
