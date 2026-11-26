<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - games.status: estado que marca el organizador (scheduled, in_progress, final, postponed,
     *   suspended, cancelled). Con "scheduled" el portal deduce En juego / Final del marcador.
     * - tournaments.is_public: el torneo aparece en el portal público (ticker y Resultados).
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('status', 20)->default('scheduled')->after('scheduled_at');
            $table->index(['tournament_id', 'scheduled_at']);
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('is_active');
        });

        // Los torneos que ya existían se publican para que el portal tenga datos desde hoy
        DB::table('tournaments')->update(['is_public' => true]);
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['tournament_id', 'scheduled_at']);
            $table->dropColumn('status');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
