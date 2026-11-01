<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bateo de un jugador en un partido. Abreviaturas de box score:
 * PA apariciones, AB turnos oficiales, R carreras, H hits (1B, 2B, 3B, HR; IH hits de cuadro, incluidos en 1B),
 * RBI impulsadas, BB bases por bolas (IBB intencionales), HBP golpeado, SF elevado de sacrificio,
 * SH toque de sacrificio, ROE se embasó por error, SO ponches (KS tirándole, KC sin tirarle),
 * LOB corredores que dejó en base al hacer el tercer out, E errores que cometió a la defensiva.
 * is_substitute: entró de cambio y ocupa el turno al bat del renglón anterior.
 */
class GameBattingLine extends Model
{
    // Columnas de estadísticas, en el orden del box score
    public const STATS = ['pa', 'ab', 'r', 'h', 'singles', 'ih', 'doubles', 'triples', 'hr', 'rbi', 'bb', 'ibb', 'hbp', 'sf', 'sh', 'roe', 'so', 'so_swinging', 'so_looking', 'lob', 'e'];

    protected $fillable = [
        'game_id',
        'team_id',
        'player_id',
        'placeholder',
        'batting_order',
        'is_substitute',
        'entered_inning',
        'position',
        ...self::STATS,
    ];

    protected $casts = [
        'is_substitute' => 'boolean',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class)->withTrashed();
    }

    // Turnos del scorecard, en el orden en que ocurrieron
    public function plateAppearances(): HasMany
    {
        return $this->hasMany(GamePlateAppearance::class)->orderBy('inning')->orderBy('sequence');
    }
}
