<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LOB individual: corredores que el bateador dejó en base al hacer el tercer out de la entrada.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_batting_lines', function (Blueprint $table) {
            $table->unsignedSmallInteger('lob')->default(0)->after('so_looking');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_batting_lines', function (Blueprint $table) {
            $table->dropColumn('lob');
        });
    }
};
