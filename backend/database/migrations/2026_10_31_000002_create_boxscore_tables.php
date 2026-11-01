<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Box score de un partido: marcador final (R H E), carreras por entrada y bateo por jugador.
 * Las carreras siguen en home_score / away_score.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // score | linescore | batting | batting_full
            $table->string('boxscore_mode', 20)->nullable()->after('away_score');
            $table->unsignedSmallInteger('home_hits')->nullable()->after('boxscore_mode');
            $table->unsignedSmallInteger('home_errors')->nullable()->after('home_hits');
            $table->unsignedSmallInteger('away_hits')->nullable()->after('home_errors');
            $table->unsignedSmallInteger('away_errors')->nullable()->after('away_hits');
            // {"away": [0, 1, ...], "home": [2, 0, ..., null]}; null = no batearon esa entrada
            $table->json('line_score')->nullable()->after('away_errors');
        });

        // Un renglón por bateador en el lineup de su equipo
        Schema::create('game_batting_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->foreignId('player_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('batting_order');
            $table->string('position', 3)->nullable();
            $table->unsignedSmallInteger('pa')->default(0);
            $table->unsignedSmallInteger('ab')->default(0);
            $table->unsignedSmallInteger('r')->default(0);
            $table->unsignedSmallInteger('h')->default(0);
            $table->unsignedSmallInteger('singles')->default(0);
            $table->unsignedSmallInteger('doubles')->default(0);
            $table->unsignedSmallInteger('triples')->default(0);
            $table->unsignedSmallInteger('hr')->default(0);
            $table->unsignedSmallInteger('rbi')->default(0);
            $table->unsignedSmallInteger('so')->default(0);
            $table->unsignedSmallInteger('so_swinging')->default(0);
            $table->unsignedSmallInteger('so_looking')->default(0);
            $table->unsignedSmallInteger('e')->default(0);
            $table->timestamps();

            $table->unique(['game_id', 'player_id']);
            $table->index(['game_id', 'team_id', 'batting_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_batting_lines');

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['boxscore_mode', 'home_hits', 'home_errors', 'away_hits', 'away_errors', 'line_score']);
        });
    }
};
