<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro con propietario: el usuario que lo creó o lo subió en una plantilla.
 */
trait BelongsToUser
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOwnedBy(Builder $query, User|int $user): Builder
    {
        return $query->where($this->getTable() . '.user_id', $user instanceof User ? $user->id : $user);
    }

    // Los administradores ven los registros de todos; el resto, solo los suyos
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->ownedBy($user);
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->isAdmin() || (int) $this->user_id === (int) $user->id;
    }
}
