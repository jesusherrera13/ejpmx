<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Publicidad de un patrocinador. Se muestra entre starts_at y ends_at (fechas incluidas),
 * o siempre si is_permanent.
 */
class Advertising extends Model
{
    protected $table = 'advertisings';

    protected $fillable = [
        'title',
        'content',
        'content_type_id',
        'sponsor_id',
        'image',
        'video_url',
        'link_url',
        'starts_at',
        'ends_at',
        'is_permanent',
        'is_active',
    ];

    protected $casts = [
        'starts_at' => 'date:Y-m-d',
        'ends_at' => 'date:Y-m-d',
        'is_permanent' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $appends = ['image_url', 'status'];

    protected $with = ['contentType', 'sponsor:id,name,logo'];

    public function contentType(): BelongsTo
    {
        return $this->belongsTo(ContentType::class);
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    /**
     * Publicidad vigente en una fecha: permanente, o con la fecha dentro de su rango.
     */
    public function scopeActiveOn(Builder $query, Carbon|string $date): Builder
    {
        $day = Carbon::parse($date)->toDateString();

        return $query->where(fn (Builder $q) => $q
            ->where('is_permanent', true)
            ->orWhere(fn (Builder $range) => $range
                ->where(fn (Builder $s) => $s->whereNull('starts_at')->orWhereDate('starts_at', '<=', $day))
                ->where(fn (Builder $e) => $e->whereNull('ends_at')->orWhereDate('ends_at', '>=', $day))
            )
        );
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image ? url(Storage::url($this->image)) : null,
        );
    }

    // Estado según hoy: inactiva, programada, vigente o vencida
    protected function status(): Attribute
    {
        return Attribute::make(get: function () {
            if (! $this->is_active) {
                return 'inactive';
            }
            if ($this->is_permanent) {
                return 'active';
            }
            $today = Carbon::today();
            if ($this->starts_at && $this->starts_at->gt($today)) {
                return 'scheduled';
            }
            if ($this->ends_at && $this->ends_at->lt($today)) {
                return 'expired';
            }

            return 'active';
        });
    }
}
