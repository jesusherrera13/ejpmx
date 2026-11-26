<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reglas del scorecard de The Perfect Boxscore (aditiva: solo agrega columnas, no toca datos existentes).
 *
 * Turnos (game_plate_appearances):
 *  - out_type: cómo fue el out en base: PK revire (pickoff) o PC pisa y corre; null = otro out en base.
 *    La secuencia (1-3, 7-2...) sigue en out_notation.
 *  - stolen_bases: bases que robó en el turno, separadas por coma (2, 3, 4 = home).
 *  - dp_runner_order: en un DP del bateador, renglón del lineup (1..n) del corredor que fue el otro out.
 *  - dp_notation: en el turno de ese corredor, su parte del doble play (6-4, 5-4...).
 *
 * Bateo (game_batting_lines): sb bases robadas, pk outs por revire, gidp bateó para doble play.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_plate_appearances', function (Blueprint $table) {
            if (! Schema::hasColumn('game_plate_appearances', 'out_type')) {
                $table->string('out_type', 2)->nullable()->after('out_notation');
            }
            if (! Schema::hasColumn('game_plate_appearances', 'stolen_bases')) {
                $table->string('stolen_bases', 8)->nullable()->after('bases');
            }
            if (! Schema::hasColumn('game_plate_appearances', 'dp_runner_order')) {
                $table->unsignedSmallInteger('dp_runner_order')->nullable()->after('out_during_order');
            }
            if (! Schema::hasColumn('game_plate_appearances', 'dp_notation')) {
                $table->string('dp_notation', 20)->nullable()->after('dp_runner_order');
            }
        });

        Schema::table('game_batting_lines', function (Blueprint $table) {
            foreach (['sb', 'pk', 'gidp'] as $column) {
                if (! Schema::hasColumn('game_batting_lines', $column)) {
                    $table->unsignedSmallInteger($column)->default(0)->after('lob');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('game_plate_appearances', function (Blueprint $table) {
            $table->dropColumn(['out_type', 'stolen_bases', 'dp_runner_order', 'dp_notation']);
        });

        Schema::table('game_batting_lines', function (Blueprint $table) {
            $table->dropColumn(['sb', 'pk', 'gidp']);
        });
    }
};
