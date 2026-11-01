<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hits de cuadro (IH) en el bateo, y en cada turno del scorecard hasta qué base llegó el bateador
 * (1 a 4; 4 = home, anotó) y si después lo pusieron out en las bases.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_batting_lines', function (Blueprint $table) {
            $table->unsignedSmallInteger('ih')->default(0)->after('singles');
        });

        Schema::table('game_plate_appearances', function (Blueprint $table) {
            $table->unsignedTinyInteger('bases')->default(0)->after('rbi');
            $table->boolean('out_on_base')->default(false)->after('scored');
        });

        // Los turnos en que ya se había marcado carrera llegaron a home
        DB::table('game_plate_appearances')->where('scored', true)->update(['bases' => 4]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_plate_appearances', function (Blueprint $table) {
            $table->dropColumn(['bases', 'out_on_base']);
        });

        Schema::table('game_batting_lines', function (Blueprint $table) {
            $table->dropColumn('ih');
        });
    }
};
