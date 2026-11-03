<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bateo por jugador de un partido: un renglón por bateador en el lineup de su equipo.
 * Mientras no se elige al jugador, el renglón lleva un nombre provisional (placeholder).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('game_batting_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->foreignId('player_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('placeholder', 40)->nullable();
            $table->unsignedSmallInteger('batting_order');
            $table->boolean('is_substitute')->default(false);
            $table->unsignedTinyInteger('entered_inning')->nullable();
            $table->string('position', 3)->nullable();
            $table->unsignedSmallInteger('pa')->default(0);
            $table->unsignedSmallInteger('ab')->default(0);
            $table->unsignedSmallInteger('r')->default(0);
            $table->unsignedSmallInteger('h')->default(0);
            $table->unsignedSmallInteger('singles')->default(0);
            $table->unsignedSmallInteger('ih')->default(0);
            $table->unsignedSmallInteger('doubles')->default(0);
            $table->unsignedSmallInteger('triples')->default(0);
            $table->unsignedSmallInteger('hr')->default(0);
            $table->unsignedSmallInteger('rbi')->default(0);
            $table->unsignedSmallInteger('bb')->default(0);
            $table->unsignedSmallInteger('ibb')->default(0);
            $table->unsignedSmallInteger('hbp')->default(0);
            $table->unsignedSmallInteger('sf')->default(0);
            $table->unsignedSmallInteger('sh')->default(0);
            $table->unsignedSmallInteger('roe')->default(0);
            $table->unsignedSmallInteger('so')->default(0);
            $table->unsignedSmallInteger('so_swinging')->default(0);
            $table->unsignedSmallInteger('so_looking')->default(0);
            $table->unsignedSmallInteger('lob')->default(0);
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
    }
};
