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
        // Hit de cuadro: sencillo que no salió del cuadro (cuenta también en 1B)
        'IH' => ['singles', 'ih', 'h'],
        '2B' => ['doubles', 'h'],
        '3B' => ['triples', 'h'],
        'HR' => ['hr', 'h'],
        'BB' => ['bb'],
        'IBB' => ['bb', 'ibb'],
        'HBP' => ['hbp'],
        'KS' => ['so_swinging', 'so'],
        'KC' => ['so_looking', 'so'],
        // Tercer strike no retenido (dropped third strike): es ponche y turno oficial, pero el
        // bateador se embasa por wild pitch o passed ball (KDS tirándole, KDC cantado)
        'KDS' => ['so_swinging', 'so'],
        'KDC' => ['so_looking', 'so'],
        'GO' => [],
        'FO' => [],
        'LO' => [],
        // Bateó para doble play (GIDP)
        'DP' => ['gidp'],
        'FC' => [],
        // Se embasó por error de la defensiva (Reached On Error): es turno oficial, no hit
        'ROE' => ['roe'],
        'SF' => ['sf'],
        'SH' => ['sh'],
    ];

    public const NOT_AT_BAT = ['BB', 'IBB', 'HBP', 'SF', 'SH'];

    // Jugadas en que el bateador se embasa (en las demás es out y no tiene corrido)
    public const REACHES = ['1B', 'IH', '2B', '3B', 'HR', 'BB', 'IBB', 'HBP', 'KDS', 'KDC', 'FC', 'ROE'];

    /**
     * Outs de la jugada del bateador (reglas de The Perfect Boxscore): en un DP es solo el del bateador
     * (el del corredor se suma cuando se indica quién fue, dp_runner_order) y en la bola ocupada (FC)
     * el bateador se embasa: el corredor puesto out se marca "Out en base" en su propio turno.
     */
    public const OUTS = ['KS' => 1, 'KC' => 1, 'GO' => 1, 'FO' => 1, 'LO' => 1, 'SF' => 1, 'SH' => 1, 'DP' => 1];

    // Cómo fue el out en base: PK revire (pickoff), PC pisa y corre
    public const OUT_TYPES = ['PK', 'PC'];

    // Bases que se pueden robar: 2da, 3ra y home
    public const STEALABLE = [2, 3, 4];

    protected $fillable = [
        'game_batting_line_id',
        'inning',
        'sequence',
        'result',
        'notation',
        'rbi',
        'bases',
        'scored',
        'out_on_base',
        'out_notation',
        'out_during_order',
        'out_type',
        'stolen_bases',
        'dp_runner_order',
        'dp_notation',
    ];

    protected $casts = [
        'scored' => 'boolean',
        'out_on_base' => 'boolean',
    ];

    public function battingLine(): BelongsTo
    {
        return $this->belongsTo(GameBattingLine::class, 'game_batting_line_id');
    }

    /**
     * Bases robadas como lista de enteros (se guardan como "2,3").
     *
     * @return int[]
     */
    public static function parseSteals(?string $value): array
    {
        return array_values(array_intersect(self::STEALABLE, array_map('intval', array_filter(explode(',', (string) $value)))));
    }

    /**
     * Estadísticas de bateo de una lista de turnos: PA, AB, R, H, 1B... RBI, BB, SO, SB, PK, GIDP.
     * Sin LOB (depende del orden de los turnos en la entrada) ni E (es defensiva).
     *
     * @param  iterable<array{result: string, rbi?: int, scored?: bool, stolen_bases?: ?string, out_on_base?: bool, out_type?: ?string}>  $appearances
     * @return array<string, int>
     */
    public static function stats(iterable $appearances): array
    {
        $stats = array_fill_keys(array_diff(GameBattingLine::STATS, ['lob', 'e']), 0);

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
            $stats['sb'] += count(self::parseSteals($appearance['stolen_bases'] ?? null));
            $stats['pk'] += ! empty($appearance['out_on_base']) && ($appearance['out_type'] ?? null) === 'PK' ? 1 : 0;
        }

        return $stats;
    }
}
