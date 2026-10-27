<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el propietario (user_id) a equipos, jugadores y torneos.
 * Las llaves únicas pasan a ser por usuario: cada usuario tiene su propio catálogo.
 */
return new class extends Migration
{
    // tabla => columna que era única globalmente
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
        // Los registros existentes se asignan al primer usuario
        $ownerId = DB::table('users')->orderBy('id')->value('id');

        foreach (self::TABLES as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('user_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            });

            if ($ownerId) {
                DB::table($table)->whereNull('user_id')->update(['user_id' => $ownerId]);
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->foreignId('user_id')->nullable(false)->change();
                $blueprint->dropUnique([$column]);
                $blueprint->unique([$column, 'user_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::TABLES as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropUnique([$column, 'user_id']);
                $blueprint->unique([$column]);
                $blueprint->dropConstrainedForeignId('user_id');
            });
        }
    }
};
