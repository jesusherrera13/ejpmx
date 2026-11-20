<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo de posiciones: las nueve en el campo (con su número de scoring), el bateador designado y el cuerpo técnico
        Schema::create('fielding_positions', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('number')->nullable();
            $table->string('code', 5)->unique();
            $table->string('name', 60);
            $table->string('name_en', 60);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('tournament_roster_players', function (Blueprint $table) {
            $table->foreignId('fielding_position_id')->nullable()->after('player_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_roster_players', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fielding_position_id');
        });

        Schema::dropIfExists('fielding_positions');
    }
};
