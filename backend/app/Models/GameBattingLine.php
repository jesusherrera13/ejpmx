<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bateo de un jugador en un partido. Abreviaturas de box score:
 * PA apariciones, AB turnos oficiales, R carreras, H hits (1B, 2B, 3B, HR),
 * RBI impulsadas, BB bases por bolas (IBB intencionales), HBP golpeado, SF elevado de sacrificio,
 * SH toque de sacrificio, SO ponches (KS tirándole, KC sin tirarle), E errores.
 * is_substitute: entró de cambio y ocupa el turno al bat del renglón anterior.
 */
class GameBattingLine extends Model
{
    // Columnas de estadísticas, en el orden del box score
    public const STATS = ['pa', 'ab', 'r', 'h', 'singles', 'doubles', 'triples', 'hr', 'rbi', 'bb', 'ibb', 'hbp', 'sf', 'sh', 'so', 'so_swinging', 'so_looking', 'e'];

    protected $fillable = [
        'game_id',
        'team_id',
        'player_id',
        'batting_order',
        'is_substitute',
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
