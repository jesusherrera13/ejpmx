<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;

abstract class Controller
{
    // Solo el propietario (user_id) o un administrador puede ver o modificar el registro; a los demás les responde 404
    protected function ensureOwner(Model $model): void
    {
        abort_unless($model->isVisibleTo(auth()->user()), 404);
    }
}
