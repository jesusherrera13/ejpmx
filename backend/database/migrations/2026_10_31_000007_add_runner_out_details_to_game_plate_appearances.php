<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cómo pusieron out al corredor en las bases (6-5, CS2-6, PO1-3...) y en el turno de qué bateador
 * de su equipo ocurrió (batting_order de ese bateador; null = en su propio turno).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_plate_appearances', function (Blueprint $table) {
            $table->string('out_notation', 20)->nullable()->after('out_on_base');
            $table->unsignedSmallInteger('out_during_order')->nullable()->after('out_notation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_plate_appearances', function (Blueprint $table) {
            $table->dropColumn(['out_notation', 'out_during_order']);
        });
    }
};
