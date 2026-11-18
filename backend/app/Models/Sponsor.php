<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Sponsor extends Model
{
    protected $fillable = [
        'name',
        'contact_name',
        'email',
        'phone',
        'website',
        'logo',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['logo_url'];

    public function advertisings(): HasMany
    {
        return $this->hasMany(Advertising::class);
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->logo ? url(Storage::url($this->logo)) : null,
        );
    }
}
