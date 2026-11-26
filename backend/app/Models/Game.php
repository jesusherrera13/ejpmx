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
        'status',
        'home_score',
        'away_score',
        'boxscore_mode',
        'home_hits',
        'home_errors',
        'away_hits',
        'away_errors',
        'home_lob',
        'away_lob',
        'line_score',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime:Y-m-d H:i:s',
        'line_score' => 'array',
    ];

    // Modalidades de captura del box score, de la más simple a la más completa
    public const BOXSCORE_MODES = ['score', 'linescore', 'batting', 'batting_full', 'scorecard'];

    // Estados que marca el organizador. "scheduled" deja que el portal deduzca el estado del marcador.
    public const STATUSES = ['scheduled', 'in_progress', 'final', 'postponed', 'suspended', 'cancelled'];

    /**
     * Estado que se muestra en el portal. El marcado a mano manda; con "scheduled" se deduce:
     * con carreras capturadas y fecha pasada es Final (si no hay empate), el mismo día es En juego.
     */
    public function effectiveStatus(): string
    {
        if (($this->status ?? 'scheduled') !== 'scheduled') {
            return $this->status;
        }

        $hasScore = $this->home_score !== null && $this->away_score !== null;
        if (! $hasScore || ! $this->scheduled_at) {
            return 'scheduled';
        }

        $day = $this->scheduled_at->toDateString();
        $today = now()->toDateString();

        if ($day > $today) {
            return 'scheduled';
        }

        if ($day < $today && $this->home_score !== $this->away_score) {
            return 'final';
        }

        return 'in_progress';
    }

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
