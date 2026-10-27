<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Player extends Model
{
    use BelongsToUser, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'second_last_name',
        'birthdate',
        'curp',
        'is_active',
    ];

    protected $casts = [
        'birthdate' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];
}
