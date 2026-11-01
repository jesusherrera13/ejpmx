<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bateo detallado: bases por bolas (BB, IBB), golpeados (HBP), sacrificios (SF, SH)
 * y si el jugador entró de cambio (ocupa el turno del bateador anterior en el lineup).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_batting_lines', function (Blueprint $table) {
            $table->boolean('is_substitute')->default(false)->after('batting_order');
            $table->unsignedSmallInteger('bb')->default(0)->after('rbi');
            $table->unsignedSmallInteger('ibb')->default(0)->after('bb');
            $table->unsignedSmallInteger('hbp')->default(0)->after('ibb');
            $table->unsignedSmallInteger('sf')->default(0)->after('hbp');
            $table->unsignedSmallInteger('sh')->default(0)->after('sf');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_batting_lines', function (Blueprint $table) {
            $table->dropColumn(['is_substitute', 'bb', 'ibb', 'hbp', 'sf', 'sh']);
        });
    }
};
