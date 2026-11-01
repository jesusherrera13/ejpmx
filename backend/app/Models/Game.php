<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'scheduled_at',
        'home_score',
        'away_score',
        'boxscore_mode',
        'home_hits',
        'home_errors',
        'away_hits',
        'away_errors',
        'line_score',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime:Y-m-d H:i:s',
        'line_score' => 'array',
    ];

    // Modalidades de captura del box score, de la más simple a la más completa
    public const BOXSCORE_MODES = ['score', 'linescore', 'batting', 'batting_full', 'scorecard'];

    // "19:30", o null si el partido aún no tiene hora (00:00)
    public function scheduledTime(): ?string
    {
        $time = $this->scheduled_at?->format('H:i');

        return $time === '00:00' ? null : $time;
    }

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

    public function battingLines(): HasMany
    {
        return $this->hasMany(GameBattingLine::class)->orderBy('batting_order');
    }
}
