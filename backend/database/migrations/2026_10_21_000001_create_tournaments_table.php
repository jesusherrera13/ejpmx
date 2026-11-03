<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de torneos. Cada usuario tiene el suyo: el nombre es único por usuario sin contar
 * los eliminados (borrado lógico), para poder volver a dar de alta uno que se borró.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            // Juegos de cada serie al generar el calendario (1, 2 o 3)
            $table->unsignedTinyInteger('games_per_series')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // MySQL no tiene índices parciales: la tercera parte del índice es NULL en los eliminados
        // y los NULL no chocan entre sí en un índice único
        DB::statement(DB::getDriverName() === 'mysql'
            ? 'CREATE UNIQUE INDEX tournaments_name_user_id_unique ON tournaments (name, user_id, (IF(deleted_at IS NULL, 1, NULL)))'
            : 'CREATE UNIQUE INDEX tournaments_name_user_id_unique ON tournaments (name, user_id) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
