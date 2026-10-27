<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Borrado lógico (deleted_at) en equipos, jugadores y torneos.
 * Las llaves únicas por usuario pasan a ser índices parciales que ignoran los registros
 * eliminados, para poder volver a dar de alta un nombre/CURP que se borró.
 */
return new class extends Migration
{
    // tabla => columna única por usuario
    private const TABLES = [
        'teams' => 'name',
        'players' => 'curp',
        'tournaments' => 'name',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABLES as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->softDeletes();
                $blueprint->dropUnique([$column, 'user_id']);
            });

            DB::statement("CREATE UNIQUE INDEX {$table}_{$column}_user_id_unique ON {$table} ({$column}, user_id) WHERE deleted_at IS NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::TABLES as $table => $column) {
            DB::statement("DROP INDEX IF EXISTS {$table}_{$column}_user_id_unique");

            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropSoftDeletes();
                $blueprint->unique([$column, 'user_id']);
            });
        }
    }
};
