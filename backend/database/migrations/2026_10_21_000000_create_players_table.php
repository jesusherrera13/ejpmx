<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de jugadores. Cada usuario tiene el suyo: la CURP es única por usuario sin contar
 * los eliminados (borrado lógico), para poder volver a dar de alta uno que se borró.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('second_last_name', 100)->nullable();
            $table->date('birthdate')->nullable();
            $table->string('curp', 18)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // MySQL no tiene índices parciales: la tercera parte del índice es NULL en los eliminados
        // y los NULL no chocan entre sí en un índice único
        DB::statement(DB::getDriverName() === 'mysql'
            ? 'CREATE UNIQUE INDEX players_curp_user_id_unique ON players (curp, user_id, (IF(deleted_at IS NULL, 1, NULL)))'
            : 'CREATE UNIQUE INDEX players_curp_user_id_unique ON players (curp, user_id) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
