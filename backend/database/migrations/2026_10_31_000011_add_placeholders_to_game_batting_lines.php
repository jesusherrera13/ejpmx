<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lineup provisional: un renglón puede no tener jugador todavía ("Bateador 3") para capturar el juego
 * en vivo y asignar después al jugador real. Los NULL no chocan en el índice único (game_id, player_id).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_batting_lines', function (Blueprint $table) {
            $table->foreignId('player_id')->nullable()->change();
            $table->string('placeholder', 40)->nullable()->after('player_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_batting_lines', function (Blueprint $table) {
            $table->dropColumn('placeholder');
            $table->foreignId('player_id')->nullable(false)->change();
        });
    }
};
