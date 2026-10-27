<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tournament extends Model
{
    use BelongsToUser, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Equipos inscritos, en el orden en que se agregaron
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'tournament_team')->withTimestamps()->orderBy('tournament_team.id');
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }
}
