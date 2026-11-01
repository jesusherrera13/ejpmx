<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entrada en la que un cambio entró a jugar: desde ahí batea en el turno del titular que reemplazó.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_batting_lines', function (Blueprint $table) {
            $table->unsignedTinyInteger('entered_inning')->nullable()->after('is_substitute');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_batting_lines', function (Blueprint $table) {
            $table->dropColumn('entered_inning');
        });
    }
};
