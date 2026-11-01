<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Turno al bat capturado en el scorecard.
 */
class GamePlateAppearance extends Model
{
    /**
     * Jugadas del scorecard y la estadística de bateo que suman.
     * Las que no cuentan como turno oficial (AB) son BB, IBB, HBP, SF y SH.
     */
    public const RESULTS = [
        '1B' => ['singles', 'h'],
        '2B' => ['doubles', 'h'],
        '3B' => ['triples', 'h'],
        'HR' => ['hr', 'h'],
        'BB' => ['bb'],
        'IBB' => ['bb', 'ibb'],
        'HBP' => ['hbp'],
        'KS' => ['so_swinging', 'so'],
        'KC' => ['so_looking', 'so'],
        'GO' => [],
        'FO' => [],
        'LO' => [],
        'DP' => [],
        'FC' => [],
        'E' => [],
        'SF' => ['sf'],
        'SH' => ['sh'],
    ];

    public const NOT_AT_BAT = ['BB', 'IBB', 'HBP', 'SF', 'SH'];

    protected $fillable = [
        'game_batting_line_id',
        'inning',
        'sequence',
        'result',
        'notation',
        'rbi',
        'scored',
    ];

    protected $casts = [
        'scored' => 'boolean',
    ];

    public function battingLine(): BelongsTo
    {
        return $this->belongsTo(GameBattingLine::class, 'game_batting_line_id');
    }

    /**
     * Estadísticas de bateo de una lista de turnos: PA, AB, R, H, 1B... RBI, BB, SO.
     *
     * @param  iterable<array{result: string, rbi?: int, scored?: bool}>  $appearances
     * @return array<string, int>
     */
    public static function stats(iterable $appearances): array
    {
        $stats = array_fill_keys(array_diff(GameBattingLine::STATS, ['e']), 0);

        foreach ($appearances as $appearance) {
            $stats['pa']++;
            if (! in_array($appearance['result'], self::NOT_AT_BAT, true)) {
                $stats['ab']++;
            }
            foreach (self::RESULTS[$appearance['result']] as $column) {
                $stats[$column]++;
            }
            $stats['rbi'] += (int) ($appearance['rbi'] ?? 0);
            $stats['r'] += empty($appearance['scored']) ? 0 : 1;
        }

        return $stats;
    }
}
