<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class Blog extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'image',
        'video_url',
        'is_published',
        'content_type_id',
    ];

    // 1. Dile a Laravel que siempre incluya esta nueva propiedad en los JSON
    protected $appends = ['image_url'];

    protected $with = ['contentType'];

    public function contentType()
    {
        return $this->belongsTo(ContentType::class);
    }

    // 2. Define el Accesor
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image 
                ? url(Storage::url($this->image)) 
                : null,
        );
    }
}