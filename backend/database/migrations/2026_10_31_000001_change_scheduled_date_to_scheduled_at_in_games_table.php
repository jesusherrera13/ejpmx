<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los partidos guardan fecha y hora: scheduled_date (date) pasa a scheduled_at (datetime).
 * Los partidos existentes quedan a las 00:00, que se interpreta como "sin hora asignada".
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->renameColumn('scheduled_date', 'scheduled_at');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dateTime('scheduled_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->date('scheduled_at')->nullable()->change();
        });

        Schema::table('games', function (Blueprint $table) {
            $table->renameColumn('scheduled_at', 'scheduled_date');
        });
    }
};
