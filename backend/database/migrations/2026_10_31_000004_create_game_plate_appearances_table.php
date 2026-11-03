<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scorecard: cada turno al bat de un jugador, en la entrada en que ocurrió.
 * El bateo del jugador, el line score y el marcador se calculan a partir de estos turnos.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('game_plate_appearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_batting_line_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('inning');
            // Orden del turno dentro de la entrada (si el equipo le da la vuelta al lineup)
            $table->unsignedTinyInteger('sequence')->default(1);
            // Clave de GamePlateAppearance::RESULTS: 1B, BB, KS, GO, ...
            $table->string('result', 4);
            // Anotación del scorebook: 6-3, F8, L4, 5-4-3...
            $table->string('notation', 20)->nullable();
            $table->unsignedTinyInteger('rbi')->default(0);
            // Base a la que llegó el bateador (4 = anotó)
            $table->unsignedTinyInteger('bases')->default(0);
            $table->boolean('scored')->default(false);
            // Out como corredor: anotación (p. ej. 6-4) y turno del lineup en que ocurrió
            $table->boolean('out_on_base')->default(false);
            $table->string('out_notation', 20)->nullable();
            $table->unsignedSmallInteger('out_during_order')->nullable();
            $table->timestamps();

            $table->index(['game_batting_line_id', 'inning']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_plate_appearances');
    }
};
