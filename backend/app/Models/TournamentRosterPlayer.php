<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jugador en el roster de un equipo inscrito en un torneo.
 */
class TournamentRosterPlayer extends Model
{
    protected $fillable = [
        'tournament_team_id',
        'tournament_id',
        'player_id',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class)->withTrashed();
    }
}
