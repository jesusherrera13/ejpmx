<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ROE (Reached On Error): veces que el bateador se embasó por un error de la defensiva.
 * La jugada del scorecard "E" pasa a llamarse "ROE".
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_batting_lines', function (Blueprint $table) {
            $table->unsignedSmallInteger('roe')->default(0)->after('sh');
        });

        DB::table('game_plate_appearances')->where('result', 'E')->update(['result' => 'ROE']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('game_plate_appearances')->where('result', 'ROE')->update(['result' => 'E']);

        Schema::table('game_batting_lines', function (Blueprint $table) {
            $table->dropColumn('roe');
        });
    }
};
