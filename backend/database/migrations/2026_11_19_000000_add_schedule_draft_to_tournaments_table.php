<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Borrador del calendario: parámetros y partidos (con los cambios manuales) aún sin guardar como calendario
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->json('schedule_draft')->nullable()->after('games_per_series');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('schedule_draft');
        });
    }
};
