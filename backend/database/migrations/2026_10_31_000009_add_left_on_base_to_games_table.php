<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LOB (Left On Base): corredores que cada equipo dejó en base al caer el tercer out de sus entradas.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedSmallInteger('home_lob')->nullable()->after('home_errors');
            $table->unsignedSmallInteger('away_lob')->nullable()->after('away_errors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['home_lob', 'away_lob']);
        });
    }
};
