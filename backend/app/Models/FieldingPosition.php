<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Posición de un jugador en el roster: las nueve del campo (number es su número de scoring),
 * el bateador designado y el cuerpo técnico (manager, coach, batboy, staff).
 */
class FieldingPosition extends Model
{
    protected $fillable = [
        'number',
        'code',
        'name',
        'name_en',
        'sort_order',
    ];

    protected $casts = [
        'number' => 'integer',
        'sort_order' => 'integer',
    ];

    public function rosterPlayers(): HasMany
    {
        return $this->hasMany(TournamentRosterPlayer::class);
    }
}
