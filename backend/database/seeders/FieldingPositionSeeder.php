<?php

namespace Database\Seeders;

use App\Models\FieldingPosition;
use Illuminate\Database\Seeder;

/**
 * Posiciones del roster. Se identifican por código, así se puede volver a correr sin duplicarlas.
 */
class FieldingPositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            [1, 'P', 'Lanzador', 'Pitcher'],
            [2, 'C', 'Receptor', 'Catcher'],
            [3, '1B', 'Primera base', 'First baseman'],
            [4, '2B', 'Segunda base', 'Second baseman'],
            [5, '3B', 'Tercera base', 'Third baseman'],
            [6, 'SS', 'Parador en corto', 'Shortstop'],
            [7, 'LF', 'Jardinero izquierdo', 'Left fielder'],
            [8, 'CF', 'Jardinero central', 'Center fielder'],
            [9, 'RF', 'Jardinero derecho', 'Right fielder'],
            [null, 'DH', 'Bateador designado', 'Designated hitter'],
            [null, 'MGR', 'Manager', 'Manager'],
            [null, 'CH', 'Coach', 'Coach'],
            [null, 'BAT', 'Batboy', 'Batboy'],
            [null, 'STF', 'Staff', 'Staff'],
        ];

        foreach ($positions as $index => [$number, $code, $name, $nameEn]) {
            FieldingPosition::updateOrCreate(['code' => $code], [
                'number' => $number,
                'name' => $name,
                'name_en' => $nameEn,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
