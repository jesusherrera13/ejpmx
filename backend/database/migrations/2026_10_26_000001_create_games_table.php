<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rol de juegos de un torneo: cada fila es un partido de una serie.
 * Vuelta (round) > Jornada (matchday) > Serie (series) > Partido (game_number).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round');
            $table->unsignedSmallInteger('matchday');
            $table->unsignedInteger('series');
            $table->unsignedSmallInteger('series_game');
            $table->unsignedInteger('game_number');
            $table->foreignId('home_team_id')->constrained('teams')->restrictOnDelete();
            $table->foreignId('away_team_id')->constrained('teams')->restrictOnDelete();
            $table->dateTime('scheduled_at')->nullable();
            // Box score: las carreras (R) van en home_score / away_score
            $table->unsignedSmallInteger('home_score')->nullable();
            $table->unsignedSmallInteger('away_score')->nullable();
            // score | linescore | batting | batting_full
            $table->string('boxscore_mode', 20)->nullable();
            $table->unsignedSmallInteger('home_hits')->nullable();
            $table->unsignedSmallInteger('home_errors')->nullable();
            $table->unsignedSmallInteger('home_lob')->nullable();
            $table->unsignedSmallInteger('away_hits')->nullable();
            $table->unsignedSmallInteger('away_errors')->nullable();
            $table->unsignedSmallInteger('away_lob')->nullable();
            // {"away": [0, 1, ...], "home": [2, 0, ..., null]}; null = no batearon esa entrada
            $table->json('line_score')->nullable();
            $table->timestamps();

            $table->unique(['tournament_id', 'game_number']);
            $table->index(['tournament_id', 'matchday']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
