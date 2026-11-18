<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Reduce las imágenes que se suben antes de guardarlas: las limita a un ancho máximo
 * (sin agrandar las chicas), respeta la orientación de la foto y las guarda en WebP.
 * Una foto de celular de varios MB queda en unos cientos de KB.
 */
class ImageOptimizer
{
    public const MAX_WIDTH = 1600;

    public const MAX_HEIGHT = 1600;

    public const QUALITY = 80;

    /**
     * Guarda la imagen optimizada en el disco indicado y regresa su ruta relativa.
     */
    public function store(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        $manager = new ImageManager(new Driver);

        // read() aplica la orientación EXIF (fotos de celular giradas)
        $image = $manager->read($file->getRealPath());
        $image->scaleDown(width: self::MAX_WIDTH, height: self::MAX_HEIGHT);

        $path = trim($directory, '/').'/'.Str::random(40).'.webp';
        Storage::disk($disk)->put($path, (string) $image->toWebp(self::QUALITY));

        return $path;
    }

    /**
     * Guarda la imagen recortada al centro a un tamaño fijo (p. ej. 800×1000 para formato
     * vertical 4:5), en WebP, y regresa su ruta relativa.
     */
    public function storeCover(UploadedFile $file, string $directory, int $width, int $height, string $disk = 'public'): string
    {
        $manager = new ImageManager(new Driver);

        $image = $manager->read($file->getRealPath());
        $image->cover($width, $height);

        $path = trim($directory, '/').'/'.Str::random(40).'.webp';
        Storage::disk($disk)->put($path, (string) $image->toWebp(self::QUALITY));

        return $path;
    }
}
