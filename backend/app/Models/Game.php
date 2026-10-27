<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'round',
        'matchday',
        'series',
        'series_game',
        'game_number',
        'home_team_id',
        'away_team_id',
        'scheduled_date',
        'home_score',
        'away_score',
    ];

    protected $casts = [
        'scheduled_date' => 'date:Y-m-d',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id')->withTrashed();
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id')->withTrashed();
    }
}
